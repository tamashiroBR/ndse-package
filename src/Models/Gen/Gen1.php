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

namespace NDSE\Models\Gen;

use NDSE\Math\Complex;
use NDSE\Models\AbstractModel;

/**
 * Defines Generator type 1: classical model
 *
 * @author Márcio A. Tamashiro
 */
class Gen1 extends AbstractModel
{
    /**
     * 
     */
    protected $idv = [
                      'input' => ['freq0','Ug0','Pg0','Qg0'],
                      'param' => ['H','D','xd','xd_tr'],
                      'X' => ['delta','omega','Eq_tr','Ed_tr'],
                      'dX' => ['ddelta','domega','dEq','dEd'],
                      'Y' => []      
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
        $freq0 = $this->get('freq0');
        $Ug0 = $this->get('Ug0');
        $Pg0 = $this->get('Pg0');
        $Qg0 = $this->get('Qg0');
        $xd_tr = $this->get('xd_tr');
        
        $omega0 = 2*M_PI*$freq0;
        
        // Initial machine armature currents
        $Sg0 = new Complex($Pg0,-$Qg0);
        $Ia0 = $Sg0->div($Ug0->conj());

        // Initial Steady-state internal EMF
        $temp = $Ug0->add($Ia0->multiply(new Complex(0,$xd_tr)));
        $Eq_tr = new Complex($temp->re,$temp->img);

        $Eq_tr0 = $Eq_tr->abs();
        $delta0 = $Eq_tr->ang();
        $Ed_tr0  = 0;
        
        $this->set('delta',$delta0);
        $this->set('Eq_tr',$Eq_tr0);
        $this->set('Ed_tr',$Ed_tr0);
        
        return [$delta0, $omega0, $Eq_tr0, $Ed_tr0];
    }
 
    /**
     * Defines 
     */
    public function dFx($input)
    {
        $freq0 = $this->get('freq0');
        $omega0 = 2*M_PI*$freq0;       
        $H = $this->get('H');
        $D = $this->get('D');
        
        $Pm = $input[0];
        $omega = $input[1];
        $Pe =  $input[2];

        $ddelta = $omega - $omega0;

        $domega = M_PI*($freq0/$H)*(-$D*$ddelta + $Pm - $Pe);
        
        return [$ddelta, $domega, 0, 0];
    }
 
    /**
     * Defines 
     */
    public function Gy($input)
    { 
        $delta = $this->get('delta');
        $Eq_tr = $this->get('Eq_tr');
        $xd = $this->get('xd');
        $xd_tr = $this->get('xd_tr');
        
        //Calculate machine currents and power          
        $U = $input->abs();
        $theta = $input->ang();
        
        $Pe = (1/$xd_tr)*$U*$Eq_tr*sin($delta - $theta);
        
        // Calculate generator currents
        $z = new Complex($Eq_tr*cos($delta),$Eq_tr*sin($delta));
        $Ig = $z->div(new Complex(0,$xd_tr));
        
        return [$Pe, $Ig];
    }
}