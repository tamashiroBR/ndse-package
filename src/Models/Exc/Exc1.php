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
namespace NDSE\Models\Exc;

use NDSE\Math\Complex;
use NDSE\Models\AbstractModel;

/**
 * Defines Exciter type 1: IEEE type I
 *
 * @author Márcio A. Tamashiro
 */
class Exc1 extends AbstractModel
{   
    /**
     * 
     */
    protected $idv = [
                      'input' => ['Ug0','Efd0'],
                      'param' => ['Ka','Ta','Ke','Te','Kf','Tf','Aex','Bex','Urmin','Urmax'],
                      'X' => ['Efd', 'Uf', 'Ur'],
                      'dX' => ['dEfd','dUf', 'dUr'],
                      'Y' => ['Uref','Uref2']
                     ];
    
    /**
     * Constructs
     */
    public function __construct($data)
    {
        parent::__construct($data); 
    }
   
    /**
     * Defines 
     */
    public function x0()
    {    
        $Efd0 = $this->get('Efd0');
        $Ka = $this->get('Ka');
        $Ke = $this->get('Ke');
        $Aex = $this->get('Aex');
        $Bex = $this->get('Bex');

        $U0 = $this->get('Ug0');

        $Uf0 = 0;
        $Ux0 = $Aex*exp($Bex*$Efd0);
        $Ur0 = $Ux0+$Ke*$Efd0;
        $Uref2 = $U0 + ($Ux0+$Ke*$Efd0)/$Ka - $U0;
        $Uref = $U0;

        $this->set('Uref',$Uref);
        $this->set('Uref2',$Uref2);

        $this->set('Efd',$Efd0);
        $this->set('Uf',$Uf0);
        $this->set('Ur',$Ur0);
      
        return [$Efd0, $Uf0, $Ur0];
    }
    
    /**
     * Defines 
     */
    public function dFx($input)
    {
        $U = $input[0];
        $Efd = $input[1];
        $Uf = $input[2];
        $Ur = $input[3];

        $Ka = $this->get('Ka');
        $Ta = $this->get('Ta');
        $Ke = $this->get('Ke');
        $Te = $this->get('Te');
        $Kf = $this->get('Kf');
        $Tf = $this->get('Tf');
        $Aex = $this->get('Aex');
        $Bex = $this->get('Bex');
        $Urmin = $this->get('Urmin');
        $Urmax = $this->get('Urmax');
        $Uref = $this->get('Uref');
        $Uref2 = $this->get('Uref2');

        $Ux = $Aex*exp($Bex*$Efd);
        $dUr = 1/$Ta * ($Ka*($Uref - $U + $Uref2 - $Uf) - $Ur);
        $dUf = 1/$Tf * (($Kf/$Te) * ($Ur - $Ux - $Ke*$Efd) - $Uf);

        if ($Ur > $Urmax) {
                $Ur2 = $Urmax;
        } elseif ($Ur < $Urmin) {
                $Ur2 = $Urmin;
        } else {
                $Ur2 = $Ur;
        }
        
        $dEfd = 1/$Te * ( $Ur2 - $Ux - $Ke*$Efd);
               
        return [$dEfd, $dUf, $dUr];
    }
}