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

use NDSE\Math\Matrix;
use NDSE\Math\Sparse;

/**
 * Description of makeH
 *
 * @author Márcio A. Tamashiro
 */
class makeH extends \Thread
{
 
  protected $input;
  protected $JACOB;
  
  public function __construct($input)
  {
    $this->input = $input;
  }

  public function run()
  {
    $nt = $this->input[0];
    $npvq = $this->input[1];
    $V = $this->input[2];
    $theta = $this->input[3];
    $PCALC = $this->input[4];
    $QCALC = $this->input[5];
    $Ybus = $this->input[6];
    $IPESP = $this->input[7];
    $LQESP = $this->input[8];
    $IQESP = $this->input[9];

    $JACOB = new Sparse($nt,$nt);
//    $JACOB = MATRIX::zeros($nt,$nt);

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
    $this->JACOB = $JACOB;
  }

  public function get()
  {
      return $this->JACOB;
  }
}