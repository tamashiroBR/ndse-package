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

use NDSE\Math\Complex;
use NDSE\Math\Sparse;

/**
 * Defines Matrix Class
 *
 * @author Márcio A. Tamashiro
 */
class Matrix extends AbstractMatrix
{
    /**
     * Elements of the matrix, as an array of array
     */
    protected $arr = [];

    /**
     * Size of the matrix, column and row number
     */
    protected $num = ['rows' => 0, 'cols' => 0];

    /**
     * Constructs new matrix giving its elements
     */
    public function __construct($arr = [])
    {      
        if (is_array($arr) && !empty($arr)) {
            $this->arr = $arr;
            $this->num['rows'] = count($arr);
            $this->num['cols'] = count($arr[0]);
        }
    }
	
    /**
     * Defines 
     */
    public function get($row = [], $col = [])
    {
        $arr_out = [];
        $arr = $this->arr;

        if ((empty($row) && $row != 0) && (empty($col) && $col !=0)) {
            if (count($arr) == 1) {
                return $arr[0];
            } else {
                return $arr;
            }
        }

        if ((($row >= 0) && ($row < count($arr))) && (empty($col) && $col != 0)) {
            return $arr[$row];
        }

        if ((($col >= 0) && ($col < count($arr[0]))) && (empty($row) && $row != 0)) {
            foreach ($arr as $k => $v) {
                $arr_out[] = [$v[$col]];
            }
            return $arr_out;
        } 

        if ((($row >= 0) && ($row < count($arr))) && (($col >= 0) && ($col < count($arr[0])))) {
            return $arr[$row][$col];
        }
    }

    /**
     * Sets one item at given row and column
     */
    public function set($value, $row = [], $col = [])
    {
        if (is_array($value)) {
            if ((($row >= 0) && ($row < count($value))) && (empty($col) && $col != 0)) {
		foreach ($value as $col => $v) {
                    $this->arr[$row][$col] = $v;
		}
            } elseif ((($col >= 0) && ($col < count($value[0]))) && (empty($row) && $row != 0)) {
                foreach ($value as $row => $v) {
                    $this->arr[$row][$col] = $v[0];
                }
            }
        } elseif (is_numeric($value) || $value instanceof Complex) {
            if(
                ($row >= 0 && $row < $this->getN('rows'))
                &&
                ($col >= 0 && $col < $this->getN('cols'))
            ) {
                $this->arr[$row][$col] = $value; 
            }
        }
    }
 
    /**
     * Adds the given matrix with the current one to give another new matrix
     */
    public function add($matrix)
    {
        if (($matrix instanceof Matrix) && ($this->equal($matrix))) {
            $arr_out = [];

            foreach ($this->arr as $row => $item) {
                $arrOther = $matrix->get($row,[]);
                $arrNew = [];

                foreach ($item as $col => $value) {
                    if ($arrOther[$col] instanceof Complex) {
                        $arrNew[] = $arrOther[$col]->add($value);
                    } elseif ($value instanceof Complex) {
                        $arrNew[] = $value->add($arrOther[$col]);
                    } else {
                        $arrNew[] = $arrOther[$col] + $value;
                    }
                }

                $arr_out[] = $arrNew;
            }

            return new self($arr_out);
        }
    }

    /**
     * Multiplies current matrix to another one or to a scalar
     */
    public function multiply($matrix)
    {
        $multiplyAllow = false;

        if (is_numeric($matrix)) {
            $multiplyAllow = true;
        }

        if ($matrix instanceof Complex) {
            $multiplyAllow = true;
        }

        if ($matrix instanceof Matrix) {
            if ($this->getN('cols') == $matrix->getN('rows')) {
                $multiplyAllow = true;
            }
        }

        if ($multiplyAllow) {
            if ($matrix instanceof Matrix) {
                $arr_out = [];

                //for ($i = 0; $i < $this->getN(rows); $i++) {
                foreach ($this->arr as $i => $v) {
                    $arrOutRow = [];

                    for ($j = 0; $j < $matrix->getN('cols'); $j++) {
                        $arrCol = [];
                        $arrRow = [];
                        $arrCol = $matrix->get([],$j);
                        $arrRow = $this->get($i,[]);

                        $arrItem = [];
                        $hasComplex = false;

                        foreach ($arrCol as $row => $value) {
                            if ($arrRow[$row] instanceof Complex) {
                                $arrItem[] = $arrRow[$row]->multiply($value[0]);
                                $hasComplex = true;
                            } elseif ($value[0] instanceof Complex) {
                                $arrItem[] = $value[0]->multiply($arrRow[$row]);
                                $hasComplex = true;
                            } else {
                                $arrItem[] = $arrRow[$row] * $value[0];
                            }
                        }

                        if ($hasComplex) {
                            $sum = new Complex(0, 0);

                            foreach ($arrItem as $item) {
                                if (is_numeric($item)) {
                                    $item = new Complex($item, 0);
                                }

                                $sum = $item->add($sum);
                        }

                            $arrOutRow[] = $sum;
                        } else {
                            $arrOutRow[] = array_sum($arrItem);
                        }
                    }

                    $arr_out[] = $arrOutRow;
                }
        }

            if (is_numeric($matrix) || $matrix instanceof Complex) {
                $arr_out = [];

                for ($i = 0; $i < $this->getN('rows'); $i++) {
                    $arrRow = [];
                    $arrRow = $this->get($i,[]);

                    foreach ($arrRow as $col => $value) {
                        if (is_numeric($matrix)) {
                            if ($value instanceof Complex) {
                                $arrRow[$col] = $value->multiply($matrix);
                            } else {
                                $arrRow[$col] = $matrix * $value;
                            }
                        } else {
                            $arrRow[$col] = $matrix->multiply($value);
                        }
                    }

                    $arr_out[] = $arrRow;
                }
            }

            return new self($arr_out);
        }
    }

    /**
     * 
     */
    public static function zeros($row, $col)
    {
        $arr_out = [];

        for ($i = 0; $i < $row; $i++) {
            $arr_out[] = array_fill(0,$col,0);
        }

        return new self($arr_out);
/*        if ($row > 1) {
            return $arr_out;
        } else {
            return $arr_out[0];
        }*/
    }

    /**
     * 
     */
    public function subMatrix($rows = [], $cols = [])
    {
        $arr = $this->arr;
        $arr_out = [];
        $temp = [];
        // eval() was replaced with explicit comparisons for PHP 8 compatibility.
        $expr = function($v, $c) {
            $val   = (float) $v;
            $limit = (float) $c[2];
            return match($c[1]) {
                '=='  => $val == $limit,
                '!='  => $val != $limit,
                '<'   => $val <  $limit,
                '>'   => $val >  $limit,
                '=<'  => $val <= $limit,
                '>='  => $val >= $limit,
                default => false,
            };
        };

        if ((empty($rows) && $rows != 0)
            &&
            (is_array($cols) && !is_null($cols))
            &&
            (max($cols) < $this->getN('cols'))
            // &&
            // !in_array($cols[1],['==','!=','<','>','=<','>='])			
        ) {
            foreach ($arr as $r => $v) {
                $j = 0;
                foreach ($cols as $k => $c) {
                    $arr_out[$r][$j] = $arr[$r][$c];
                    $j++;
                }
            }
        } 

        if ((empty($cols) && $cols != 0)
            &&
            (is_array($rows) && !is_null($rows))
            &&
            (max($rows) < $this->getN('rows'))
            &&
            !in_array($rows[1],['==','!=','<','>','=<','>='])	
        ) {
            $i = 0;
            foreach ($rows as $r => $v) {
                foreach ($arr[0] as $c => $vc) {
                    $arr_out[$i][$c] = $arr[$rows[$r]][$c];
                }
                $i++;
            }
        }

        if ((empty($rows) && $rows != 0)
            &&		
            is_array($cols) 
            &&
            count($cols) == 3
            &&
            is_numeric($cols[0])
            &&
            is_numeric($cols[2])
            &&
            in_array($cols[1],['==','!=','<','>','=<','>='])
        ) {
            foreach ($arr as $r => $rv) {
                $j = 0;
                foreach ($rv as $c => $v) {				
                    $v = $arr[$cols[0]][$c];			
                    if ($expr($v,$cols)) {
                        $arr_out[$r][$j] = $arr[$r][$c];
                        $j++;
                    }
                }
            }
        }			

        if ((empty($cols) && $cols != 0)
            &&		
            is_array($rows) 
            &&
            count($rows) == 3
            &&
            is_numeric($rows[0])
            &&
            is_numeric($rows[2])
            &&
            in_array($rows[1],['==','!=','<','>','=<','>='])
        ) {	
            $i = 0;
            foreach ($arr as $r => $rv) {
                $v = $arr[$r][$rows[0]];			
                if ($expr($v,$rows)) {
                    foreach ($rv as $c => $v) {	
                        $arr_out[$i][$c] = $arr[$r][$c];
                    }
                    $i++;
                }
            }
        }

//		if (count($arr_out) == 1) {
//			return new self($arr_out[0]);	
//		} else {
        return new self($arr_out);	
//		}
	}

    /**
     * Tests whether the current matrix is the same as the given one
     */
    public function equal($matrix)
    {
        return (
            $this->getN('cols') == $matrix->getN('cols')
            &&
            $this->getN('rows') == $matrix->getN('rows')
        );
    }

    /**
     * Returns the transpose of the current matrix
     */
    public function transpose()
    {
        $arr_out = [];

        foreach ($this->arr as $row => $rv) {
            foreach ($rv as $col => $rc) {
                $arr_out[$col][$row] = $rc;
            }
        }

//		if (count($arr_out) == 1) {
//			return new self($arr_out[0]);	
//		} else {
	return new self($arr_out);	
//		}
    }

    /**
     * Defines
     */
    public function sparse()
    {
        $Ai = [];
        $Aj = [];
        $Av = [];

        $counts = [];

        $m = $this->getN('rows');

        $temp = [];
        for($i = $m-1; $i != -1; --$i) {
                $temp = $this->get($i);

                for($j = 0; $j < count($temp); ++$j) {
                        while($j >= count($counts)) $counts[count($counts)] = 0;
                                if($temp[$j] != 0) $counts[$j]++;
                }
        }

        $n = count($counts);

        $Ai[0] = 0;

        for($i = 0; $i < $n; ++$i) {
                $Ai[$i+1] = $Ai[$i] + $counts[$i];
        }

        $temp = [];
        for($i = $m-1; $i != -1; --$i) {
                $temp = $this->get($i);
                for($j = 0; $j < count($temp); ++$j) {
                        if($temp[$j] != 0) {
                                $counts[$j]--;
                                $Aj[$Ai[$j]+$counts[$j]] = $i;
                                $Av[$Ai[$j]+$counts[$j]] = $temp[$j];
                        }
                }
        }

        ksort($Aj);
        ksort($Av);

        return new Sparse(['i' => $Ai, 'j' => $Aj,'v' => $Av]);
    }

    /**
     * Returns human readable matrix string
     */
    public function __toString()
    {
        $arr_out = [];
        $arr_col_width = [];

        foreach ($this->arr as $row) {
            $arr_row = [];

            foreach ($row as $col => $value) {
                //if (!($value instanceof Complex)) {
				//	$value = round($value, self::PRECISION);
				//}
				
                $arr_row[] = (string) $value;

                $int_length = strlen($arr_row[count($arr_row) - 1]);

                if(
                    (isset($arr_col_width[$col]) && $arr_col_width[$col] < $int_length)
                    ||
                    !isset($arr_col_width[$col])
                ) {
                    $arr_col_width[$col] = $int_length;
                }
            }

            $arr_out[] = $arr_row;
        }

        foreach ($arr_out as $row => $item) {
            $arr_row = [];

            foreach ($item as $col => $value) {
                $arr_row[] = str_pad($value, $arr_col_width[$col], ' ' ,STR_PAD_LEFT);
            }

            $arr_out[$row] = implode('  ', $arr_row);
        }

        return implode(PHP_EOL, $arr_out);
    }
}