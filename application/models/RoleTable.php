<?php
/**
 * Copyright (c) 2008 Endeavor Systems, Inc.
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
 * RoleTable
 *
 * @uses Fisma_Doctrine_Table
 * @package Model
 * @copyright (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @author Josh Boyd <joshua.boyd@endeavorsystems.com>
 * @license http://www.openfisma.org/content/license GPLv3
 */
class RoleTable extends Fisma_Doctrine_Table implements Fisma_Search_Searchable, Fisma_Search_Facetable
{
    /**
     * Implement the interface for Searchable
     */
    public function getSearchableFields()
    {
        return array (
            'name' => array(
                'initiallyVisible' => true,
                'label' => 'Name',
                'sortable' => true,
                'type' => 'text'
            ),
            'nickname' => array(
                'initiallyVisible' => true,
                'label' => 'Nickname',
                'sortable' => true,
                'type' => 'text',
                'formatter' => 'Fisma.TableFormat.recordLink',
                'formatterParameters' => array(
                    'prefix' => '/role/view/id/'
                )
            ),
            'type' => array(
                'initiallyVisible' => true,
                'label' => $this->getLogicalName('type'),
                'type' => 'enum',
                'enumValues' => $this->getEnumValues('type'),
                'sortable' => true

            ),
            'createdTs' => array(
                'initiallyVisible' => false,
                'label' => 'Creation Date',
                'sortable' => true,
                'type' => 'datetime'
            ),
            'modifiedTs' => array(
                'initiallyVisible' => false,
                'label' => 'Modified Date',
                'sortable' => true,
                'type' => 'datetime'
            ),
            'description' => array(
                'initiallyVisible' => true,
                'label' => 'Description',
                'sortable' => false,
                'type' => 'text'
            )
        );
    }

    /**
     * Returns an array of faceted filters
     *
     * @return array
     */
    public function getFacetedFields()
    {
        return array(
            array(
                'label' => 'Role Type',
                'column' => 'type',
                'filters' => array(
                    array(
                        'label' => 'Account Types',
                        'operator' => 'enumIs',
                        'operands' => array('ACCOUNT\_TYPE')
                    ),
                    array(
                        'label' => 'User Groups',
                        'operator' => 'enumIs',
                        'operands' => array('USER\_GROUP')
                    )
                )
            )
        );
    }

    /**
     * Implement required interface, but there is no field-level ACL in this model
     *
     * @return array
     */
    public function getAclFields()
    {
        return array();
    }

    /**
     * Construct a query to get all available roles
     *
     * @return Doctrine_Query
     */
    public function getAllRolesQuery()
    {
        return Doctrine_Query::create()
            ->from('Role r')
            ->orderBy('r.nickname');
    }
}
