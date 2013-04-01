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
 * SecurityControlCatalogTable
 *
 * @uses Fisma_Doctrine_Table
 * @package Model
 * @copyright (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @author Josh Boyd <joshua.boyd@endeavorsystems.com>
 * @license http://www.openfisma.org/content/license GPLv3
 */
class SecurityControlCatalogTable extends Fisma_Doctrine_Table implements Fisma_Search_Searchable
{
    /**
     * Get an array of catalogs by id and name, in a format that is compatible with
     * Zend_Form_Element_Select#addMultiOptions()
     *
     * @return array
     * @deprecated  pending the replacement with getCatalogsQuery()
     */
    public function getCatalogs($catalogQuery = null)
    {
        $catalogQuery = (isset($catalogQuery)) ? $catalogQuery : self::getCatalogsQuery();
        return $catalogQuery->execute();
    }

    /**
     * Build the query for getCatalogs()
     *
     * @return Doctrine_Query
     */
    public function getCatalogsQuery()
    {
        // Get data for the select element. Columns are aliased as 'key' and 'value' for addMultiOptions().
        $catalogQuery = Doctrine_Query::create()
                      ->select('id AS key, name AS value')
                      ->from('SecurityControlCatalog')
                      ->orderBy('name')
                      ->setHydrationMode(Doctrine::HYDRATE_ARRAY);
        return $catalogQuery;
    }

    /**
     * Implement the interface for Searchable
     */
    public function getSearchableFields()
    {
        return array (
            'name' => array(
                'initiallyVisible' => true,
                'sortable' => true,
                'type' => 'text',
                'formatter' => 'Fisma.TableFormat.recordLink',
                'formatterParameters' => array(
                    'prefix' => '/security-control-catalog/view/id/'
                )
            ),
            'published' => array(
                'initiallyVisible' => true,
                'sortable' => true,
                'type' => 'boolean'
            ),
            'description' => array(
                'initiallyVisible' => true,
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

}
