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
 * The index controller implements the default action when no specific request
 * is made.
 *
 * @author     Chris Chen <chriszero@users.sourceforge.net>
 * @copyright  (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @license    http://www.openfisma.org/content/license GPLv3
 * @package    Controller
 */
class IndexController extends Fisma_Zend_Controller_Action_Security
{
    /**
     * Set ajaxContect on analystAction and chartsAction
     */
    public function init()
    {
        $this->_helper->ajaxContext()
            ->addActionContext('notification', 'html')
            ->addActionContext('dismiss', 'json')
            ->initContext();
        parent::init();
    }

    /**
     * The default action - show the home page
     *
     * @GETAllowed
     * @return void
     */
    public function indexAction()
    {
        if ($this->_acl->hasArea('finding')) {
            $this->_redirect('/finding/dashboard');
        } elseif ($this->_acl->hasArea('incident')) {
            $this->_redirect('/incident-dashboard');
        } elseif ($this->_acl->hasArea('vulnerability')) {
            $this->_redirect('/vm/vulnerability/list');
        } elseif ($this->_acl->hasArea('system_inventory')) {
            $this->_redirect('/organization-dashboard');
        } else {
            throw new Fisma_Zend_Exception_User(
                'Your account does not have access to any dashboards. Please contact the'
                . ' administrator to correct your account privileges.'
            );
        }
    }

    /**
     * @GETAllowed
     */
    public function notificationAction()
    {
        if ($this->_me->Notifications->count() > 0) {
            $this->view->notifications = $this->_me->Notifications;
            $this->view->submitUrl = "javascript:Fisma.Util.formPostAction('', '/index/dismiss/', "
                                     . $this->_me->id . ')';
        }
    }

    /**
     * Delete user's notification.
     *
     * @return void
     */
    public function dismissAction()
    {
        $format = $this->getRequest()->getParam('format');
        if ($format == 'json') {
            $jsonResponse = new Fisma_AsyncResponse;
        }
        try {
            $user = CurrentUser::getInstance();

            $user->Notifications->delete();
            $user->mostRecentNotifyTs = Fisma::now();
            $user->save();

            if ($jsonResponse) {
                // $jsonResponse->succeed();
            }
        } catch (Exception $e) {
            $jsonResponse->fail($e->getMessage());
        }
    }

    /**
     * Go to User's home page
     *
     * @GETAllowed
     */
    public function homeAction()
    {
        if ($user = CurrentUser::getInstance()) {
            $this->_redirect($user->homeUrl);
        } else {
            $this->_redirect('/auth/login');
        }
    }
}
