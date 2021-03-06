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
 * Bootstrap class for Zend_Application
 *
 * @uses Zend_Application_Bootstrap_Bootstrap
 * @copyright (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @author Josh Boyd <joshua.boyd@endeavorsystems.com>
 * @license http://www.openfisma.org/content/license GPLv3
 */
class Bootstrap extends Fisma_Zend_Application_Bootstrap_SymfonyContainerBootstrap
{
    /**
     * Register shutdown function
     *
     * @access protected
     * @return void
     */
    protected function _initShutdown()
    {
        $this->bootstrap('Session');
        register_shutdown_function(array("Zend_Session", "writeClose"), true);
    }

    /**
     * Initialize the error handler
     *
     * @access protected
     * @return void
     */
    protected function _initErrorHandler()
    {
        $errorHandler = create_function(
            '$code, $error, $file = NULL, $line = NULL', '
            if (error_reporting() & $code) {
                // This error is not suppressed by current error reporting settings
                // Convert the error into an ErrorException
                throw new ErrorException($error, $code, 0, $file, $line);
            }

            // Do not execute the PHP error handler
            return TRUE;'
        );

        set_error_handler($errorHandler);
    }

    /**
     * Initialize configuration
     *
     * @access protected
     * @return void
     */
    protected function _initConfiguration()
    {
        Fisma::setConfiguration(new Fisma_Configuration_Database());
    }

    /**
     * Initialize and connect to the database
     *
     * @access protected
     * @return void
     */
    protected function _initDb()
    {
        $db = Fisma::$appConf['db'];
        $connectString = $db['adapter']
                       . '://'
                       . $db['username']
                       . ':'
                       . $db['password']
                       . '@'
                       . $db['host']
                       . ($db['port'] ? ':' . $db['port'] : '')
                       . '/'
                       . $db['schema'];

        Doctrine_Manager::connection($connectString);
        $manager = Doctrine_Manager::getInstance();
        $manager->setAttribute(Doctrine::ATTR_USE_DQL_CALLBACKS, true);
        $manager->setAttribute(Doctrine::ATTR_USE_NATIVE_ENUM, true);
        $manager->setAttribute(Doctrine::ATTR_AUTOLOAD_TABLE_CLASSES, true);
        $manager->setAttribute(Doctrine::ATTR_VALIDATE, Doctrine::VALIDATE_ALL);

        $manager->registerValidators(
            array('Fisma_Doctrine_Validator_Ip', 'Fisma_Doctrine_Validator_Url', 'Fisma_Doctrine_Validator_Phone')
        );

        // Set globally on a Doctrine_Manager instance .
        $manager->setCollate('utf8_unicode_ci');
        $manager->setCharset('utf8');

        /**
         * Set up the cache driver and connect to the manager.
         * Make sure that we only cache in web app mode, and that the application is installed.
         **/
        if (function_exists('apc_fetch') && Fisma::mode() == Fisma::RUN_MODE_WEB_APP) {
            $cacheDriver = new Doctrine_Cache_Apc();
            $manager->setAttribute(Doctrine::ATTR_QUERY_CACHE, $cacheDriver);
        }

        Zend_Registry::set(
            'doctrine_config',
            array(
                'data_fixtures_path'  =>  Fisma::getPath('fixture'),
                'models_path'         =>  Fisma::getPath('model'),
                'migrations_path'     =>  Fisma::getPath('migration'),
                'yaml_schema_path'    =>  Fisma::getPath('schema'),
                'generate_models_options' => array(
                    'generateTableClasses' => true,
                    'baseClassName' => 'Fisma_Doctrine_Record'
                )
            )
        );
    }

    /**
     * Instantiate a search engine and save it in the registry
     *
     * @access protected
     * @return void
     */
    protected function _initSearchEngine()
    {
        $searchConfig = Fisma::$appConf['search'];

        $searchEngine = new Fisma_Search_Engine($searchConfig['host'], $searchConfig['port'], $searchConfig['path']);

        Zend_Registry::set('search_engine', $searchEngine);
    }

    /**
     * _initRegisterLogger
     *
     * @access protected
     * @return void
     */
    protected function _initRegisterLogger()
    {
        $this->bootstrap('Log');

        $logger = $this->getResource('Log');

        Zend_Registry::set('Zend_Log', $logger);
    }

    /**
     * _initHelperBroker
     *
     * @access protected
     * @return void
     */
    protected function _initHelperBroker()
    {
        Zend_Controller_Action_HelperBroker::addPrefix('Fisma_Zend_Controller_Action_Helper');
        Zend_Controller_Action_HelperBroker::addHelper(new Fisma_Zend_Controller_Action_Helper_ForcedPostRequest);
    }

    /**
     * Initialize the layout
     *
     * @access protected
     * @return void
     */
    protected function _initLayout()
    {
        Zend_Layout::startMvc(
            array(
                'layoutPath' => Fisma::getPath('layout'),
                'view' => new Fisma_Zend_View()
            )
        );
    }

    /**
     * Configure the view
     *
     * @access protected
     * @return void
     */
    protected function _initView()
    {
        // Configure the views
        $view = Zend_Layout::getMvcInstance()->getView();
        $view->addHelperPath(Fisma::getPath('viewHelper'), 'View_Helper_');
        $view->addScriptPath(Fisma::getPath('application') . '/modules/default/views/scripts');
        $view->setEncoding('UTF-8');
        $view->doctype('HTML5');
        // Make sure that we don't double encode
        $view->setEscape(array('Fisma', 'htmlentities'));
        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
        $viewRenderer->setView($view);
        $viewRenderer->setViewSuffix('phtml');
        Kint::enabled(Fisma::debug());
    }

    /**
     * Initialize the File Manager
     *
     * @access protected
     * @return void
     */
    protected function _initFileManager()
    {
        Zend_Registry::set(
            'fileManager',
            new Fisma_FileManager(Fisma::getPath('fileStorage'), new finfo(FILEINFO_MIME))
        );
    }

    /**
     * Instantiate a mail handler
     *
     * @access protected
     * @return void
     */
    protected function _initMailHandler()
    {
        $mailHandler = new Fisma_MailHandler_Queue();

        Zend_Registry::set('mail_handler', $mailHandler);
    }

    /**
     * Cached the mail templates
     */
    protected function _initMailTemplate()
    {
        Zend_Registry::set('mail_template', $this->getOption('mail_template'));
        Zend_Registry::set('mail_title', $this->getOption('mail_title'));
    }
}
