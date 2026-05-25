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

/**
 * Defines Sparse Matrix Class
 *
 * @author Márcio A. Tamashiro
 */
class Sparse extends AbstractMatrix
{
    /**
     * Elements of the sparse matrix
     */
    protected $arr = ['i' => [], 'j' => [], 'v' => []];

    /**
     * Size of the sparse matrix, column and row number
     */
    protected $num = ['rows' => 0, 'cols' => 0];

    /**
     * Constructs new matrix giving its elements
     */
    public function __construct()
    {      
        $numargs = func_num_args();

        if ($numargs == 1) {
            $arr = func_get_arg(0);
            if (is_array($arr) && count($arr) == 3) {
                $arr = array_values($arr);
                $i = 0;
                foreach ($this->arr as $k => $v) {
                    $this->arr[$k] = $arr[$i];
                    $i++;
                }
                $this->num['rows'] = count($arr[0])-1;
                $this->num['cols'] = max($arr[1])+1;
            }
        } elseif ($numargs == 2) {
            $dim_r = func_get_arg(0);
            $dim_c = func_get_arg(1);
            if (is_numeric($dim_r) && is_numeric($dim_c)) {
                $this->num['rows'] = $dim_r;
                $this->num['cols'] = $dim_c;
            }
        }
    }

    /**
     * Defines magic getter
     */
    public function __get($name)
    {
        switch($name) {
            case      'i':
            case      'j':
            case      'v': return $this->arr[$name];
            default: break;
        }
    }	
	
    /**
     * Defines 
     */
    public function get($row = [], $col = [])
    {
        if ((empty($row) && $row != 0) && (empty($col) && $col !=0)) {
            return [$this->i, $this->j, $this->v];
        }

        $Ai = $this->i;
        $Aj = $this->j;
        $Av = $this->v;

        $m = $this->getN('rows');
        $n = $this->getN('cols');

        $i = $j = [];

        if(empty($row) && $row != 0) {
            for ($ii = 0; $ii < $m; $ii++) {
                $i[] = $ii;
            }
        } elseif (is_numeric($row)) {
            $i[] = $row;
        }

        if (empty($col) && $col != 0) { 
            for ($jj = 0; $jj < $n; $jj++) {
                $j[] = $jj;
            }
        } elseif (is_numeric($col)) {
            $j[] = $col;
        }

        $P = count($i);
        $Q = count($j);

        for ($ii = 0; $ii < $n; $ii++) {
            $Bi[] = 0;
        }
        $Bj = [];
        $Bv = [];	

        for ($ii = 0; $ii < $m; $ii++) {
            $x[] = 0;
            $flags[] = 0;
        }

        $count = 0;

        for ($q = 0; $q < $Q; $q++) {
            $jq = $j[$q];
			if (isset($Ai[$jq])) $q0 = $Ai[$jq]; else $q0 = 0;
            if (isset($Ai[$jq+1])) $q1 = $Ai[$jq+1]; else $q1 =0;

            for ($p = $q0; $p < $q1; $p++) {
                $r = $Aj[$p];
                $flags[$r] = 1;
                $x[$r] = $Av[$p];
            }

            for ($p = 0; $p < $P; $p++) {
                $ip = $i[$p];
                if ($flags[$ip]) {
                    $Bj[$count] = $p;
                    $Bv[$count] = $x[$i[$p]];
                    ++$count;
                }
            }

            for ($p = $q0; $p < $q1; ++$p) {
                $r = $Aj[$p];
                $flags[$r] = 0;
            }

            $Bi[$q+1] = $count;
        }

        if (empty($Bv)) {
			return null;
		} elseif (count($Bv) == 1) {
            return $Bv[0];
        } else {
            return new self([$Bi, $Bj, $Bv]);				
        }

//		return [$Bi, $Bj, $Bv];	
    }

    /**
     * Defines 
     */
    public function set($value, $row = [], $col = [])
    {
//          $Av = $this->get($row,$col)[2][0];
        $Av = $this->get($row,$col);

        if (is_null($Av)) {
            $Av = 0;
        }

        for ($i = 0; $i < ($this->getN('cols'))+2; $i++) {
            if ($i < $col+1) {
                $Bi[] = 0;				
            }
            else {
                $Bi[] = 1;				
            }
        }

        $Bj[] = $row;

        if (($value instanceof Complex) && ($Av instanceof Complex)) {
            $Bv[] = $value->add($Av->neg());
        } elseif ((is_numeric($value)) && (is_numeric($Av))) {
            $Bv[] = $value-$Av;
        } elseif (($value instanceof Complex) && (is_numeric($Av))) {
            $Bv[] = $value->add(-$Av);
        } elseif ((is_numeric($value)) && ($Av instanceof Complex)) {
            $Bv[] = $Av->add(-$value);
        }

        //$B =  new self($this->nrows,$this->ncols);		
        //$B->i = $Bi;
        //$B->j = $Bj;
        //$B->v = $Bv;
        $B = new self([$Bi,$Bj,$Bv]);

        $out = $this->add($B);
        $this->arr['i'] = $out->i;
        $this->arr['j'] = $out->j;
        $this->arr['v'] = $out->v;
    }

    /**
     * Defines
     */
    public function add($sparse)
    {
        $Xi = $this->arr['i'];
        $Xj = $this->arr['j'];
        $Xv = $this->arr['v'];

        $Yi = $sparse->i;
        $Yj = $sparse->j;
        $Yv = $sparse->v;

        $m = $this->getN('rows');
        $n = $this->getN('cols');

        for ($i = 0; $i < $m; $i++) {
            $x[] = 0;
            $y[] = 0;
        }

        for ($i = 0; $i < $n; $i++) {
            $Zi[] = 0;
        }
        $Zj = [];
        $Zv = [];

        $p = 0;

        for ($i = 0; $i < $n; ++$i) {
			if (isset($Xi[$i])) $j0 = $Xi[$i]; else $j0 = 0;
			if (isset($Xi[$i+1])) $j1 = $Xi[$i+1]; else $j1 = 0;
//             $j0 = $Xi[$i];
//             $j1 = $Xi[$i+1];
            for ($j = $j0; $j != $j1; ++$j) {
                $k = $Xj[$j];
                $x[$k] = 1;
                $Zj[$p] = $k;
                ++$p;
            }

            $j0 = $Yi[$i];
            $j1 = $Yi[$i+1];
            for ($j = $j0; $j != $j1; ++$j) {
                $k = $Yj[$j];
                $y[$k] = $Yv[$j];
                if ($x[$k] == 0) {
                    $Zj[$p] = $k;
                    ++$p;
                }
            }

            $Zi[$i+1] = $p;

			if (isset($Xi[$i])) $j0 = $Xi[$i]; else $j0 = 0;
			if (isset($Xi[$i+1])) $j1 = $Xi[$i+1]; else $j1 = 0;
//             $j0 = $Xi[$i];
//             $j1 = $Xi[$i+1];
            for ($j = $j0; $j != $j1; ++$j) {
                $x[$Xj[$j]] = $Xv[$j];
            }

			if (isset($Zi[$i])) $j0 = $Zi[$i]; else $j0 = 0;
			if (isset($Zi[$i+1])) $j1 = $Zi[$i+1]; else $j1 = 0;
//             $j0 = $Zi[$i];
//             $j1 = $Zi[$i+1];
            for ($j = $j0; $j != $j1; ++$j) {
                $k = $Zj[$j];
                $xk = $x[$k];
                $yk = $y[$k];
                //$zk = $xk + $yk;
                //echo "xk: ".$xk." yk: ".$yk."<br>";
                if (($xk instanceof Complex) && ($yk instanceof Complex)) {
                    //echo "complex complex";
                    $zk = $xk->add($yk);
                } elseif ((is_numeric($xk)) && (is_numeric($yk))) {
                    //echo "numeric numeric";
                    $zk = $xk+$yk;
                } elseif (($xk instanceof Complex) && (is_numeric($yk))) {
                    //echo "complex numeric";
                    $zk = $xk->add($yk);
                } elseif ((is_numeric($xk)) && ($yk instanceof Complex)) {
                    //echo "numeric complex";
                    $zk = $yk->add($xk);
                }
                $Zv[$j] = $zk;
            }

			if (isset($Xi[$i])) $j0 = $Xi[$i]; else $j0 = 0;
			if (isset($Xi[$i+1])) $j1 = $Xi[$i+1]; else $j1 = 0;
//             $j0 = $Xi[$i];
//             $j1 = $Xi[$i+1];
            for ($j = $j0; $j != $j1; ++$j) {
                $x[$Xj[$j]] = 0;
            }

			if (isset($Yi[$i])) $j0 = $Yi[$i]; else $j0 = 0;
			if (isset($Yi[$i+1])) $j1 = $Yi[$i+1]; else $j1 = 0;
//             $j0 = $Yi[$i];
//             $j1 = $Yi[$i+1];
            for ($j = $j0; $j != $j1; ++$j) {
                $y[$Yj[$j]] = 0;
            }
        }

        ksort($Zj);
        ksort($Zv);

        //$Z =  new self($m,$n);
        //$Z->i = $Zi;
        //$Z->j = $Zj;
        //$Z->v = $Zv;

        //$Z = [$Zi, $Zj, $Zv];

        //return $Z;

        return new self([$Zi, $Zj, $Zv]);
    }
	
    /**
     * Defines
     */
    public function multiply($sparse)
    {
        $Ai = $this->arr['i'];
        $Aj = $this->arr['j'];
        $Av = $this->arr['v'];

        $Bi = $sparse->i;
        $Bj = $sparse->j;
        $Bv = $sparse->v;

        $m = $this->getN('rows');
        $n = $this->getN('cols');
        $o = $sparse->getN('cols');


        for ($i = 0; $i < $m; $i++) {
            $flags[] = 0;
        }

        $C =  new self($m,$o);

        for ($i = 0; $i < $o; $i++) {
            $Ci[] = 0;
        }
        $Cj = [];
        $Cv = [];

        for ($k = 0; $k != $o; ++$k) {
            $j0 = $Bi[$k];
            $j1 = $Bi[$k+1];
            $p = 0;
            $xj = [];
            for ($j = $j0; $j < $j1; ++$j) {
                $a = $Bj[$j];
                $b = $Bv[$j];
                $i0 = $Ai[$a];
                $i1 = $Ai[$a+1];
                for ($i = $i0; $i < $i1; ++$i) {
                    $l = $Aj[$i];
                    if ($flags[$l] === 0) {
                        $xj[$p] = $l;
                        $flags[$l] = 1;
                        $p = $p+1;
                    }
                    $x[$l] = $x[$l] + $Av[$i]*$b;
                }
            }
            $j0 = $Ci[$k];
            $j1 = $j0+$p;
            $Ci[$k+1] = $j1;
            for ($j = $p-1; $j != -1; --$j) {
                $b = $j0+$j;
                $i = $xj[$j];
                $Cj[$b] = $i;
                $Cv[$b] = $x[$i];
                $flags[$i] = 0;
                $x[$i] = 0;
            }
            $Ci[$k+1] = $Ci[$k]+$p;
        }

        ksort($Cj);
        ksort($Cv);

        $C->i = $Ci;
        $C->j = $Cj;
        $C->v = $Cv;

        return $C;
    }
		
    /**
     * Returns the full of the current sparse matrix
     */
    public function full()
    {
        $m = $this->getN('rows');
        $n = $this->getN('cols');

        for($i = 0; $i < $m; $i++) {
            for($j = 0; $j < $n; $j++) {
                $B[$i][$j] = 0;
            }
        }

        if (!is_null($this->arr['v'])) {
            for($i = 0; $i < $n; $i++) {
                $j0 = $this->arr['i'][$i];
                $j1 = $this->arr['i'][$i+1];

                for($j = $j0; $j < $j1; ++$j) {
                    $B[$this->arr['j'][$j]][$i] = $this->arr['v'][$j];
                }
            }
        }

        return new Matrix($B);
        //return $B;
    }

    /**
     * Returns human readable sparse matrix
     */
    public function __toString()
    {
        $Ai = $this->i;
        $Aj = $this->j;
        $Av = $this->v;

        $n = count($Ai)-1;
        $m = count($Aj);

        $arr_out = [];
        for ($i = 0; $i < $n; ++$i) {
            $j0 = $Ai[$i];
            $j1 = $Ai[$i+1];

            for ($j = $j0; $j != $j1; ++$j) {
                $arr_out[] = '('.$Aj[$j].','.$i.') '.$Av[$j];
            }
        }

        return implode(PHP_EOL, $arr_out);
    }
}