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
 * Classe abstrata Matrix
 *
 * @package   NDSE\Math
 * @author    Márcio A. Tamashiro <marcio.tamashior@gmail.com>
 * @since     1.0.0
 */
abstract class AbstractMatrix
{
    /**
     * Dimensão da matriz, número de linhas e de colunas
     *
     * @var array $num vetor com primeiro índice nomeado como 'rows' e o segundo índice como 'cols'
     */
    protected $num = [];

    /**
     * Método getN para retornar o número de linhas ou de colunas de uma matriz
     *
     * @param string $name palavra 'rows' para linhas ou 'cols' para colunas
     *
     * @since 1.0.0
     */
    public function getN($name)
    {
        $name = strtolower($name);
        switch($name) {
            case $name: return $this->num[$name];
               default: break;
        }
    }
	
    /**
     * Método abstrato get para retornar uma linha/coluna/elemento de uma matriz
     *
     * @param number $row valor numérico inteiro ou null
     * @param number $col valor numérico inteiro ou null
     *
     * @since 1.0.0
     */
    abstract public function get($row = [], $col = []);

    /**
     * Método abstrato set para especificar o valor de um elemento da matriz
     *
     * @param number $value valor numérico inteiro ou objeto do tipo Complex
     * @param number $row valor numérico inteiro
     * @param number $col valor numérico inteiro
     *
     * @since 1.0.0
     */
    abstract public function set($value, $row = [], $col = []);
	
    /**
     * Método abstrato add para realizar a soma entre matrizes
     *
     * @param Matrix $matrix objeto do tipo Matrix
     *
     * @since 1.0.0
     */
    abstract public function add($matrix);

    /**
     * Método abstrato multiply para realizar a multiplicação entre matrizes ou por um escalar
     *
     * @param Matrix $matrix objeto do tipo Matrix ou um número escalar
     *
     * @since 1.0.0
     */
    abstract public function multiply($matrix);
}