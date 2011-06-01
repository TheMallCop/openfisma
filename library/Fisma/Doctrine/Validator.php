<?php
/**
 * Copyright (c) 2011 Endeavor Systems, Inc.
 *
 * This file is part of OpenFISMA.
 *
 * OpenFISMA is free software: you can redistribute it and/or modify it under the terms of the GNU General Public 
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * OpenFISMA is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied 
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more 
 * details.
 *
 * You should have received a copy of the GNU General Public License along with OpenFISMA.  If not, see 
 * {@link http://www.gnu.org/licenses/}.
 */

/**
 * A subclass of Doctrine_Validator which overrite parent getValidator function with suppressing 
 * the warning message of fileNotFound generated by class_exists() 
 * 
 * @uses Doctrine_Validator
 * @package    Fisma
 * @subpackage Fisma_Doctrine
 * @copyright  (c) Endeavor Systems, Inc. 2011 {@link http://www.endeavorsystems.com}
 * @author     Mark Ma <mark.ma@reyosoft.com>
 * @license    http://www.openfisma.org/content/license GPLv3
 */
class Fisma_Doctrine_Validator extends Doctrine_Validator
{
    /**
     * @var array $validators       an array of validator objects
     */
    private static $_validators = array();

    /**
     * Get a validator instance for the passed $name, suppress the warning message 
     * of fileNotFound generated by class_exists() 
     *
     * @param  string   $name  Name of the validator or the validator class name
     * @return Doctrine_Validator_Interface $validator
     */
    public static function getValidator($name)
    {
        if ( ! isset(self::$_validators[$name])) {
            $class = 'Doctrine_Validator_' . ucwords(strtolower($name));
            if (@class_exists($class)) {
                self::$_validators[$name] = new $class;
            } else if (@class_exists($name)) {
                self::$_validators[$name] = new $name;
            } else {
                throw new Doctrine_Exception("Validator named '$name' not available.");
            }

        }
        return self::$_validators[$name];
    }

}
