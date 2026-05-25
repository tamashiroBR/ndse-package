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

namespace NDSE\Models;

/**
 * Defines ModelAbstract Class
 */
abstract class AbstractModel
{
    /**
     * 
     */
    protected $input = [];
    
    /**
     * 
     */
    protected $param = [];
    
    /**
     * 
     */
    protected $X = [];
    
    /**
     * 
     */
    protected $dX = [];
   
    /**
     * 
     */
    protected $Y = [];
    
    /**
     * Constructs
     */
    public function __construct($data)
    {    
        $input = array_values($data[0]);
        $param = array_values($data[1]);

        $ind = 0;
        foreach ($this->idv['input'] as $k => $v) {
            $this->input[$ind] = $input[$ind];
            $ind++;
        }
		
        $ind = 2;
        foreach ($this->idv['param'] as $k => $v) {
            $this->param[$ind-2] = $param[$ind];           
            $ind++;
        }
 
        $ind = 0;
        foreach ($this->idv['X'] as $k => $v) {
            $this->X[$ind] = 0;
            $ind++;
        }

        $ind = 0;        
        foreach ($this->idv['dX'] as $k => $v) {
            $this->dX[$ind] = 0;
            $ind++;
        }

        $ind = 0;
        foreach ($this->idv['Y'] as $k => $v) {
            $this->Y[$ind] = 0;
            $ind++;
        }
    }

    /**
     * Defines
     */
    public function get($name)
    {
        foreach ($this->idv['input'] as $k => $v) {
            if ($name == $v) {
                return $this->input[$k];
            }
        }

        foreach ($this->idv['param'] as $k => $v) {
            if ($name == $v) {
                return $this->param[$k];
            }
        }
       
        foreach ($this->idv['X'] as $k => $v) {
            if ($name == $v) {
                return $this->X[$k];
            }
        }
     
        foreach ($this->idv['dX'] as $k => $v) {
            if ($name == $v) {
                return $this->dX[$k];
            }
        }
        
        foreach ($this->idv['Y'] as $k => $v) {
            if ($name == $v) {
                return $this->Y[$k];
            }
        }
    }

    /**
     * Defines
     */
    public function set($name,$value)
    {       
        foreach ($this->idv['X'] as $k => $v) {
            if ($name == $v) {
                $this->X[$k] = $value;
            }
        }
     
        foreach ($this->idv['dX'] as $k => $v) {
            if ($name == $v) {
                $this->dX[$k] = $value;
            }
        }
        
        foreach ($this->idv['Y'] as $k => $v) {
            if ($name == $v) {
                $this->Y[$k] = $value;
            }
        }
    }
   
    /**
     * Defines 
     */
    abstract public function x0();
    
    /**
     * Defines 
     */
    abstract public function dFx($input);
 
    /**
     * Defines 
     */
//    abstract public function Gy($input);
}