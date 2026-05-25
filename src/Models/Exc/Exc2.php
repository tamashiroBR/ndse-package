<?php
/*
 * NDSE Web Simulator
 * Exc2 — IEEE Type ST1 Static Excitation System
 *
 * State variables:
 *   x[0] = Efd   — field voltage (pu)
 *   x[1] = Vm    — measured terminal voltage (pu, filtered)
 *   x[2] = Vr    — voltage regulator output (pu)
 *
 * Parameters (from exc matrix, columns 2..11):
 *   Ka, Ta  — amplifier gain and time constant
 *   Kf, Tf  — stabilising feedback gain and time constant
 *   Tr      — measurement filter time constant
 *   Vrmin, Vrmax — regulator output limits
 *   Kc      — commutation reactance factor (0 for static exciter)
 *   Kd      — demagnetisation factor
 *   Ki      — integral gain (0 = no integral action)
 *
 * Equations (simplified IEEE ST1A):
 *   dVm/dt  = (|Vt| - Vm) / Tr
 *   dVr/dt  = (Ka*(Vref - Vm + Vs) - Vr) / Ta
 *   Efd     = Vr  (direct, no lag — static exciter)
 *   dEfd/dt = (Vr - Efd) / Te  where Te → 0 (set to small value for numerical stability)
 *
 * For simplicity we use a 2-state model (Vm, Vr) and set Efd = Vr directly.
 */
namespace NDSE\Models\Exc;
use NDSE\Models\AbstractModel;

class Exc2 extends AbstractModel
{
    protected $idv = [
        'input' => ['Ug0', 'Efd0'],
        'param' => ['Ka', 'Ta', 'Kf', 'Tf', 'Tr', 'Vrmin', 'Vrmax', 'Kc', 'Kd', 'Ki'],
        'X'     => ['Efd', 'Vm', 'Vr'],
        'dX'    => ['dEfd', 'dVm', 'dVr'],
        'Y'     => ['Vref']
    ];

    public function __construct($data)
    {
        parent::__construct($data);
    }

    /**
     * Initialise states from operating point.
     * At t=0: Vm = |Vt0|, Vr = Efd0, Vref = |Vt0| + Efd0/Ka
     */
    public function x0()
    {
        $Efd0 = $this->get('Efd0');
        $U0   = $this->get('Ug0');   // |Vt| at t=0
        $Ka   = $this->get('Ka');

        $Vm0  = $U0;
        $Vr0  = $Efd0;
        $Vref = $U0 + $Efd0 / $Ka;

        $this->set('Vref', $Vref);
        $this->set('Efd',  $Efd0);
        $this->set('Vm',   $Vm0);
        $this->set('Vr',   $Vr0);

        return [$Efd0, $Vm0, $Vr0];
    }

    /**
     * State derivatives.
     * $input = [|Vt|, Efd, Vm, Vr]
     */
    public function dFx($input)
    {
        $Vt  = $input[0];   // terminal voltage magnitude (pu)
        $Efd = $input[1];
        $Vm  = $input[2];
        $Vr  = $input[3];

        $Ka   = $this->get('Ka');
        $Ta   = $this->get('Ta');
        $Kf   = $this->get('Kf');
        $Tf   = $this->get('Tf');
        $Tr   = $this->get('Tr');
        $Vrmin = $this->get('Vrmin');
        $Vrmax = $this->get('Vrmax');
        $Vref  = $this->get('Vref');

        // Stabilising feedback signal (derivative feedback)
        $Vs = -($Kf / $Tf) * $Efd;   // simplified: Vs ≈ -Kf/Tf * Efd

        // Measurement filter
        $dVm = ($Tr > 1e-6) ? (($Vt - $Vm) / $Tr) : 0.0;

        // Voltage regulator
        $Verr = $Vref - $Vm + $Vs;
        $dVr  = ($Ka * $Verr - $Vr) / $Ta;

        // Apply limits to Vr for Efd computation
        $VrLim = max($Vrmin, min($Vrmax, $Vr));

        // Static exciter: Efd tracks Vr with small time constant (Te = 0.01 s)
        $Te   = 0.01;
        $dEfd = ($VrLim - $Efd) / $Te;

        return [$dEfd, $dVm, $dVr];
    }
}
