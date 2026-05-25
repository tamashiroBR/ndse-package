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

namespace NDSE;

/**
 * Defines File Class
 *
 * @author Márcio A. Tamashiro
 */
class File
{
    /**
     * 
     */
    private $file = [];

    /**
     * 
     */
    private $path = [];

    /**
     * 
     */
    private $name = [];

    /**
     * 
     */
    private $ext = [];

    /**
     * 
     */
    private $data = [];

    /**
     * 
     */
    private $type_ext = ['json'];
    
    /**
     * 
     */
    private $type_data = ['lf','ta'];
    
    /**
     * Constructs
     */
    public function __construct($file)
    {  
        $this->file = pathinfo($file)['basename'];
        $this->path = pathinfo($file)['dirname'];
        $this->name = pathinfo($file)['filename'];
        $this->ext = pathinfo($file)['extension'];
    }
	
    /**
     * Defines 
     */
    public function open()
    {
        if (is_file($this->file) && in_array($this->ext,$this->type_ext)) {
            return true;
        } else {
            return false;
        }
    }
	
    /**
     * Defines 
     */
    public function read()
    {
        if ($this->ext == 'json') {
            $content = file_get_contents($this->file);
            $json = json_decode($content);

            $info = $json->info;
            $type = $info[0];
            if (in_array($type,$this->type_data)) {   
                $this->setData($type,$json);
            }
        }
        return $this->data;
    }
    
    /**
     * Defines 
     */
    private function setData($type,$json)
    {
        switch ($type) {
            case 'lf': $this->data = [
                                        'optLF' => $json->optLF,
                                        'bus' => $json->bus,
                                        'branch' => $json->branch
                                     ];
                       break;
            case 'ta': $this->data = [
                                        'optLF' => $json->optLF,
                                        'optTA' => $json->optTA,
                                        'bus' => $json->bus,
                                        'branch' => $json->branch,
                                        'gen' => $json->gen,
                                        'exc' => $json->exc,
                                        'gov' => $json->gov,
                                        'event' => $json->event             
                                     ]; 
                       break;
              default: break;
        }       
    }

    /**
     * Defines 
     */
    public function import(){}
    
    /**
     * Defines 
     */
    public function export(){}
}