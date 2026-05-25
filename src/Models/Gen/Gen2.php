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
 * Defines Generator type 2: 4th order model
 *
 * @author Márcio A. Tamashiro
 */
class Gen2 extends AbstractModel
{   
    /**
     * 
     */
    protected $idv = [
                      'input' => ['freq0','Ug0','Pg0','Qg0'],
                      'param' => ['H','D','xd','xd_tr','xq','xq_tr','Td_tr','Tq_tr'],
                      'X' => ['delta','omega','Eq_tr','Ed_tr'],
                      'dX' => ['ddelta','domega','dEq','dEd'],
                      'Y' => ['Efd','Id','Iq']
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
        $xd = $this->get('xd');
        $xq = $this->get('xq');
        $xd_tr = $this->get('xd_tr');
        $xq_tr = $this->get('xq_tr');
        $Ug0 = $this->get('Ug0');
        $Pg0 = $this->get('Pg0');
        $Qg0 = $this->get('Qg0');
        
        $omega0 = 2*M_PI*$freq0;

        // Initial machine armature currents
        $Sg0 = new Complex($Pg0,-$Qg0);
        $Ia = $Sg0->div($Ug0->conj());

        $Ia0 = $Ia->abs();        
        $phi0 = $Ia->ang();

        // Initial Steady-state internal EMF
        $Eq = $Ug0->add($Ia->multiply(new Complex(0,$xq)));
        
        $Eq0 = $Eq->abs();        
        $delta0 = $Eq->ang();
        
        // Machine currents in dq frame        
        $Id0 = -$Ia0*sin($delta0 - $phi0);
        $Iq0 = $Ia0*cos($delta0 - $phi0);

        // Field voltage
        $Efd0 = $Eq0 - ($xd - $xq)*$Id0;

        // Initial Transient internal EMF
        $Eq_tr0  = $Efd0 + ($xd - $xd_tr)*$Id0;
        $Ed_tr0  = -($xq - $xq_tr)*$Iq0;

        $this->set('Efd',$Efd0);
        
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
        $xd = $this->get('xd');
        $xq = $this->get('xq');
        $xd_tr = $this->get('xd_tr');
        $xq_tr = $this->get('xq_tr');
        $Td_tr = $this->get('Td_tr');
        $Tq_tr = $this->get('Tq_tr');
        $Efd = $this->get('Efd');
        $Ed_tr = $this->get('Ed_tr');
        $Eq_tr = $this->get('Eq_tr');
        $Id = $this->get('Id');
        $Iq = $this->get('Iq');

        $Pm = $input[0];
        $omega = $input[1];
        $Pe =  $input[2];

        $ddelta = $omega - $omega0;

        $domega = M_PI*($freq0/$H)*(-$D*$ddelta + $Pm - $Pe);

        $dEq = (1/$Td_tr) * ($Efd - $Eq_tr + ($xd - $xd_tr)*$Id);

        $dEd = (1/$Tq_tr) * (-$Ed_tr - ($xq - $xq_tr)*$Iq);
               
        return [$ddelta, $domega, $dEq, $dEd];
    }
 
    /**
     * Defines 
     */
    public function Gy($input)
    {       
        $delta = $this->get('delta');
        $Eq_tr = $this->get('Eq_tr');
        $Ed_tr = $this->get('Ed_tr');
        $xd_tr = $this->get('xd_tr');
        $xq_tr = $this->get('xq_tr');
        
        //Calculate machine currents and power     
        $U = $input->abs();
        $theta = $input->ang();

        // Tranform U to rotor frame of reference
        $vd = -$U*sin($delta- $theta);
        $vq = $U*cos($delta - $theta);

        $Id = ($vq - $Eq_tr)/$xd_tr;
        $Iq = -($vd - $Ed_tr)/$xq_tr;

        $Pe = $Eq_tr*$Iq + $Ed_tr*$Id + ($xd_tr - $xq_tr)*$Id*$Iq;

        // Calculate generator Norton current for augmented network
        // Ig = Ia + U/(j*xd_tr) where Ia = (Iq + j*Id) * e^(j*delta)
        // This ensures Yaug * U = Ig is consistent with Ybus * U = Ia
        $Ia = new Complex($Iq, $Id);
        $Ia_global = $Ia->multiply(new Complex(cos($delta),sin($delta)));
        $Ig = $Ia_global->add($input->div(new Complex(0,$xd_tr)));

        $this->set('Id',$Id);
        $this->set('Iq',$Iq);
        
        return [$Pe, $Ig];
    }
}