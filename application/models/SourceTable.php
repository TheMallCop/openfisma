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
 * SourceTable
 *
 * @uses Fisma_Doctrine_Table
 * @package Model
 * @copyright (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @author Josh Boyd <joshua.boyd@endeavorsystems.com>
 * @license http://www.openfisma.org/content/license GPLv3
 */
class SourceTable extends Fisma_Doctrine_Table implements Fisma_Search_Searchable
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
                'type' => 'text',
                'formatter' => 'Fisma.TableFormat.recordLink',
                'formatterParameters' => array(
                    'prefix' => '/finding/source/view/id/'
                )
            ),
            'nickname' => array(
                'initiallyVisible' => true,
                'label' => 'Nickname',
                'sortable' => true,
                'type' => 'text'
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
     * Implement required interface, but there is no field-level ACL in this model
     *
     * @return array
     */
    public function getAclFields()
    {
        return array();
    }

    /**
     * Get sources
     *
     * @param Doctrine_Query $sourceQuery Optional, default to getSourcesQuery()
     * @return Doctrine_Collection The collection of sources
     * @deprecated pending on the removal of execution out of model classes
     */
    public function getSources($sourceQuery = null)
    {
        $sourceQuery = (isset($sourceQuery)) ? $sourceQuery : $this->getSourcesQuery();
        return $sourceQuery->execute();
    }

    /**
     * Build query for getSources()
     *
     * @return Doctrine_Query
     */
    public function getSourcesQuery()
    {
        $sourceQuery = Doctrine_Query::create()
                       ->from('Source s')
                       ->orderBy('s.nickname');

        return $sourceQuery;
    }
}
