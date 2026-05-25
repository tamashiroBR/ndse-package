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

namespace NDSE\Math;

use NDSE\Math\Matrix;
use NDSE\Math\Complex;

/*
 * Solver implementation
 *
 * @author Márcio A. Tamashiro
 */
abstract class LinAlg
{
    public static function gaussianElimination($matrix, $vector)
    {		
        //$row = $matrix->rows;
        //$col = $matrix->cols;

        $row = $matrix->getN('rows');
        $col = $matrix->getN('cols');
        
        for ($i = 0; $i < $row; $i++) {
            // find pivo element
            if ($matrix->get($i, $i) instanceof Complex) {
                $max = $matrix->get($i, $i)->abs();
            } else {
                $max = abs($matrix->get($i, $i));
            }
            $maxIndex = $i;
            for ($j = $i+1; $j < $row; $j++) {
                if ($matrix->get($i, $i) instanceof Complex) {
                    $abs = $matrix->get($j, $i)->abs();			
                } else {
                    $abs = abs($matrix->get($j, $i));
                }
                if ($abs > $max) {
                    $max = $abs;
                    $maxIndex = $j;
                }
            }

            // pivoting
           if ($maxIndex !== $i) {
                // change maxIndex row with i row in $matrix
                $temp = $matrix->get($i,[])->get();
                $matrix->set($matrix->get($maxIndex,[])->get(), $i, []);
                $matrix->set($temp,$maxIndex, []);

                // change maxIndex row with i row in $vector
                $temp = $vector->get($i,[])->get();
                $vector->set($vector->get($maxIndex,[])->get(), $i, []);
                $vector->set($temp, $maxIndex, []);
            }
 
            $mi = $matrix->get($i,[]);
            $vi = $vector->get($i,[]);

            for ($j = $i+1; $j < $row; $j++) {
                $mj = $matrix->get($j,[]);
                $vj = $vector->get($j,[]);

                if ($matrix->get($j, $i) instanceof Complex) {
                    $m = $matrix->get($j, $i)->neg();
                    $fac = $m->div($matrix->get($i, $i));
                } elseif (is_numeric($matrix->get($j, $i)) && ($matrix->get($i, $i) instanceof Complex)) {
                    $m = $matrix->get($i, $i)->inv();
                    $fac = $m->multiply(-$matrix->get($j, $i));
                } elseif (is_numeric($matrix->get($j, $i)) && is_numeric($matrix->get($i, $i))) {
                    $fac = -$matrix->get($j, $i)/$matrix->get($i, $i);
                }		
									
                $mjj = $mj->add($mi->multiply($fac));
                $matrix->set($mjj->get(), $j, []);

                $vjj = $vj->add($vi->multiply($fac));
                $vector->set($vjj->get(), $j, []);
            }
        }

        $xVector = Matrix::zeros($row,1);
		
        for ($i = $row-1; $i >= 0; $i--) {
            $sum = 0;
	
            for ($j = $i+1; $j < $row; $j++) {
                if ($matrix->get($i, $j) instanceof Complex) {
                    $z = $matrix->get($i, $j)->multiply($xVector->get($j,0));
                    $sum = $z->add($sum);
                } elseif ($xVector->get($j,0) instanceof Complex) {
                    $z = $xVector->get($j,0)->multiply($matrix->get($i, $j));
                    $sum = $z->add($sum);					
                } elseif (is_numeric($matrix->get($i, $j)) && is_numeric($xVector->get($j,0))) {
                    $sum += ($matrix->get($i, $j))*($xVector->get($j,0));
                }
            }
	
            $v_out = [];
            if ($vector->get($i,0) instanceof Complex) {
                $out = $vector->get($i,0)->sub($sum);
                $v_out[] = $out->div($matrix->get($i, $i));				
            } elseif (is_numeric($vector->get($i,0)) && is_numeric($matrix->get($i, $i))) {
                $v_out[] = ($vector->get($i,0)-$sum)/($matrix->get($i, $i));
            }

            $xVector->set($v_out, $i, []);
        }

        return $xVector;
    }

    //public static function LUsolver($sparse, $vector)
    public static function LUdecomp($sparse)
    {
        $m = count($sparse->i)-1;

        for ($i = 0; $i < $m+1; $i++) {
                $Li[] = 0;
        }
        $Lj = [];
        $Lv = [];
        $L =[$Li,$Lj,$Lv];

        for ($i = 0; $i < $m+1; $i++) {
            $Ui[] = 0;
        }
        $Uj = [];
        $Uv = [];
        $U =[$Ui,$Uj,$Uv];

        for ($i = 0; $i < $m; $i++) {
            $P[] = $i;
            $Pinv[] = $i;
        }

        for ($i = 0; $i < $m; $i++) {
            $y[] = 0;
            $xj[] = 0;
        }

        $fcn1 = function($J,$Ai,$Aj) use (&$y,&$xj,&$Pinv) {
            $m = 0;
            $n = count($xj);

            $k = [];
            $k1 = [];
            $j = [];

            if ($y[$J] instanceof Complex) {
                if ($y[$J]->abs() != 0) {
                    return;
                }
                $y[$J] = new Complex(1,0);				
            } else {
                if ($y[$J] != 0) {
                    return;
                }
                $y[$J] = 1;				
            }

            $j[0] = $J;
            $k[0] = $km = $Ai[$Pinv[$J]];
            $k1[0] = $k11 = $Ai[$Pinv[$J]+1];

            while (1) {
                if(is_nan($km)) {
                    //throw new Error("Ow!");
                    echo "Error!";
                }
                if ($km >= $k11) {
                    $xj[$n] = $Pinv[$j[$m]];
                    if ($m == 0) {
                        return;
                    }
                    ++$n;
                    --$m;
                    $km = $k[$m];
                    $k11 = $k1[$m];
                } else {
                    $foo = $Aj[$km];
                    if ($y[$foo] instanceof Complex) {
                        if ($y[$foo]->abs() == 0) {
                            $y[$foo] = new Complex(1,0);
                            $k[$m] = $km;
                            ++$m;
                            $j[$m] = $foo;
                            $foo = $Pinv[$foo];
                            $km = $Ai[$foo];
                            $k1[$m] = $k11 = $Ai[$foo+1];
                        } else {
                            ++$km;
                        }
                    } else {
                        if ($y[$foo] == 0) {
                            $y[$foo] = 1;
                            $k[$m] = $km;
                            ++$m;
                            $j[$m] = $foo;
                            $foo = $Pinv[$foo];
                            $km = $Ai[$foo];
                            $k1[$m] = $k11 = $Ai[$foo+1];
                        } else {
                            ++$km;
                        }
                    }
                }
            }
        };
			
        $fcn2 =  function($A,$B,$I) use (&$y,&$xj,&$P,$fcn1) {
            $Ai = $A[0];
            $Aj = $A[1];
            $Av = $A[2];

            $m = count($Ai)-1;
            $n = 0;

            $Bi = $B->i;
            $Bj = $B->j;
            $Bv = $B->v;

            $i0 = $Bi[$I];
            $i1 = $Bi[$I+1];			

            //$xj.length = 0;
            if (count($xj) > 0) {
                $xj = [];
            }

            for ($i = $i0; $i < $i1; ++$i) {
                //echo "Bj[$i] ".$Bj[$i]."<br>";
                $fcn1($Bj[$i],$Ai,$Aj);
            }

            for ($i = count($xj)-1; $i != -1; --$i) {
                $j = $xj[$i]; 
                $y[$P[$j]] = 0; 
            }
            for ($i = $i0; $i != $i1; ++$i) {
                $j = $Bj[$i];
                $y[$j] = $Bv[$i];
            }			
//echo "y ";var_dump($y);echo "<br>";
//echo "xj ";var_dump($xj);echo "<br>";

            for ($i = count($xj)-1; $i != -1; --$i) {
                $j = $xj[$i];
                $l = $P[$j];
                $j0 = $Ai[$j];
                $j1 = $Ai[$j+1];
                for ($k = $j0; $k < $j1; ++$k) { 
                    if ($Aj[$k] == $l) {
                        if ($y[$l] instanceof Complex) {
                            $y[$l] = $y[$l]->div($Av[$k]);
                        } else {
                            $y[$l] /= $Av[$k]; 
                        }
                        break;
                    } 
                }
                $a = $y[$l];
                for ($k = $j0; $k < $j1; ++$k) {
                    if ($Av[$k] instanceof Complex) {
                        $yAj = $Av[$k]->multiply($a);
                        $yAj = $yAj->sub($y[$Aj[$k]]);					
                        $y[$Aj[$k]] = $yAj->neg(); 
                    } elseif ($a instanceof Complex) {
                        $yAj = $a->multiply($Av[$k]);
                        $yAj = $yAj->sub($y[$Aj[$k]]);
                        $y[$Aj[$k]] = $yAj->neg();
                    } else {
                        $y[$Aj[$k]] -= $a*$Av[$k];
                    }
                }
                $y[$l] = $a;
            }
        };

        for ($i = 0; $i < $m; ++$i) {
            $fcn2($L,$sparse,$i);
            $a = -1;
            $e = -1;

            for ($j = count($xj)-1; $j != -1; --$j) {
                $k = $xj[$j];
                if ($k <= $i) {
                    continue;
                }
                if ($y[$P[$k]] instanceof Complex) {
                    $c = $y[$P[$k]]->abs();
                } else {
                    $c = abs($y[$P[$k]]);					
                }
                if ($c > $a) {
                    $e = $k;
                    $a = $c; 
                }
            }

            if ($y[$P[$i]] instanceof Complex) {
                $y_abs = $y[$P[$i]]->abs();
            } else {
                $y_abs = abs($y[$P[$i]]);
            }	

            if ($y_abs < $a) {
                $j = $P[$i];
                $a = $P[$e];
                $P[$i] = $a;
                $Pinv[$a] = $i;
                $P[$e] = $j;
                $Pinv[$j] = $e;
            }			

            $a = $Li[$i];
            $e = $Ui[$i];
            $d = $y[$P[$i]];
            $Lj[$a] = $P[$i];
            $Lv[$a] = 1;
            ++$a;
            for ($j = count($xj)-1; $j != -1; --$j) {
                $k = $xj[$j];
                $c = $y[$P[$k]];	
                $xj[$j] = 0;				
                $y[$P[$k]] = 0;

                if ($k <= $i) {
                    $Uj[$e] = $k;
                    $Uv[$e] = $c;
                    ++$e;
                } else { 
                    $Lj[$a] = $P[$k];
                    if ($c instanceof Complex) {
                        $Lv[$a] = $c->div($d);
                    } elseif ($d instanceof Complex) {
                        $Lv[$a] = $d->inv()->multiply($c);
                    } else {
                        $Lv[$a] = $c/$d;
                    }
                    ++$a; 
                }
            }
            $Li[$i+1] = $a;
            $Ui[$i+1] = $e;

            $L =[$Li,$Lj,$Lv];
        }

        for ($j = count($Lj)-1; $j != -1; --$j) {
            $Lj[$j] = $Pinv[$Lj[$j]];
        }

        $L =[$Li,$Lj,$Lv];
        $U =[$Ui,$Uj,$Uv];

        return [$L,$U,$Pinv];
    }
/*
echo "Li<br>";
var_dump($Li); echo "<br>";
echo "Lj<br>";
var_dump($Lj); echo "<br>";
echo "Lv<br>";
var_dump($Lv); echo "<br>";
echo "Ui<br>";
var_dump($Ui); echo "<br>";
echo "Uj<br>";
var_dump($Uj); echo "<br>";
echo "Uv<br>";
var_dump($Uv); echo "<br>";
*/

    public static function LUsolver($LU, $vector)
    {
        $L = $LU[0];
        $U = $LU[1];
        $Pinv = $LU[2];

        $fcn4 = function($A,$b,&$x,&$bj,&$xj) {
            $Ai = $A[0];
            $Aj = $A[1];
            $Av = $A[2];

            //m = count($Ai)-1;
            $n = 0;

            $fcn3 = function($j) use (&$x,&$xj,&$n,$Ai,$Aj,&$fcn3) {
                if ($x[$j] instanceof Complex) {
                    if ($x[$j]->abs() != 0) {
                        return;
                    }
                    $x[$j] = new Complex(1,0);
                } else {
                    if ($x[$j] != 0) {
                        return;
                    }
                    $x[$j] = 1;
                }

                for ($k = $Ai[$j]; $k < $Ai[$j+1]; ++$k) {
                    //echo $Aj[$k]." ";
                    $fcn3($Aj[$k]);
                }

                $xj[$n] = $j;
                ++$n;
            };
			
            for ($i = count($bj)-1 ; $i != -1; --$i) {
                $fcn3($bj[$i]);
            }

            if (count($xj) > $n) {
                $xj = array_slice($xj, 0, $n);
            }

            for ($i = count($xj)-1; $i != -1; --$i) {
                $x[$xj[$i]] = 0;
            }

            for ($i = count($bj)-1; $i != -1; --$i) {
                $j = $bj[$i];
                $x[$j] = $b[$j];
            }

            for ($i = count($xj)-1; $i != -1; --$i) {
                $j = $xj[$i];
                $j0 = $Ai[$j];
                $j1 = max($Ai[$j+1],$j0);

                for ($k = $j0; $k != $j1; ++$k) {
                    if ($Aj[$k] == $j) {
                        if ($Av[$k] instanceof Complex) {
                                $x[$j] = $Av[$k]->inv()->multiply($x[$j]);
                        } elseif (is_numeric($x[$j]) && is_numeric($Av[$k])) {
                                $x[$j] /= $Av[$k];
                        }
                        break;
                    }
                }

                $a = $x[$j];
                for ($k = $j0; $k != $j1; ++$k) {
                    $l = $Aj[$k];
                    if ($l != $j) {
                        if ($Av[$k] instanceof Complex) {
                            $xl = $Av[$k]->multiply($a);
                            $xl = $xl->sub($x[$l]);					
                            $x[$l] = $xl->neg(); 
                        } elseif (is_numeric($x[$l]) && is_numeric($Av[$k])) {
                            $x[$l] -= $a*$Av[$k];
                        }
                    }
                }
            }
        };

        //$Bi = [0,count($vector)];
        for ($i = 0; $i < count($vector); $i++) {
            $Bj[] = $i;
        }
        $Bv = $vector;

        $n = count($L[0])-1;
        //$n = count($Li)-1;
        $m = count($vector);

        for ($i = 0; $i < $n; $i++) {
            $x[] = 0;
            $b[] = 0;
        }

        $xj = [];
        $bj = [];

        $k = 0;

        for ($i = 0; $i < $m; ++$i) { 
            $J = $Pinv[$Bj[$i]];
            $bj[$k] = $J;
            $b[$J] = $Bv[$i];
            ++$k;
        }

        if (count($bj) > $k) {
            $bj = array_slice($bj, 0, $k);
        }

        $fcn4($L,$b,$x,$bj,$xj);

        for ($j = count($bj)-1; $j != -1; --$j) {
                $b[$bj[$j]] = 0;
        }

        $fcn4($U,$x,$b,$xj,$bj);

        return $b;
    }
}