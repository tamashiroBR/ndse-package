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
 
/**
 * Defines Angle Class
 *
 * @author Márcio A. Tamashiro
 */
class Angle
{
    const TYPE_RAD = 'rad';
    const TYPE_DEG = 'deg';

    /**
     * Stores radians angle's value
     */
    protected $ang_rad = [];

    /**
     * Stores degrees angle's value
     */
    protected $ang_deg = [];

    /**
     * Stores angle's type
     */
    protected $type = [];

    /**
     * Create new Angle, from radians by default, or with second argument,
     * can be degrees too
     */
    public function __construct($ang, $type = self::TYPE_RAD)
    {
        $type = strtolower($type);
        $this->type = $type;

        switch ($this->type) {
            case self::TYPE_DEG: $this->ang_deg = $ang;
                                 $this->ang_rad = deg2rad($ang);
                                 break;
            case self::TYPE_RAD: $this->ang_rad = $ang;
                                 $this->ang_deg = rad2deg($ang);
                                 break;
                        default: break;
        }
    }
	
    /**
     * Gets Angle object as degrees
     */
    public function deg()
    {
        return $this->ang_deg;
    }

    /**
     * Gets Angle object as radians
     */
    public function rad()
    {
        return $this->ang_rad;
    }

    /**
     * Display angle's value as radians or degrees
     */
    public function __toString()
    {
	if ($this->type == self::TYPE_DEG) {
            return $this->ang_deg.'&deg;';
        }
		
	if ($this->type == self::TYPE_RAD) {
            return $this->ang_rad." rad";
        }
    }
}
