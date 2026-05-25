<?php
/*
 * Copyright (C) 2016 Márcio A. Tamashiro
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace NDSE\Tools;

use NDSE\Math\Angle;
use NDSE\Math\Complex;
use NDSE\Math\Matrix;
use NDSE\Math\LinAlg;
use NDSE\Models\Gen\Gen1;
use NDSE\Models\Gen\Gen2;
use NDSE\Models\Exc\Exc1;
use NDSE\Models\Exc\Exc2;

/**
 * Transient Stability Analysis using the Implicit Trapezoidal Method
 * with Newton-Raphson corrector (industry-standard algorithm used in PSS/E).
 *
 * Algorithm (Modified Euler Predictor + Trapezoidal Corrector):
 *
 *   PREDICTOR (Euler explicit):
 *     x_pred = x_n + h * f(x_n, U_n)
 *
 *   CORRECTOR (Newton-Raphson on trapezoidal residual):
 *     G(x_{n+1}) = x_{n+1} - x_n - (h/2)*[f(x_n, U_n) + f(x_{n+1}, U_{n+1})] = 0
 *
 *     Newton iteration:
 *       J = I - (h/2) * df/dx   (Jacobian of G, approximated by finite differences)
 *       x_{n+1}^{k+1} = x_{n+1}^k - J^{-1} * G(x_{n+1}^k)
 *
 * The trapezoidal method is A-stable (unconditionally stable for linear systems),
 * making it suitable for stiff power system models with AVR, governors, etc.
 * It is 2nd-order accurate and has no numerical damping (energy-conservative).
 *
 * Model independence: the solver dispatches through the generic dFx() and Gy()
 * interfaces defined in AbstractModel, so any generator model that implements
 * those two methods is automatically supported without changes to this file.
 *
 * @author Márcio A. Tamashiro (original); Trapezoidal/NR refactor added.
 */
class TransientAnalysis extends AbstractTools
{
    /** Newton-Raphson maximum iterations for corrector */
    const NR_MAX_ITER = 20;

    /** Newton-Raphson convergence tolerance */
    const NR_TOL = 1e-8;

    /** Finite-difference perturbation for Jacobian approximation */
    const FD_EPS = 1e-6;

    /**
     * Default simulation options.
     */
    protected $option = [
        'freq0'     => 60,
        'starttime' => 0,
        'stoptime'  => 1,
        'stepsize'  => 1e-2,
    ];

    /**
     * Constructor — maps optTA array (indexed) into named option keys.
     */
    public function __construct($data)
    {
        $this->data = $data;

        $idx = array_keys($this->option);
        foreach ($this->data['optTA'] as $k => $v) {
            $this->option[$idx[$k]] = $v;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: count generators by model number
    // ─────────────────────────────────────────────────────────────────────────
    public function getNmodel($name, $model)
    {
        $name = strtolower($name);

        if (is_numeric($model) && $model >= 0) {
            $gen = new Matrix($this->getData('gen'));

            if ($name == 'gen') {
                return count($gen->subMatrix([0, '==', $model], [])->get());
            }
            if ($name == 'exc') {
                return count($gen->subMatrix([1, '==', $model], [])->get());
            }
            if ($name == 'gov') {
                return count($gen->subMatrix([2, '==', $model], [])->get());
            }
        }
        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Build augmented admittance matrix (loads + generator Norton equivalents)
    // ─────────────────────────────────────────────────────────────────────────
    public function makeYaug($U, $lf, $xd_tr)
    {
        // Clone Ybus to avoid modifying the LoadFlow's internal matrix
        $Yraw  = $lf->getYbus();
        $Y     = clone $Yraw;
        $Sbase = $lf->getOption('sbase');
        $ngen  = $lf->getNtype('pv') + 1;
        $nbus  = $lf->getN('bus');
        $bus   = new Matrix($lf->getData('bus'));

        $P  = $bus->get([], 4);   // Pl (load active power, MW)
        $Q  = $bus->get([], 5);   // Ql (load reactive power, MVAR)
        $Gs = $bus->get([], 6);   // Shunt conductance (pu on Sbase) — used for fault injection
        $Bs = $bus->get([], 7);   // Shunt susceptance (pu on Sbase)

        // Add constant-impedance load admittances AND shunt elements to diagonal
        for ($i = 0; $i < $nbus; $i++) {
            $Pl = $P[$i][0] / $Sbase;
            $Ql = $Q[$i][0] / $Sbase;

            // Constant-impedance load: y_load = S* / |V|^2
            $yload = (new Complex($Pl, -$Ql))->div(pow($U[$i]->abs(), 2));

            // Shunt element: y_shunt = Gs + j*Bs  (already in pu)
            $gs_val = isset($Gs[$i][0]) ? (float)$Gs[$i][0] : 0.0;
            $bs_val = isset($Bs[$i][0]) ? (float)$Bs[$i][0] : 0.0;
            $yshunt = new Complex($gs_val, $bs_val);

            $ytotal = $yload->add($yshunt);
            $diag   = $Y->get($i, $i);
            $Y->set(is_null($diag) ? $ytotal : $diag->add($ytotal), $i, $i);
        }

        // Add generator Norton admittances (1 / j*xd') to diagonal
        $xd_flat = is_array($xd_tr[0]) ? $xd_tr[0] : $xd_tr;
        for ($i = 0; $i < $ngen; $i++) {
            $ygen = (new Complex(0, $xd_flat[$i]))->inv();
            $diag = $Y->get($i, $i);
            $Y->set(is_null($diag) ? $ygen : $diag->add($ygen), $i, $i);
        }

        return $Y;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Instantiate a generator object from model number
    // ─────────────────────────────────────────────────────────────────────────
    private function makeGenObj($model, $data_gen)
    {
        switch ($model) {
            case 1:  return new Gen1($data_gen);
            case 2:  return new Gen2($data_gen);
            default: throw new \RuntimeException("Unsupported generator model: $model");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Instantiate an exciter object from model number
    // ─────────────────────────────────────────────────────────────────────────
    private function makeExcObj($model, $data_exc)
    {
        switch ($model) {
            case 1:  return new Exc1($data_exc);
            case 2:  return new Exc2($data_exc);
            default: throw new \RuntimeException("Unsupported exciter model: $model");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Apply current state vector to generator objects
    // ─────────────────────────────────────────────────────────────────────────
    private function applyGenState(array &$gg, Matrix $Xgen, array $genmodel)
    {
        $Xt = $Xgen->transpose();
        foreach ($genmodel as $i => $m) {
            $gg[$i]->set('delta',  $Xt->get(0, $i));
            $gg[$i]->set('Eq_tr', $Xt->get(2, $i));
            $gg[$i]->set('Ed_tr', $Xt->get(3, $i));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Solve network: given generator states, compute bus voltages U and Pe
    // ─────────────────────────────────────────────────────────────────────────
    private function solveNetwork(
        array &$gg, Matrix $Xgen, array $genmodel, array $genbus,
        $nbus, $LU, array $U_prev
    ) {
        $this->applyGenState($gg, $Xgen, $genmodel);

        $Ig = array_fill(0, $nbus, 0);
        foreach ($genmodel as $i => $m) {
            $id      = $genbus[$i] - 1;
            $Ggen    = $gg[$i]->Gy($U_prev[$id]);
            $Ig[$id] = $Ggen[1];
        }

        $U = LinAlg::LUsolver($LU, $Ig);

        // Recompute Pe with updated voltages
        $Pe = [];
        foreach ($genmodel as $i => $m) {
            $id   = $genbus[$i] - 1;
            $Ggen = $gg[$i]->Gy($U[$id]);
            $Pe[] = $Ggen[0];
        }

        return [$U, $Pe];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Evaluate generator state derivatives f(X, Pe, Pm, omega)
    // Returns flat array of derivative vectors [ngen][ncols]
    // ─────────────────────────────────────────────────────────────────────────
    private function evalGenDerivatives(
        array &$gg, Matrix $Xgen, array $genmodel, array $genbus,
        array $Pm, array $Pe,
        $exc_on, array $excmodel, array $excbus, array &$ex, Matrix $Xexc
    ) {
        $omega = $Xgen->transpose()->get(1, []);
        $dX    = [];
        $idex  = 0;

        foreach ($genmodel as $i => $m) {
            if ($exc_on && $m >= 2) {
                $gg[$i]->set('Efd', $Xexc->transpose()->get(0, $idex));
                $idex++;
            }
            $dX[] = $gg[$i]->dFx([$Pm[$i], $omega[$i], $Pe[$i]]);
        }

        return new Matrix($dX);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Evaluate exciter derivatives
    // ─────────────────────────────────────────────────────────────────────────
    private function evalExcDerivatives(
        array &$ex, array $excmodel, array $excbus,
        array $U, Matrix $Xexc
    ) {
        $dXexc = [];
        foreach ($excmodel as $i => $m) {
            $id      = $excbus[$i] - 1;
            $Xt      = $Xexc->transpose();
            $in      = [
                $U[$id]->abs(),
                $Xt->get(0, $i),
                $Xt->get(1, $i),
                $Xt->get(2, $i),
            ];
            $dXexc[] = $ex[$i]->dFx($in);
        }
        return new Matrix($dXexc);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Convert Matrix to flat PHP array of floats
    // ─────────────────────────────────────────────────────────────────────────
    private function matToFlat(Matrix $M)
    {
        $rows = $M->getN('rows');
        $cols = $M->getN('cols');
        $flat = [];
        for ($i = 0; $i < $rows; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                $flat[] = $M->get($i, $j);
            }
        }
        return $flat;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Build Matrix from flat array (same shape as reference Matrix)
    // ─────────────────────────────────────────────────────────────────────────
    private function flatToMat(array $flat, Matrix $ref)
    {
        $rows = $ref->getN('rows');
        $cols = $ref->getN('cols');
        $arr  = [];
        $k    = 0;
        for ($i = 0; $i < $rows; $i++) {
            $row = [];
            for ($j = 0; $j < $cols; $j++) {
                $row[] = $flat[$k++];
            }
            $arr[] = $row;
        }
        return new Matrix($arr);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Evaluate trapezoidal residual G(x1) = x1 - x0 - (h/2)*(f0 + f1)
    // where f0 is pre-computed (fixed), f1 = f(x1)
    // Returns flat array of residuals
    // ─────────────────────────────────────────────────────────────────────────
    private function trapResidual(
        array $x1_flat, array $x0_flat, array $f0_flat,
        $h,
        array &$gg, Matrix $Xgen_ref, array $genmodel, array $genbus,
        array $Pm, $exc_on, array $excmodel, array $excbus, array &$ex,
        Matrix $Xexc_ref, $nbus, $LU, array $U_prev
    ) {
        // Rebuild Matrix from flat array
        $Xgen1 = $this->flatToMat($x1_flat, $Xgen_ref);

        // Solve network at x1
        list($U1, $Pe1) = $this->solveNetwork(
            $gg, $Xgen1, $genmodel, $genbus, $nbus, $LU, $U_prev
        );

        // Evaluate f(x1)
        $f1_mat = $this->evalGenDerivatives(
            $gg, $Xgen1, $genmodel, $genbus,
            $Pm, $Pe1, $exc_on, $excmodel, $excbus, $ex, $Xexc_ref
        );
        $f1_flat = $this->matToFlat($f1_mat);

        // G(x1) = x1 - x0 - (h/2)*(f0 + f1)
        $n   = count($x1_flat);
        $res = [];
        for ($i = 0; $i < $n; $i++) {
            $res[] = $x1_flat[$i] - $x0_flat[$i] - ($h / 2.0) * ($f0_flat[$i] + $f1_flat[$i]);
        }
        return $res;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Newton-Raphson corrector for the trapezoidal method
    // Solves G(x1) = 0 starting from x1_init (predictor solution)
    // Returns corrected flat state array
    // ─────────────────────────────────────────────────────────────────────────
    private function newtonCorrector(
        array $x1_init, array $x0_flat, array $f0_flat,
        $h,
        array &$gg, Matrix $Xgen_ref, array $genmodel, array $genbus,
        array $Pm, $exc_on, array $excmodel, array $excbus, array &$ex,
        Matrix $Xexc_ref, $nbus, $LU, array $U_prev
    ) {
        $x1 = $x1_init;
        $n  = count($x1);

        for ($iter = 0; $iter < self::NR_MAX_ITER; $iter++) {

            // Evaluate residual G(x1)
            $G = $this->trapResidual(
                $x1, $x0_flat, $f0_flat, $h,
                $gg, $Xgen_ref, $genmodel, $genbus,
                $Pm, $exc_on, $excmodel, $excbus, $ex,
                $Xexc_ref, $nbus, $LU, $U_prev
            );

            // Check convergence: ||G||_inf < tol
            $norm = 0.0;
            foreach ($G as $gi) {
                $norm = max($norm, abs($gi));
            }
            if ($norm < self::NR_TOL) {
                break;
            }

            // Build Jacobian J = dG/dx1 by forward finite differences
            // J_ij = (G_i(x1 + eps*e_j) - G_i(x1)) / eps
            $J = [];
            for ($j = 0; $j < $n; $j++) {
                $x1p = $x1;
                $x1p[$j] += self::FD_EPS;
                $Gp = $this->trapResidual(
                    $x1p, $x0_flat, $f0_flat, $h,
                    $gg, $Xgen_ref, $genmodel, $genbus,
                    $Pm, $exc_on, $excmodel, $excbus, $ex,
                    $Xexc_ref, $nbus, $LU, $U_prev
                );
                $col = [];
                for ($i = 0; $i < $n; $i++) {
                    $col[] = ($Gp[$i] - $G[$i]) / self::FD_EPS;
                }
                $J[] = $col;
            }

            // Solve J * dx = G  using Gaussian elimination with partial pivoting
            $dx = $this->solveLinearSystem($J, $G, $n);

            // Update: x1 = x1 - dx
            for ($i = 0; $i < $n; $i++) {
                $x1[$i] -= $dx[$i];
            }
        }

        return $x1;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Gaussian elimination with partial pivoting to solve J*dx = G
    // J is stored as J[col][row] (column-major from finite differences)
    // ─────────────────────────────────────────────────────────────────────────
    private function solveLinearSystem(array $J_colmaj, array $b, $n)
    {
        // Convert column-major J to row-major augmented matrix [A|b]
        $A = [];
        for ($i = 0; $i < $n; $i++) {
            $row = [];
            for ($j = 0; $j < $n; $j++) {
                $row[] = $J_colmaj[$j][$i];  // J[col][row] -> A[row][col]
            }
            $row[] = $b[$i];
            $A[]   = $row;
        }

        // Forward elimination with partial pivoting
        for ($col = 0; $col < $n; $col++) {
            // Find pivot
            $maxVal = abs($A[$col][$col]);
            $maxRow = $col;
            for ($row = $col + 1; $row < $n; $row++) {
                if (abs($A[$row][$col]) > $maxVal) {
                    $maxVal = abs($A[$row][$col]);
                    $maxRow = $row;
                }
            }
            // Swap rows
            if ($maxRow !== $col) {
                $tmp       = $A[$col];
                $A[$col]   = $A[$maxRow];
                $A[$maxRow] = $tmp;
            }
            // Eliminate below
            $pivot = $A[$col][$col];
            if (abs($pivot) < 1e-15) continue;  // singular — skip
            for ($row = $col + 1; $row < $n; $row++) {
                $factor = $A[$row][$col] / $pivot;
                for ($k = $col; $k <= $n; $k++) {
                    $A[$row][$k] -= $factor * $A[$col][$k];
                }
            }
        }

        // Back substitution
        $x = array_fill(0, $n, 0.0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $sum = $A[$i][$n];
            for ($j = $i + 1; $j < $n; $j++) {
                $sum -= $A[$i][$j] * $x[$j];
            }
            $x[$i] = (abs($A[$i][$i]) > 1e-15) ? $sum / $A[$i][$i] : 0.0;
        }

        return $x;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Main simulation loop — Implicit Trapezoidal Method with Newton-Raphson
    // ─────────────────────────────────────────────────────────────────────────
    public function run()
    {
        // ── Load Flow initialisation ──────────────────────────────────────────
        $lfData = $this->data;
        if (!isset($lfData['optLF'])) {
            $lfData['optLF'] = [100, 100, 1e-6, 0];
        }
        $lf = new LoadFlow($lfData);
        $lf->makeYbus();

        $resLF      = json_decode($lf->run());
        $resLF      = (new Matrix($resLF->bus))->transpose();
        $resLF_Umag = $resLF->get(1, []);
        $resLF_Uang = $resLF->get(2, []);
        $resLF_P    = $resLF->get(3, []);
        $resLF_Q    = $resLF->get(4, []);

        $nbus    = $lf->getN('bus');
        $nbranch = $lf->getN('branch');
        $bus     = $lf->getData('bus');
        $branch  = $lf->getData('branch');

        // ── Simulation options ────────────────────────────────────────────────
        $starttime = $this->getOption('startTime');
        $stoptime  = $this->getOption('stopTime');
        $stepsize  = $this->getOption('stepSize');
        $freq0     = $this->getOption('freq0');

        // ── Event list ────────────────────────────────────────────────────────
        $event = new Matrix($this->getData('event'));

        // ── Generator data ────────────────────────────────────────────────────
        $gen      = new Matrix($this->getData('gen'));
        $ngen     = $gen->getN('rows');
        $genbus   = $gen->transpose()->get(0, []);
        $genmodel = $gen->transpose()->get(1, []);

        // ── Exciter data (optional) ───────────────────────────────────────────
        $exc_on   = 0;
        $excmodel = [];
        $excbus   = [];
        $ex       = [];

        $excData = $this->getData('exc');
        if ($excData !== null && is_array($excData) && count($excData) > 0) {
            $excMat   = new Matrix($excData);
            $excmodel = $excMat->transpose()->get(1, []);
            $excbus   = $excMat->transpose()->get(0, []);
            $exc_on   = 1;
        }

        // ── Initial bus voltages (phasor) ─────────────────────────────────────
        $U0 = [];
        for ($i = 0; $i < $nbus; $i++) {
            $U0[] = new Complex($resLF_Umag[$i], new Angle($resLF_Uang[$i], 'deg'));
        }
        $U00 = $U0;   // store pre-fault voltages for Yaug rebuild after event

        $S = [];
        for ($i = 0; $i < $nbus; $i++) {
            $S[] = new Complex($resLF_P[$i], $resLF_Q[$i]);
        }

        // ── Build augmented Ybus ──────────────────────────────────────────────
        $xd_tr = new Matrix([array_fill(0, $ngen, 0)]);
        for ($i = 0; $i < $ngen; $i++) {
            $xd_tr->set($gen->get($i, 5), 0, $i);   // col 5 = xd' for all models
        }

        $Yaug = $this->makeYaug($U0, $lf, $xd_tr->get());
        $LU   = LinAlg::LUdecomp($Yaug);

        // ── Initialise generator objects ──────────────────────────────────────
        $gg    = [];
        $Pm    = [];
        $Pe    = [];
        $Xgen0 = [];

        foreach ($genmodel as $i => $m) {
            $id       = $genbus[$i] - 1;
            $Pg0      = $S[$id]->re  / $lf->getOption('sbase');
            $Qg0      = $S[$id]->img / $lf->getOption('sbase');
            $data_gen = [[$freq0, $U0[$id], $Pg0, $Qg0], $gen->get($i, [])];

            $gg[$i]   = $this->makeGenObj($m, $data_gen);
            $Xgen0[]  = $gg[$i]->x0();
            $G0       = $gg[$i]->Gy($U0[$id]);
            $Pm[]     = $G0[0];
            $Pe[]     = $G0[0];
        }

        $Xgen0 = new Matrix($Xgen0);

        // ── Initialise exciter objects ────────────────────────────────────────
        $Xexc0_arr = [];
        if ($exc_on) {
            foreach ($excmodel as $i => $m) {
                $id       = $excbus[$i] - 1;
                $data_exc = [[$U0[$id]->abs(), $gg[$id]->get('Efd')], $excMat->get($i, [])];
                $ex[$i]   = $this->makeExcObj($m, $data_exc);
                $Xexc0_arr[] = $ex[$i]->x0();
            }
        }
        $Xexc0 = new Matrix($Xexc0_arr);

        // ── Output arrays ─────────────────────────────────────────────────────
        $Time   = [];
        $Ubus   = [];
        $Angles = [];
        $Speeds = [];
        $Pmec   = [];
        $EfdOut = [];

        // ── Main simulation loop — Implicit Trapezoidal + NR ─────────────────
        $t  = $starttime;
        $ev = 1;

        while ($t <= ($stoptime + $stepsize * 0.5)) {

            // ── Save current step output ──────────────────────────────────────
            $Time[]   = round($t, 10);
            $Ubus[]   = $U0;
            $Angles[] = $Xgen0->transpose()->get(0, []);
            $Speeds[] = $Xgen0->transpose()->get(1, []);
            $Pmec[]   = $Pm;
            $EfdOut[] = $exc_on ? [0, 0, $Xexc0->transpose()->get(0, 0)] : [0, 0, 0];

            // ── Check for events at current time ──────────────────────────────
            if (!is_null($event->get()) && $ev <= $event->getN('rows')) {
                if (abs($t - $event->get($ev - 1, 3)) < (10 * pow(2, -26))) {
                    switch ($event->get($ev - 1, 0)) {
                        case 1:
                            $bus[$event->get($ev - 1, 1) - 1][$event->get($ev - 1, 2)]
                                = $event->get($ev - 1, 4);
                            break;
                        case 2:
                            $branch[$event->get($ev - 1, 1) - 1][$event->get($ev - 1, 2)]
                                = $event->get($ev - 1, 4);
                            break;
                    }
                    $ev++;

                    // Rebuild Ybus and Yaug after topology change
                    $lf->setData('bus', $bus);
                    $lf->setData('branch', $branch);
                    $lf->makeYbus();
                    $Yaug = $this->makeYaug($U00, $lf, $xd_tr->get());
                    $LU   = LinAlg::LUdecomp($Yaug);

                    // Recompute voltages and Pe at t+ (post-event)
                    list($U0, $Pe) = $this->solveNetwork(
                        $gg, $Xgen0, $genmodel, $genbus, $nbus, $LU, $U0
                    );

                    // Save post-event values at same time instant
                    $Time[]   = round($t, 10);
                    $Ubus[]   = $U0;
                    $Angles[] = $Xgen0->transpose()->get(0, []);
                    $Speeds[] = $Xgen0->transpose()->get(1, []);
                    $Pmec[]   = $Pm;
                    $EfdOut[] = $exc_on ? [0, 0, $Xexc0->transpose()->get(0, 0)] : [0, 0, 0];
                }
            }

            // ── Implicit Trapezoidal Integration ──────────────────────────────
            //
            // Step 1: Evaluate f(x_n) at current state
            list($U_n, $Pe_n) = $this->solveNetwork(
                $gg, $Xgen0, $genmodel, $genbus, $nbus, $LU, $U0
            );
            $f0_mat  = $this->evalGenDerivatives(
                $gg, $Xgen0, $genmodel, $genbus,
                $Pm, $Pe_n, $exc_on, $excmodel, $excbus, $ex, $Xexc0
            );
            $x0_flat = $this->matToFlat($Xgen0);
            $f0_flat = $this->matToFlat($f0_mat);

            // Step 2: Predictor — Euler explicit
            //   x_pred = x_n + h * f(x_n)
            $x_pred = [];
            $n_flat = count($x0_flat);
            for ($i = 0; $i < $n_flat; $i++) {
                $x_pred[] = $x0_flat[$i] + $stepsize * $f0_flat[$i];
            }

            // Step 3: Corrector — Newton-Raphson on trapezoidal residual
            //   G(x1) = x1 - x0 - (h/2)*(f0 + f1) = 0
            $x1_flat = $this->newtonCorrector(
                $x_pred, $x0_flat, $f0_flat, $stepsize,
                $gg, $Xgen0, $genmodel, $genbus,
                $Pm, $exc_on, $excmodel, $excbus, $ex,
                $Xexc0, $nbus, $LU, $U_n
            );

            $Xgen1 = $this->flatToMat($x1_flat, $Xgen0);

            // Step 4: Update exciter states (trapezoidal for exciters too)
            if ($exc_on) {
                $Xexc0_flat = $this->matToFlat($Xexc0);
                $fexc0_mat  = $this->evalExcDerivatives($ex, $excmodel, $excbus, $U_n, $Xexc0);
                $fexc0_flat = $this->matToFlat($fexc0_mat);

                // Predictor for exciter
                $xexc_pred = [];
                $ne = count($Xexc0_flat);
                for ($i = 0; $i < $ne; $i++) {
                    $xexc_pred[] = $Xexc0_flat[$i] + $stepsize * $fexc0_flat[$i];
                }
                $Xexc_pred = $this->flatToMat($xexc_pred, $Xexc0);

                // Corrector for exciter (one trapezoidal step, no NR needed — linear)
                list($U1, ) = $this->solveNetwork(
                    $gg, $Xgen1, $genmodel, $genbus, $nbus, $LU, $U_n
                );
                $fexc1_mat  = $this->evalExcDerivatives($ex, $excmodel, $excbus, $U1, $Xexc_pred);
                $fexc1_flat = $this->matToFlat($fexc1_mat);

                $xexc1 = [];
                for ($i = 0; $i < $ne; $i++) {
                    $xexc1[] = $Xexc0_flat[$i] + ($stepsize / 2.0) * ($fexc0_flat[$i] + $fexc1_flat[$i]);
                }
                $Xexc1 = $this->flatToMat($xexc1, $Xexc0);
            } else {
                $Xexc1 = $Xexc0;
            }

            // ── Accept step ───────────────────────────────────────────────────
            list($U0, $Pe) = $this->solveNetwork(
                $gg, $Xgen1, $genmodel, $genbus, $nbus, $LU, $U_n
            );

            $Xgen0 = $Xgen1;
            $Xexc0 = $Xexc1;

            // Advance time
            $t = $t + $stepsize;
        }

        // ── Format output ─────────────────────────────────────────────────────
        $y1 = $y2 = $y3 = $y4 = $y5 = [];

        for ($i = 0; $i < count($Angles); $i++) {
            for ($j = 0; $j < $ngen; $j++) {
                $y1[$i][$j] = $Angles[$i][$j] * 180 / M_PI;          // delta [deg]
                $y2[$i][$j] = $Ubus[$i][$j]->abs();                   // |V| [pu]
                $y3[$i][$j] = $Speeds[$i][$j] / (2 * M_PI * $freq0); // omega [pu]
                $y4[$i][$j] = $Pmec[0][$j];                           // Pm [pu]
                $y5[$i][$j] = $EfdOut[$i][$j];                        // Efd [pu]
            }
        }

        return json_encode([
            'time'  => $Time,
            'delta' => $y1,
            'volt'  => $y2,
            'omega' => $y3,
            'pmec'  => $y4,
            'efd'   => $y5,
        ], JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
