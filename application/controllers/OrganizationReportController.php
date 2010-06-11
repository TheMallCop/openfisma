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
 * Reports for organizations (in the system inventory module)
 * 
 * @author     Mark E. Haase
 * @copyright  (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Controllers
 * @version    $Id$
 */
class OrganizationReportController extends SecurityController
{
    /**
     * Check that the user has the privilege to run reports
     */
    public function preDispatch()
    {
        Fisma_Zend_Acl::requireArea('system_inventory_report');

        $this->_helper->reportContextSwitch()
                      ->htmlActionStack('header', 'panel')
                      ->addActionContext('test', array('html'))
                      ->addActionContext('personnel', array('html', 'pdf', 'xls'))
                      ->addActionContext('privacy', array('html', 'pdf', 'xls'))
                      ->addActionContext('security-authorization', array('html', 'pdf', 'xls'))
                      ->initContext();
    }
    
    /**
     * List ISO and ISSO personnel for each organization and system
     */
    public function personnelAction()
    {
        $personnelQuery = Doctrine_Query::create()
                          ->select('r.nickname, o.nickname, u.nameLast, u.nameFirst, u.phoneOffice, u.email')
                          ->from('Organization o')
                          ->leftJoin('o.UserRole ur')
                          ->leftJoin('ur.Role r')
                          ->leftJoin('ur.User u')
                          ->andWhere('o.orgType = ?', array('system'))
                          ->andWhere('r.nickname LIKE ? OR r.nickname LIKE ?', array('ISO', 'ISSO'))
                          ->orderBy('o.lft, o.rgt, u.nameLast, u.nameFirst')
                          ->setHydrationMode(Doctrine::HYDRATE_SCALAR);

        $personnel = $personnelQuery->execute();

        $report = new Fisma_Report();
        
        $report->setTitle('Personnel Report')
               ->addColumn(new Fisma_Report_Column('System', true))
               ->addColumn(new Fisma_Report_Column('Role', true))
               ->addColumn(new Fisma_Report_Column('Last Name', true))
               ->addColumn(new Fisma_Report_Column('First Name', true))
               ->addColumn(new Fisma_Report_Column('Office Phone', true))
               ->addColumn(new Fisma_Report_Column('E-mail', true))
               ->setData($personnel);

        $this->_helper->reportContextSwitch()->setReport($report);        
    }
    
    /**
     * List privacy status for all systems
     */
    public function privacyAction()
    {
        $systemQuery = Doctrine_Query::create()
                       ->select('bureau.nickname AS name')
                       ->addSelect('system.nickname AS name')
                       ->addSelect('systemData.hasPii AS has_pii')
                       ->addSelect('systemData.piaRequired AS pia_required')
                       ->addSelect(
                           'IF(\'YES\' = systemData.piaRequired, 
                               IF(systemData.piaUrl IS NULL, \'NO\', \'YES\'),
                               \'N/A\') AS pia_url'
                       )
                       ->addSelect('systemData.sornRequired AS sorn_required')
                       ->addSelect(
                           'IF(\'YES\' = systemData.piaRequired, 
                               IF(systemData.sornUrl IS NULL, \'NO\', \'YES\'),
                               \'N/A\') AS sorn_url'
                       )
                       ->from('Organization system')
                       ->innerJoin('system.System systemData')
                       ->leftJoin('Organization bureau')
                       ->where('system.orgType = ?', array('system'))
                       ->andWhere('bureau.orgType = ?', array('bureau'))
                       ->andWhere('system.lft BETWEEN bureau.lft and bureau.rgt')
                       ->orderBy('bureau.nickname, system.nickname')
                       ->setHydrationMode(Doctrine::HYDRATE_SCALAR);

        $systems = $systemQuery->execute();

        $report = new Fisma_Report();
                
        $report->setTitle('Privacy Report')
               ->addColumn(new Fisma_Report_Column('Bureau', true))
               ->addColumn(new Fisma_Report_Column('System', true))
               ->addColumn(new Fisma_Report_Column('Contains PII', true))
               ->addColumn(new Fisma_Report_Column('PIA Required', true))
               ->addColumn(new Fisma_Report_Column('PIA Completed', true, 'Fisma.TableFormat.yesNo'))
               ->addColumn(new Fisma_Report_Column('SORN Required', true))
               ->addColumn(new Fisma_Report_Column('SORN Completed', true, 'Fisma.TableFormat.yesNo'))
               ->setData($systems);

        $this->_helper->reportContextSwitch()->setReport($report);        
    }
    
    /**
     * List security authorization status for all systems
     */
    public function securityAuthorizationAction()
    {
        $systemQuery = Doctrine_Query::create()
                       ->select('bureau.nickname AS name')
                       ->addSelect('system.nickname AS name')
                       ->addSelect('IFNULL(systemData.fipsCategory, \'NONE\') AS fips_category')
                       ->addSelect('IFNULL(systemData.controlledBy, \'N/A\') AS operated_by')
                       ->addSelect('IFNULL(systemData.securityAuthorizationDt, \'N/A\') AS security_auth_dt')
                       ->addSelect('IFNULL(systemData.controlAssessmentDt, \'N/A\') AS self_assessment_dt')
                       ->addSelect('IFNULL(systemData.contingencyPlanTestDt, \'N/A\') AS cplan_test_dt')
                       ->from('Organization system')
                       ->innerJoin('system.System systemData')
                       ->leftJoin('Organization bureau')
                       ->where('system.orgType = ?', array('system'))
                       ->andWhere('bureau.orgType = ?', array('bureau'))
                       ->andWhere('system.lft BETWEEN bureau.lft and bureau.rgt')
                       ->orderBy('bureau.nickname, system.nickname')
                       ->setHydrationMode(Doctrine::HYDRATE_SCALAR);

        $systems = $systemQuery->execute();

        $report = new Fisma_Report();
                
        $report->setTitle('Security Authorizations Report')
               ->addColumn(new Fisma_Report_Column('Bureau', true))
               ->addColumn(new Fisma_Report_Column('System', true))
               ->addColumn(new Fisma_Report_Column('FIPS 199', true))
               ->addColumn(new Fisma_Report_Column('Operated By', true))
               ->addColumn(
                   new Fisma_Report_Column(
                       'Security Authorization', 
                       true, 
                       'Fisma.TableFormat.securityAuthorization'
                   )
               )
               ->addColumn(
                   new Fisma_Report_Column(
                       'Self-Assessment', 
                       true,
                       'Fisma.TableFormat.selfAssessment'
                   )
               )
               ->addColumn(
                   new Fisma_Report_Column(
                       'Contingency Plan Test', 
                       true,
                       'Fisma.TableFormat.contingencyPlanTest'
                   )
               )
               ->setData($systems);

        $this->_helper->reportContextSwitch()->setReport($report);        
    }    
}
