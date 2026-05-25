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

use NDSE\Math\Angle;

/**
 * Defines Complex Class
 *
 * @author Márcio A. Tamashiro
 */
class Complex
{
    const RECTANGULAR = 1;
    const POLAR = 2;
    //const PRECISION = 4;

    /**
     * Stores real and imaginary part
     */
    protected $z = ['re' => [], 'img' => []];

    /**
     * If complex is created from polar form, stores original values
     * inside this attribute
     */
    protected $z_polar = ['z_abs' => [], 'z_ang' => []];

    /**
     * Creates new Complex number object giving its real and imaginary parts or
     * modulus and angle
     */
    //public function __construct($arg1, $arg2, $round = self::PRECISION)
    public function __construct($arg1, $arg2)
    {
        $form = self::RECTANGULAR;

        if ($arg2 instanceof Angle) {
            $form = self::POLAR;
        }

        switch ($form) {
            case self::RECTANGULAR: if (is_numeric($arg1) && is_numeric($arg2)) {
                                        $this->z['re'] = $arg1;
                                        $this->z['img'] = $arg2;

                                        $this->z_polar = NULL;
                                    }
                                    break;
                  case self::POLAR: if (is_numeric($arg1) && $arg1 >= 0) {
                                        $this->z_polar['z_abs'] = $arg1;
                                        $this->z_polar['z_ang'] = $arg2->rad();

                                        $this->z['re'] = $arg1 * cos($arg2->rad());
                                        $this->z['img'] = $arg1 * sin($arg2->rad());
                                    }
                                    break;
                           default: break;
        }
    }

    /**
     * Defines magic method getters
     */
    public function __get($name)
    {
        switch($name) {
                case    're':
                case   'img': return $this->z[$name];
                     default: break;
        }
    }
	
    /**
     * Gets the complex's modulus
     */
    public function abs()
    {
        if (!is_null($this->z_polar)) {
            return $this->z_polar['z_abs'];
        }

        return sqrt(pow($this->re, 2) + pow($this->img, 2));
    }

    /**
     * Gets the complex's angle
     */
    public function ang()
    {
        if (!is_null($this->z_polar)) {
            return $this->z_polar['z_ang'];
        }

        //return new Angle(atan2($this->img, $this->re));
        return atan2($this->img, $this->re);
    }

    /**
     * Return conjugate complex number
     */
    public function conj()
    {
        return new self($this->re, -1 * $this->img);
    }

    /**
     * Gives the negative complex number
     */
    public function neg()
    {
        return new self(-1 * $this->re, -1 * $this->img);
    }

    /**
     * Gives the inverse complex number
     */
    public function inv()
    {
        $z = $this->conj();
        $d = pow($this->re, 2) + pow($this->img, 2);
		
        return new self($z->re/$d, $z->img/$d);
    }

    /**
     * Add value to the current complex number, creating new complex number
     */
    public function add($z)
    {
        if (is_numeric($z)) {
            $z = new self($z, 0);
        }

        return new self($this->re + $z->re, $this->img + $z->img);
    }

    /**
     * Substracts complex number `z` from the current one
     */
    public function sub($z)
    {
        if (is_numeric($z)) {
            $z = new self($z, 0);
        }

        return $this->add($z->neg());
    }

    /**
     * Multiplies current complex number with another
     */
    public function multiply($z)
    {
		if (!is_null($z)) {
        if (is_numeric($z)) {
            $z = new self($z, 0);
        }

        return new self(
                        ($this->re * $z->re) - ($this->img * $z->img),
                        ($this->re * $z->img) + ($z->re * $this->img)
                        );
		} else return new self(0,0);
    }

    /**
     * Divides current complex number with another
     */
    public function div($z)
    {
        if ((is_numeric($z) && ($z != 0)) || (($z instanceof Complex) && ($z->re != 0 || $z->img != 0))) {
            if (is_numeric($z)) {
                $z = new self($z, 0);
            }

            $div = pow($z->re, 2) + pow($z->img, 2);

            $re = ($this->re * $z->re) + ($this->img * $z->img);
            $im = ($this->img * $z->re) - ($this->re * $z->img);
            $re = $re / $div;
            $im = $im / $div;

            return new self( $re, $im);
        }
    }

    /**
     * Tests whether given `z` complex number is equal to the current complex
     * number
     */
    public function equal($z)
    {
        if (is_numeric($z)) {
            $z = new self($z, 0);
        }

        return ($z->re == $this->re) && ($z->img == $this->img);
    }

    /**
     * Changes complex number from polar to rectangular form
     */
    public function rectangular()
    {
        if (!is_null($this->z_polar)) {
            return new self($this->re, $this->img);
        }

	return $this;
    }

    /**
     * Changes complex number from rectangular to polar form
     */
    public function polar()
    {
        if (is_null($this->z_polar)) {
            return new self($this->abs(), new Angle($this->ang()));
        }

        return $this;
    }
	
    /**
     * Display complex number as rectangular or polar form
     */
    public function __toString()
    {
        // if polar form
        if (!is_null($this->z_polar)) {
            if ($this->z_polar['z_abs'] == 0) {
                return 0;
            }

            $ang = new Angle($this->z_polar['z_ang']);
            return sprintf('%1$f&ang;%2$f&deg;', $this->z_polar['z_abs'], $ang->deg());
        }

        $str_sign = '-';
        $str_re = '';
        $str_img = 'i';

        if ($this->img > 0) {
            $str_sign = '+';
        }

        if (abs($this->img) > 0) {
            $str_img = (string) abs($this->img) . 'i';
        }

        if ($this->img == 0) {
            return (string) $this->re;
        }

        if ($this->re == 0) {
            if (abs($this->img) == 1) {
                return $this->img == 1 ? 'i' : '-i';
            } else {
                return (string) $this->img . 'i';
            }
        } else {
            $str_re = (string) $this->re;
        }

        $arr = [];
        $arr[] = $str_re;
        $arr[] = $str_img;

        return implode($str_sign, $arr);
    }
}
