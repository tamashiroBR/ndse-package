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
use NDSE\Math\Sparse;
use NDSE\Math\LinAlg;

/**
 * Defines LoadFlow
 *
 * @author Márcio A. Tamashiro
 */
class LoadFlow extends AbstractTools
{
    /**
     *
     */
    protected $option = [
                           'sbase' => 100,
                        'max_iter' => 10,
                             'tol' => 1e-3,
                            'qlim' => 1
                          //'method' => 'nr'
                      ];
    
    /**
     *
     */
    protected $Ybus = [];

    /**
     * Constructs
     */
    public function __construct($data)
    {
        $this->data = $data;

        $idx = array_keys($this->option);
        foreach ($this->data['optLF'] as $k => $v) {
            $this->option[$idx[$k]] = $v;
        }
    }
	
    /**
     * Defines
     */
    public function getN($name)
    {
        $name = strtolower($name);

        switch($name) {
            case    'bus':
            case 'branch':  return count($this->getData($name));
            case  'trafo':  $trafo = new Matrix($this->getData('branch'));
                            return count($trafo->subMatrix([5,'>',0],[])->get());
            case   'line':  return ($this->getN('branch') - $this->getN('trafo'));
                  default:  break;
        }
    }

    /**
     * Defines
     */
    public function getNtype($bus_type)
    {
        $bus_type = strtolower($bus_type);

        $bus = $this->getData('bus');
        $bus = new Matrix($bus);

        $type = $bus->subMatrix([],[1]);
        $type = $type->transpose()->get();

        $npv = 0;
        $npq = 0;
        foreach($type as $k => $v) {
                if ($v == 1) $npq++;
                if ($v == 2) $npv++;
        }

        switch ($bus_type) {
            case 'pv':	return $npv;
            case 'pq':	return $npq;
              default:	break;
            /* case 'pv':	$bus_type = $bus->subMatrix([1,'==',2],[])->get();
                        // break;
            // case 'pq':	$bus_type = $bus->subMatrix([1,'==',1],[])->get();
                        // break;
              // default:	break;*/
        }
        //return count($bus_type);
    }

    /**
     * Defines
     */
    public function getYbus()
    {
        return $this->Ybus;
    }

    /**
     * Defines function to build admittance matrices
     */
    public function makeYbus()
    {
        $bus = new Matrix($this->getData('bus'));
        $branch =  new Matrix($this->getData('branch'));

//        $bus = new Matrix($bus);
//        $branch =  new Matrix($branch);

        $nbus = $this->getN('bus');
        $nbranch = $this->getN('branch');

        $bus = $bus->transpose();

        $Ysh = Matrix::zeros(1,$nbus)->get();
        $Y = new Sparse($nbus,$nbus);

        $rsh = $bus->get(6,[]); 
        $xsh = $bus->get(7,[]);

        // Only process branches if the network has any
        if ($nbranch > 0) {
            $branch = $branch->transpose();
            $Ys = Matrix::zeros(1,$nbranch)->get();
            $Bc = Matrix::zeros(1,$nbranch)->get();
            $from = $branch->get(0,[]);
            $to = $branch->get(1,[]);
            $rb = $branch->get(2,[]);
            $xb = $branch->get(3,[]);
            $bb = $branch->get(4,[]);
            $tap = $branch->get(5,[]);
            $shift = $branch->get(6,[]);
            $status = $branch->get(7,[]);
            for ($i = 0; $i < $nbranch; $i++) {
                $from[$i] = $from[$i]-1;
                $to[$i] = $to[$i]-1;
            }
        }

        for ($i = 0; $nbranch > 0 && $i < $nbranch; $i++)
        {
            // Skip branches that are out of service
            if (!$status[$i]) {
                continue;
            }

            $z = new Complex($rb[$i],$xb[$i]);
            $Ys[$i] = $z->inv();
            $Bc[$i] = new Complex(0,$bb[$i]);

            if ($tap[$i] !=  0 ) {
                $tap[$i] = new Complex($tap[$i], new Angle($shift[$i],'deg'));
            } else {
                $tap[$i] = 1;
            }

            if ($tap[$i] instanceof Complex) {
                $tap_conj = $tap[$i]->conj();
                $tap_2 = pow($tap[$i]->abs(),2);
            } elseif (is_numeric($tap[$i])) {
                $tap_conj = $tap[$i];
                $tap_2 = pow($tap[$i],2);
            }

            if ($Bc[$i] instanceof Complex) {
                $Bc_half = $Bc[$i]->div(2);
            } elseif (is_numeric($Bc[$i])) {
                $Bc_half = $Bc[$i]/2;
            }

            $Ysneg = $Ys[$i]->neg();
            $value = $Ysneg->div($tap_conj);
            if (!is_null($Y->get($from[$i],$to[$i]))) {
                $value = $value->add($Y->get($from[$i],$to[$i]));
            }
            $Y->set($value,$from[$i],$to[$i]);

            $Ysneg = $Ys[$i]->neg();
            $value = $Ysneg->div($tap[$i]);
            if (!is_null($Y->get($to[$i],$from[$i]))) {
                $value = $value->add($Y->get($to[$i],$from[$i]));
            }
            $Y->set($value,$to[$i],$from[$i]);

            $Yss = $Ys[$i]->div($tap_2);
            $value = $Yss->add($Bc_half);
            if (!is_null($Y->get($from[$i],$from[$i]))) {
                $value = $value->add($Y->get($from[$i],$from[$i]));
            }
            $Y->set($value,$from[$i],$from[$i]);

            $value = $Ys[$i]->add($Bc_half);
            if (!is_null($Y->get($to[$i],$to[$i]))) {
                $value = $value->add($Y->get($to[$i],$to[$i]));
            }
            $Y->set($value,$to[$i],$to[$i]);
        }

        //vector of shunt admittances
        for ($i = 0; $i < $nbus; $i++) {
            $temp = new Complex($rsh[$i],$xsh[$i]);
            if ($rsh[$i] == 0 && $xsh[$i] == 0) {
                $value = $temp;
            } else {
                $Ysh[$i] = $temp->inv();
                $value = $Ysh[$i];
            }
            if (!is_null($Y->get($i,$i))) {
                $value = $value->add($Y->get($i,$i));
            }
            $Y->set($value,$i,$i);
		}

        $this->Ybus = $Y;
    }

    /**
     * Defines
     */
    public function makeJ($input)
    {
        $nt = $input[0];
        $npvq = $input[1];
        $V = $input[2];
        $theta = $input[3];
        $PCALC = $input[4];
        $QCALC = $input[5];
        $Ybus = $input[6];
        $IPESP = $input[7];
        $LQESP = $input[8];
        $IQESP = $input[9];

        $JACOB = new Sparse($nt,$nt);

        // H Matrix
        for ($I = 0; $I < $npvq; $I++)
        {
            $k = $IPESP[$I];
            for ($J = 0; $J < $npvq; $J++)
            {
                $l = $IPESP[$J];
                if ($k == $l) {
                    $value = -$Ybus->get($k,$k)->img*$V[$k]*$V[$k]-$QCALC[$k];
                } else {
                    if (!is_null($Ybus->get($k,$l))) {
                        $DTETA = $theta[$k]-$theta[$l];
                        $value = $V[$k]*$V[$l]*($Ybus->get($k,$l)->re*sin($DTETA)-$Ybus->get($k,$l)->img*cos($DTETA));
                    } else $value = 0;
                }
                if ($value != 0) {
                    $JACOB->set($value,$I,$J);
                }
            }
        }

        // L Matrix
        $N1 = 0;
        /*for ($I = $npvq; $I < $nt; $I++) {
            $L = $LQESP[$N1];
            $N1 = $N1+1;
            $N2 = 0;
            for ($J = $npvq; $J < $nt; $J++) {
                $M = $LQESP[$N2];
                $N2 = $N2+1;
                if ($IPESP[$M] == $IPESP[$L]) {
                        $k=$IPESP[$L];
                        $value = -$Ybus->get($k,$k)->img*$V[$k]*$V[$k]+$QCALC[$k];
                }
                else {
                        $value = $JACOB->get($L,$M);
                }
                if ($value != 0) {
                    $JACOB->set($value,$I,$J);
                }
            }
        }*/
        for ($I = $npvq; $I < $nt; $I++) {
            $L = $LQESP[$N1];
            $k=$IPESP[$L];
            $N1 = $N1+1;
            $N2 = 0;
            for ($J = $npvq; $J < $nt; $J++) {
                $M = $LQESP[$N2];
                $l = $IPESP[$M];
                $N2 = $N2+1;
                if ($IPESP[$M] == $IPESP[$L]) {
                        $value = -$Ybus->get($k,$k)->img*$V[$k]*$V[$k]+$QCALC[$k];
                }
                else {
                    if (!is_null($Ybus->get($k,$l))) {
                        $DTETA = $theta[$k]-$theta[$l];
                        $value = $V[$k]*$V[$l]*($Ybus->get($k,$l)->re*sin($DTETA)-$Ybus->get($k,$l)->img*cos($DTETA));
                    } else $value = 0;
                }
                if ($value != 0) {
                    $JACOB->set($value,$I,$J);
                }
            }
        }

        // M Matrix
        $N = 0;
        for ($I = $npvq; $I < $nt; $I++) {
            $k = $IQESP[$N];
            $N = $N+1;
            for ($J = 0; $J < $npvq; $J++) {
                $L = $IPESP[$J];
                if ($k == $L) {
                    $value = -$Ybus->get($k,$k)->re*$V[$k]*$V[$k]+$PCALC[$k];
                } else {
                    if (!is_null($Ybus->get($k,$L))) {
                        $DTETA = $theta[$k]-$theta[$L];
                        $value = -$V[$k]*$V[$L]*($Ybus->get($k,$L)->re*cos($DTETA)+$Ybus->get($k,$L)->img*sin($DTETA));
                    } else $value = 0;
                }
                if ($value != 0) {
                    $JACOB->set($value,$I,$J);
                }
            }
        }

        // N Matrix
        for ($I = 0; $I < $npvq; $I++) {
            $k = $IPESP[$I];
            $N = 0;
            for ($J = $npvq; $J < $nt; $J++) {
                $L = $IQESP[$N];
                $N = $N+1;
                if ($k == $L) {
                    $value = $Ybus->get($k,$k)->re*$V[$k]*$V[$k]+$PCALC[$k];
                } else {
                    if (!is_null($Ybus->get($k,$L))) {
                        $DTETA = $theta[$k]-$theta[$L];
                        $value = $V[$k]*$V[$L]*($Ybus->get($k,$L)->re*cos($DTETA)+$Ybus->get($k,$L)->img*sin($DTETA));
                    } else $value = 0;
                }
                if ($value != 0) {
                    $JACOB->set($value,$I,$J);
                }
            }
        }

        return $JACOB;
    }
 
	/**
     * Defines
     */
    public function run()
    {
        $Ybus = $this->Ybus;

        $Sbase = $this->getOption('sbase');
        $maxIter = $this->getOption('max_iter');
        $tol = $this->getOption('tol');
        $qlim = $this->getOption('qlim');

        $bus = new Matrix($this->getData('bus'));

        $nbus = $this->getN('bus');
	$nbranch = $this->getN('branch');

        $type = $bus->subMatrix([],[1]);
        $type = $type->transpose()->get();

        $Pg = $bus->subMatrix([],[2]);
        $Pl = $bus->subMatrix([],[4]);

        $Psp = $Pg->add($Pl->multiply(-1));
        $Psp = $Psp->multiply(1/$Sbase);

        $Psp = $Psp->transpose()->get();
        $P = $Psp;

        $Qg = $bus->subMatrix([],[3]);
        $Ql = $bus->subMatrix([],[5]);

        $Qsp =  $Qg->add($Ql->multiply(-1));
        $Qsp = $Qsp->multiply(1/$Sbase);

        $Qsp = $Qsp->transpose()->get();
        $Q = $Qsp;

        $Qgmax = $bus->subMatrix([],[10]);
        $Qgmax = $Qgmax->multiply(1/$Sbase);
        $Qgmax = $Qgmax->transpose()->get();

        $Qgmin = $bus->subMatrix([],[11]);
        $Qgmin = $Qgmin->multiply(1/$Sbase);
        $Qgmin = $Qgmin->transpose()->get();

        $Vsp = $bus->subMatrix([],[8]);
        $Vsp = $Vsp->transpose()->get();

        $V = $Vsp;

        $theta = $bus->subMatrix([],[9])->multiply(M_PI/180);
        $theta = $theta->transpose()->get();

        $Pl = $Pl->multiply(1/$Sbase)->transpose()->get();
        $Ql = $Ql->multiply(1/$Sbase)->transpose()->get();

	unset($bus);

        $PCALC = array_fill(0, $nbus, 0.0);
        $QCALC = array_fill(0, $nbus, 0.0);
        $IPESP = [];
        $LQESP = [];
        $IQESP = [];
        $PDISC = [];
        $QDISC = [];
        $DPOT  = [];
        $PDI   = 1.0;
        $QDI   = 1.0;
        $MPVPQ = Matrix::zeros(1,$nbus)->get();

        $iter = 0;
        // Iteration begins
        do {
            for ($i = 0; $i < $nbus; $i++) {
                if ($type[$i] != 3) {
                    $PCALC[$i] = 0;
                    $QCALC[$i] = 0;
                }
            }

            for ($i = 0; $i < $nbus; $i++) {
                for ($k = 0; $k < $nbus; $k++) {
                    if ($type[$i] == 3) {
                        break;
                    } elseif (!is_null($Ybus->get($i,$k))) {
                            $DTETA = $theta[$i]-$theta[$k];
                            $PCALC[$i] = $PCALC[$i]+($Ybus->get($i,$k)->re*cos($DTETA)+$Ybus->get($i,$k)->img*sin($DTETA))*$V[$k];
                            $QCALC[$i] = $QCALC[$i]+($Ybus->get($i,$k)->re*sin($DTETA)-$Ybus->get($i,$k)->img*cos($DTETA))*$V[$k];
                    }
                }
                if ($type[$i] != 3) {
                    $PCALC[$i] = $V[$i]*$PCALC[$i];
                    $QCALC[$i] = $V[$i]*$QCALC[$i];

                    if ($type[$i] == 2) {
                        $Q[$i] = $QCALC[$i];
                    }
                }
            }

            if ($qlim)
            {
                // Checking Qlimit violation
                for ($i = 0; $i < $nbus; $i++)
                {
                    if ($type[$i] == 2) {
                        if (($QCALC[$i]+$Ql[$i]) < $Qgmin[$i]) {
                            $MPVPQ[$i] = 1;
                            $type[$i] = 1;
                            $Qsp[$i] = $Qgmin[$i]-$Ql[$i];
                            $Q[$i] = $Qsp[$i];
                        }
                        elseif (($QCALC[$i]+$Ql[$i]) > $Qgmax[$i]) {
                            $MPVPQ[$i] = 2;
                            $type[$i] = 1;
                            $Qsp[$i] = $Qgmax[$i]-$Ql[$i];
                            $Q[$i] = $Qsp[$i];
                        }
                    }
                }

                // ***  VERIFICA SE BARRAS QUE VIOLARAM REATIVOS PODEM SER PV DE NOVO
                for ($i = 0; $i < $nbus; $i++)
                {
                    if (($MPVPQ[$i] == 1 && $V[$i] < $Vsp[$i]) || ($MPVPQ[$i] == 2 && $V[$i] > $Vsp[$i])) {
                        $MPVPQ[$i] = 0;
                        $type[$i] = 2;
                        $V[$i] = $Vsp[$i];
                    }
                }
            }
            
            // *** CALCULO DO NUMERO DE BARRAS PV (NPV) E NUMERO DE BARRAS PQ (NPQ)
            $npv = 0;
            $npq = 0;
            for ($i = 0; $i < $nbus; $i++)
            {
                if ($type[$i] == 1) {
                    $npq++;
                } elseif ($type[$i] == 2) {
                    $npv++;
                }
            }

            $npvq = $npv+$npq;
            $nt = $npvq+$npq;

            // *** VETORES DE ORDENACAO DOS ELEMENTOS NA MATRIZ JACOBIANA
            $KP = 0;
            $KQ = 0;
            for ($i = 0; $i < $nbus; $i++) {
                if ($type[$i] != 3) {
                    if ($type[$i] != 2) {
                        $IQESP[$KQ] = $i;
                        $LQESP[$KQ] = $KP;
                        $KQ++;
                    }
                    $IPESP[$KP] = $i;
                    $KP++;
                }
            }

            // *** CALCULO DAS DISCORDANCIAS ENTRE POT. ESPECIFICADAS E CALCULADAS
            $KP = 0;
            $KQ = 0;
            for ($i = 0; $i < $nbus; $i++) {
//                if ($i != $BusIdref) {
                if ($type[$i] != 3) {
                    if ($type[$i] != 2) {
                        $QDISC[$KQ] = $Qsp[$i]-$QCALC[$i];
                        $KQ++;
                    }
                    $PDISC[$KP] = $Psp[$i]-$PCALC[$i];
                    $KP++;
                }
            }

            // *** FORMACAO DO VETOR DE DISCORDANCIAS DE POTENCIAS ATIVA E REATIVA
            for ($i = 0; $i < $npvq; $i++) {
                $DPOT[$i] = $PDISC[$i];
            }

            $k = 0;
            for ($i = $npvq; $i < $nt; $i++) {
                $DPOT[$i] = $QDISC[$k];
                $k++;
            }

            // If only slack bus (nt=0), no system to solve
            if ($nt > 0) {
                $input = [$nt,$npvq,$V,$theta,$PCALC,$QCALC,$Ybus,$IPESP,$LQESP,$IQESP];

                $JACOB = $this->makeJ($input);

                // Calculation of correction vectors
                $LU = LinAlg::LUdecomp($JACOB);
                $DTDV = LinAlg::LUsolver($LU,$DPOT); // Solve liner system here //

                unset($JACOB);

                // Updation of bus angles and bus voltages at each bus
                $N = 0;
                for ($i = 0; $i < $nt; $i++) {
                    if ($i < $npvq) {
                        $k = $IPESP[$i];
                        $theta[$k] = $theta[$k] + $DTDV[$i];
                    } else {
                        $L = $IQESP[$N];
                        $V[$L] = $V[$L] + $DTDV[$i]*$V[$L];
                        $N++;
                    }
                }
            }

            // Bus power mismatches
            if ($nt == 0) {
                // Only slack bus: trivially converged
                $PDI = 0.0;
                $QDI = 0.0;
            } else {
                for ($i = 0; $i < $npvq-1; $i++) {
                    $PDI = abs($PDISC[$i]);
                    if ($PDI > $tol) {
                        break;
                    }
                }

                for ($i = 0; $i < $npq-1; $i++) {
                    $QDI = abs($QDISC[$i]);
                    if ($QDI > $tol) {
                        break;
                    }
                }
            }

            $iter = $iter + 1;
			
        // Check iterator maximum
        } while (($iter < $maxIter) && (($PDI > $tol) || ($QDI > $tol)));

        if (($iter > $maxIter) || ($PDI > $tol) || ($QDI > $tol)) {
            //echo "NAO CONVERGIU.";
            return [];
        } else {
            $iter--;
// echo "<h3>CONVERGIU</h3><br>";
// echo "<h4>RESULTADOS DO FLUXO DE CARGA (" . $iter . " ITERACOES)</h4>";

        // *** CALCULOS DOS FLUXOS DE POTENCIA NAS LINHAS E TAMBEM AS PERDAS
        for ($i = 0; $i < $nbus; $i++)
        {
           $Vreal = $V[$i]*cos($theta[$i]);
           $Vimag = $V[$i]*sin($theta[$i]);
           $E[$i] = new Complex($Vreal,$Vimag);
        }

        $Ploss = 0;
        $Qloss = 0;
        $LOSS = [];
        $IKM = [];
        $IMK = [];
        $SKM = [];
        $SMK = [];
        $branch = [];

        if ($nbranch > 0) {
        $branchMatrix =  new Matrix($this->getData('branch'));
        $branchMatrix = $branchMatrix->transpose();

        $from = $branchMatrix->get(0,[]);
        $to = $branchMatrix->get(1,[]);
        $rb = $branchMatrix->get(2,[]);
        $xb = $branchMatrix->get(3,[]);
        $bb = $branchMatrix->get(4,[]);
        $tap = $branchMatrix->get(5,[]);
        $shift = $branchMatrix->get(6,[]);
        $status = $branchMatrix->get(7,[]);
        unset($branchMatrix);
        } // end if nbranch > 0

        for ($I = 0; $nbranch > 0 && $I < $nbranch; $I++)
        {
            $de = $from[$I]-1;
            $para = $to[$I]-1;

            if ($status[$I]) {
                $z = new Complex($rb[$I],$xb[$I]);
                $Ys = $z->inv();
                $Bc = new Complex(0,$bb[$I]);
                if ($Bc instanceof Complex) {
                    $Bc_half = $Bc->div(2);
                } elseif (is_numeric($Bc)) {
                    $Bc_half = $Bc/2;
                }
            }

            if ($tap[$I] == 0) {
                    $temp = $E[$de]->add($E[$para]->neg());
                    $temp = $temp->multiply($Ys);
                    $IKM[$I] = $temp->add($E[$de]->multiply($Bc_half));
                    $temp = $E[$para]->add($E[$de]->neg());
                    $temp = $temp->multiply($Ys);
                    $IMK[$I] = $temp->add($E[$para]->multiply($Bc_half));
            } else {
                    $AR = $tap[$I] * cos($shift[$I]*M_PI/180);
                    $AI = $tap[$I] * sin($shift[$I]*M_PI/180);
                    $A = new Complex($AR,$AI);
                    $IMK[$I] = $E[$para]->add($A->neg()->multiply($E[$de]));
                    $IMK[$I] = $IMK[$I]->multiply($Ys);
                    $IKM[$I] = $IMK[$I]->multiply($A->conj()->neg());
            }
            $SKM[$I] = $E[$de]->multiply($IKM[$I]->conj());
            $SKM[$I] = $SKM[$I]->multiply($Sbase);
            $SMK[$I] = $E[$para]->multiply($IMK[$I]->conj());   
            $SMK[$I] = $SMK[$I]->multiply($Sbase);
           

            $LOSS[$I] = $SKM[$I]->add($SMK[$I]);
            
            $branch[] = [$from[$I],$to[$I],$SKM[$I]->re,$SKM[$I]->img,$SMK[$I]->re,$SMK[$I]->img,$LOSS[$I]->re,$LOSS[$I]->img];
 
            $Ploss = $Ploss+$LOSS[$I]->re;
            $Qloss = $Qloss+$LOSS[$I]->img;
        }

            // *** CALCULO DA POTENCIA NA BARRA DE REFERENCIA OU OSCILANTE - SBREF
//            $i = $BusIdref;
            for ($i = 0; $i < $nbus; $i++)
            {
                if ($type[$i] == 3)
                {
                    $idbusref = $i;
                    break;
                }
            }

            $SBREF = new Complex(0,0);

            for ($j = 0; $j < $nbus; $j++) {
                $SBREFAUX = $E[$j]->multiply($Ybus->get($idbusref,$j));
                $SBREFAUX = $SBREFAUX->conj();
                $SBREF = $SBREFAUX->add($SBREF);
            }

            $SBREF = $SBREF->multiply($E[$idbusref]);
            $P[$idbusref] = $SBREF->re;
            $Q[$idbusref] = $SBREF->img;

            // *** RESULTADOS DE BARRA
            for ($i = 0 ; $i < $nbus; $i++) {
                $Angle[$i] = $theta[$i]*180/M_PI;
				$P[$i] = ($P[$i]+$Pl[$i]) * $Sbase;
                $Q[$i] = ($Q[$i]+$Ql[$i]) * $Sbase;
				$Pl[$i] = $Pl[$i] * $Sbase;
                $Ql[$i] = $Ql[$i] * $Sbase;
				$Qgmax[$i] = $Qgmax[$i] * $Sbase;
				$Qgmin[$i] = $Qgmin[$i] * $Sbase;
				if ($P[$i] < 0) {
					$Pl[$i] = $Pl[$i]-$P[$i];
					$P[$i] = 0;
				}
            }

            $bus = [];
            for ($i = 0; $i < $nbus; $i++) {
                $bus[] = [$i+1,$V[$i],$Angle[$i],$P[$i],$Q[$i],$Pl[$i],$Ql[$i],$Qgmax[$i],$Qgmin[$i]];
            }

            $json = json_encode(['iteration'=>$iter,'bus'=>$bus,'branch'=>$branch,'loss'=>[$Ploss,$Qloss]]);

            return $json;
        }
    }
}