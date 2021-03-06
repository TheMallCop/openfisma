<?php
/**
 * Copyright (c) 2012 Endeavor Systems, Inc.
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
 * A behavior which provides task on model instances
 *
 * @author     Ben Zheng
 * @copyright  (c) Endeavor Systems, Inc. 2012 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Fisma
 * @subpackage Fisma_Doctrine_Behavior_HasTasks
 */
class Fisma_Doctrine_Behavior_HasTasks extends Doctrine_Template
{
    /**
     * Overload constructor to plug in the record generator
     *
     * @param array $options The template options
     * @return void
     */
    public function __construct(array $options = array())
    {
        parent::__construct($options);

        $this->_plugin = new Fisma_Doctrine_Behavior_HasTasks_Generator();
    }

    /**
     * Define a relation to the generated task class
     *
     * @return void
     */
    public function setUp()
    {
        // The "component name" is the name of the class which is applying this behavior, so this will result in a
        // class name like 'FindingTask' or 'IncidentTask'
        $foreignClassName = $this->getTable()->getComponentName() . 'Task';

        $this->hasMany(
            $foreignClassName, 
            array(
                'local' => 'id',
                'foreign' => 'objectId'
            )
        );

        $this->_plugin->initialize($this->getTable());
    }

    /**
     * Return a task object
     *
     * @return Fisma_Doctrine_Behavior_HasTasks_Proxy The newly instantiated task instance
     */
    public function getTasks()
    {
        return new Fisma_Doctrine_Behavior_HasTasks_Proxy($this->getInvoker(), $this->_plugin);
    }
}
