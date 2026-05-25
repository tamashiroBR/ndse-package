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

/**
 * Defines ToolsAbstract
 *
 * @author Márcio A. Tamashiro
 */
abstract class AbstractTools
{
    /**
     *
     */
    protected $option = [];

    /**
     *
     */
    protected $data = [];

    /**
     * Defines
     */
    public function getOption($opt = [])
    {
        if (empty($opt) && $opt != 0) {
            return $this->option;
        }

        if (is_string($opt)) {
            $opt = strtolower($opt);
            return $this->option[$opt];
        }
    }

    /**
     * Defines
     */
    public function getData($name, $row = [], $col = [])
    {
        $name = strtolower($name);

        foreach ($this->data as $k => $v) {
            $data_name[] = $k;
        }

        if (in_array($name, $data_name)) {
            $arr = $this->data[$name];
            if ((empty($row) && $row != 0) && (empty($col) && $col != 0)) {
                return $arr;
            }

            if (($row >= 0) && ($row < count($arr)) && (empty($col) && $col != 0)) {
                return $arr[$row];
            }

            if (($col >= 0) && ($col < count($arr[0])) && (empty($row) && $row != 0)) {
                foreach ($this->data[$name] as $k => $v) {
                    $arr[$k] = $v[$col];
                }
                return $arr;
            }

            if (($row >= 0) && ($row < count($arr)) && ($col >= 0) && ($col < count($arr[0]))) {
                return $arr[$row][$col];
            }
        }
    }

    /**
     * Defines
     */
    public function setData($name, $value)
    {
        $name = strtolower($name);

        foreach ($this->data as $k => $v) {
            $data_name[] = $k;
        }

        if (in_array($name, $data_name)) {
            $this->data[$name] = $value;
        }
    }

    /**
     * Defines
     */
    abstract public function run();
}