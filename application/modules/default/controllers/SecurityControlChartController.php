<?php
/**
 * Copyright (c) 2010 Endeavor Systems, Inc.
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
 * Generate charts for the security control catalog
 * 
 * @author     Mark E. Haase
 * @copyright  (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Controllers
 * @version    $Id$
 */
class SecurityControlChartController extends Fisma_Zend_Controller_Action_Security
{
    /**
     * Set contexts for this controller's actions
     */
    public function init()
    {
        parent::init();
        
        $this->_helper->fismaContextSwitch()
                      ->setActionContext('control-deficiencies', 'json')
                      ->initContext();
    }

    /**
     * Renders a bar chart that shows the number of open findings against each security control code.
     */
    public function controlDeficienciesAction()
    {
        $displayBy = urldecode($this->getRequest()->getParam('displaySecurityBy'));

        $rtnChart = new Fisma_Chart();
        $rtnChart
            ->setColors(array('#3366FF'))
            ->setChartType('bar')
            ->setConcatColumnLabels(false)
            ->setAxisLabelY('Number of Findings');
        
        // Dont query if there are no organizations this user can see
        $visibleOrgs = FindingTable::getOrganizationIds();
        if (empty($visibleOrgs)) {
            $this->view->chart = $rtnChart->export('array');
            return;
        }
        
        $deficienciesQuery = Doctrine_Query::create()
            ->select('COUNT(*) AS count, sc.code, SUBSTRING_INDEX(sc.code, "-", 1) fam')
            ->from('SecurityControl sc')
            ->innerJoin('sc.Findings f')
            ->innerJoin('f.ResponsibleOrganization o')
            ->andWhere('f.status <> ?', 'CLOSED')
            ->whereIn('o.id', FindingTable::getOrganizationIds())
            ->setHydrationMode(Doctrine::HYDRATE_SCALAR);

        if ($displayBy === 'Family') {
            $deficienciesQuery
                ->groupBy('fam')
                ->orderBy('fam');
        } else {
            $deficienciesQuery
                ->groupBy('sc.code')
                ->orderBy('sc.code');
        }

        $deficiencyQueryResult = $deficienciesQuery->execute();

        foreach ($deficiencyQueryResult as $thisElement) {
        
            if ($displayBy === 'Family') {
                $columnLabel = $thisElement['sc_fam'];
            } else {
                $columnLabel = $thisElement['sc_code'];
            }
        
            $rtnChart->addColumn(
                $columnLabel,
                $thisElement['sc_count']
            );
            
        }

        /* TODO:    Remove this when issue with search (zend vs Solr and quotes) is fixed 
                    jira.openfisma.org/browse/OFJ-1167?focusedCommentId=14101#action_14101
        */
        // Insert quotes around VALUE in securityControl/textContains/VALUE when using Solr
        $backend = Doctrine_Query::create()
            ->select('search_backend')
            ->from('Configuration')
            ->setHydrationMode(Doctrine::HYDRATE_ARRAY)
            ->execute();
        if ($backend[0]['search_backend'] === 'solr') {
            $searchVar = "%22#ColumnLabel#%22";
        } else {
            $searchVar = '#ColumnLabel#';
        }

        // Pass a string instead of an array to Fisma_Chart to set all columns to link with this URL-rule
        $rtnChart
            ->setLinks(
                '/finding/remediation/list/queryType/advanced' .
                '/denormalizedStatus/textDoesNotContain/CLOSED' .
                '/securityControl/textContains/'. $searchVar
            );
            
        // The context switch will convert this array to a JSON responce
        $this->view->chart = $rtnChart->export('array');
        
    }
}
