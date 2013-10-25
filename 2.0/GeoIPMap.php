<?php

/**
  *
 * GeoIPMap
 *
 * Copyright (c) 2012-2013, Christian Suenkel <info@suenkel.org>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * * Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in
 *   the documentation and/or other materials provided with the
 *   distribution.
 *
 * * Neither the name of Christian Suenkel nor the names of his
 *   contributors may be used to endorse or promote products derived
 *   from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Christian Suenkel <christian@suenkel.de>
 * @link http://plugin.suenkel.org
 * @copyright 2012-2013 Christian Suenkel <info@suenkel.de>
 * @license http://www.opensource.org/licenses/BSD-3-Clause The BSD 3-Clause License
 * @category Piwik_Plugins
 * @package  GeoIPMap
 */
namespace Piwik\Plugins\GeoIPMap;

use Piwik\Plugin;
use Piwik\Menu;
use Piwik\Menu\MenuMain;

/**
 * GeoIPMap - Plugin
 *
 * provides a visual interface (based on google-maps v3) to the GeoIP-plugin
 *
 * @see http://dev.piwik.org/trac/ticket/45
 */
class GeoIPMap extends Plugin
{

    /**
     * provide a List of Hooks to be registered in Piwik-eventhandling
     *
     * @return array
     */
    public function getListHooksRegistered()
    {
        // TODO: Check if Geolocation is enabled \Piwik\PluginsManager::getInstance()->isPluginActivated('LocationProvider');
        return array('Menu.Reporting.addItems' => 'addMenu', 
                'ArchiveProcessor.Day.compute' => 'archiveDay', 
                'ArchiveProcessor.Period.compute' => 'archivePeriod');
    }

    /**
     * Hook to add the Menues beneath the visitors-menue
     *
     * @return void
     */
    public function addMenu()
    {
        MenuMain::getInstance()->add('General_Visitors', 'Map', 
                array('module' => 'GeoIPMap', 'action' => 'index'), true, 15);
    }

    /**
     * Compute the aggregation of a period (week, mont, year) and archive the data
     *
     * @param Piwik\ArchiveProcessor\Day $archiveProcessing
     *            - the archive object
     * @return void
     */
    public function archiveDay(\Piwik\ArchiveProcessor\Day $archiveProcessor)
    {
        // FIXME: debug raus
        $archiving = new Archiver($archiveProcessor);
        if (true || $archiving->shouldArchive()) {
            $archiving->archiveDay();
        }
    }

    public function archivePeriod(\Piwik\ArchiveProcessor\Period $archiveProcessor)
    {
        // FIXME: debug raus
        $archiving = new Archiver($archiveProcessor);
        if (true || $archiving->shouldArchive()) {
            $archiving->archivePeriod();
        }
    }

    /**
     * Install the plugin
     * - create tables
     * - update existing tables
     * - etc.
     */
    public function install()
    {
        parent::install();
        $this->execSql(true);
    }

    /**
     * Remove the created resources during the install
     */
    public function uninstall()
    {
        $this->execSql();
        return parent::uninstall();
    }

    public function activate()
    {
        parent::activate();
        $this->execSql(true);
    }

    /**
     * Executed every time the plugin is disabled
     */
    public function deactivate()
    {
        $this->execSql();
        return parent::deactivate();
    }

    /**
     * execute install/deinstall SQL-prerequisits
     *
     * @param string $install            
     */
    protected function execSql($install = false)
    {
        include_once (__DIR__ . '/Updates/1_82.php');
        if ($install == true) {
            Piwik_GeoIPMap_1_82::update();
            return;
        }
        // Drop table (which is the first sql-statment)
        $sqlArr = Piwik_GeoIPMap_1_82::getSql();
        \Piwik\Updater::updateDatabase(__FILE__, array(key($sqlArr) => true));
        return;
    }
}

