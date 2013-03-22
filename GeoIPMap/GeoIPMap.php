<?php

/**
 * Piwik - Open source web analytics
 *
 * @link http://www.suenkel.de/
 * @version $Id$
 * @author Christian Suenkel
 *
 * The GeoIPMap-plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * The GeoIPMap-plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GeoIPMap.  If not, see <http://www.gnu.org/licenses/>.
 * The GeoIPMap-Plugin is free software: you can redistribute it and/or modify
 *
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * GeoIPMap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GeoIPMap.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category Piwik_Plugins
 * @package Piwik_GeoIPMap
 */

/**
 *  GeoIPMap - Plugin
 *
 *  provides a visual interface (based on google-maps v3) to the GeoIP-plugin
 *  @see http://dev.piwik.org/trac/ticket/45
 */
class Piwik_GeoIPMap extends Piwik_Plugin {
    /**
     * @var int - maximum of rows to be stored in the archive
     */
    const MAX_ROWS_TOARCHIVE = 51;

    /**
     * provide information about the plugin
     * @param void
     * @return array
     */
    public function getInformation() {
         
        return array(
                'name'            => 'GeoIPMap',
                'description'     => 'Display the location of your visitors in GoogleMaps/OpenStreetMaps',
                'author'          => 'Christian Suenkel',
                'homepage'        => 'http://plugin.suenkel.org/',
                'author_homepage' => 'http://www.suenkel.de/blog/index/2012/02/update-piwik-1-7-geoipmap-mapping-visitor-data-to-google-maps-and-open-street-map/',
                'version'         => '1.9.1',
                'TrackerPlugin'   => false,
        );
    }

    /**
     * provide a List of Hooks to be registered in Piwik-eventhandling
     * @param void
     * @return array
     */
    public function getListHooksRegistered() {
        return array(
                'Menu.add' => 'addMenus',
                'ArchiveProcessing_Day.compute' => 'archiveDay',
                'ArchiveProcessing_Period.compute' => 'archivePeriod',
                'API.getReportMetadata' => 'getReportMetadata',
        );
    }

    /**
     * Hook to add the Menues beneath the visitors-menue
     * @param void
     * @return void
     */
    public function addMenus() {
        Piwik_AddMenu('General_Visitors',
        'Map', array('module' => 'GeoIPMap', 'action' => 'index'),
        true, 15);
    }

    /**
     * Registers reports metadata information
     * @param Piwik_Event_Notification $notification - the object of interes
     * @return void
     */
    public function getReportMetadata($notification) {
        $reports = &$notification->getNotificationObject();
        $reports[] = array(
                'category' => Piwik_Translate('General_Visitors'),
                'name' => Piwik_Translate('UserCountry_Country'),
                'module' => 'GeoIPMap',
                'action' => 'index',
                'dimension' => Piwik_Translate('UserCountry_Country')
        );
    }

    /**
     * Compute the aggregation of a period (week, mont, year) and archive the data
     *
     * @param Piwik_ArchiveProcessing $notification - the object of interes
     * @return void
     */
    public function archivePeriod($notification) {
        $archiveProcessing = $notification->getNotificationObject();
        if (!$archiveProcessing->shouldProcessReportsForPlugin($this->getPluginName())){
            return;
        }

        $maximumRowsInDataTable = Zend_Registry::get('config')->General->datatable_archiving_maximum_rows_standard;
        $maximumRowsInDataTable = min(self::MAX_ROWS_TOARCHIVE, $maximumRowsInDataTable);

        $archiveProcessing->archiveDataTable(array('GeoIPMap_mapv2'),
                array(Piwik_Archive::INDEX_SUM_DAILY_NB_UNIQ_VISITORS => Piwik_Archive::INDEX_NB_UNIQ_VISITORS),
                $maximumRowsInDataTable, 0, Piwik_Archive::INDEX_NB_UNIQ_VISITORS);
    }

     
    /**
     * Compute the statistics of a day
     *
     * @param Piwik_ArchiveProcessing $notification - the object of interes
     * @return void
     */
    public function archiveDay($notification) {

        $archiveProcessing = $notification->getNotificationObject();
         
        if (!$archiveProcessing->shouldProcessReportsForPlugin($this->getPluginName())){
            return;
        }

        $maximumRowsInDataTable = Zend_Registry::get('config')->General->datatable_archiving_maximum_rows_standard;
        $maximumRowsInDataTable = min(self::MAX_ROWS_TOARCHIVE, $maximumRowsInDataTable);


        /*
         * generate daily stats based on GeoIP-Data
        * (nb_visits and nb_uniq-visitors)
        */

        $mapTable = Piwik_Common::prefixTable('continentcountry');
        $select = "
                SELECT continent as location_continent,
                location_country,
                location_city,
                location_latitude,
                location_longitude,
                count(distinct idvisitor) as `" . Piwik_Archive::INDEX_NB_UNIQ_VISITORS . "`,
                        count(*)                  as `" . Piwik_Archive::INDEX_NB_VISITS . "`";

        $from = "
                FROM " .Piwik_Common::prefixTable('log_visit'). ", $mapTable";

        $where = "
                WHERE visit_last_action_time >= ?
                AND visit_last_action_time <= ?
                AND not location_latitude is null
                AND idsite = ?
                AND location_country = country";
        $group = "
                GROUP BY continent, location_country, location_city, location_latitude, location_longitude";
        $order = "
                ORDER BY `" . Piwik_Archive::INDEX_NB_UNIQ_VISITORS . "` DESC
                LIMIT $maximumRowsInDataTable";

        $sql = $select.$from.$where.$group.$order;
        $sqlBind = array(
                $archiveProcessing->getStartDatetimeUTC(),
                $archiveProcessing->getEndDatetimeUTC(),
                $archiveProcessing->idsite);


        $query = $archiveProcessing->db->query($sql,$sqlBind);

        /**
         * convert the result of the database query to piwik-archive-table
        */
        $table = new Piwik_DataTable();
        while ($row = $query->fetch()) {
            $r = new Piwik_DataTable_Row(array(
                    Piwik_DataTable_Row::COLUMNS => array(
                            'label' => $row['location_latitude'] . '|' . $row['location_longitude'],
                            Piwik_Archive::INDEX_NB_UNIQ_VISITORS => $row[Piwik_Archive::INDEX_NB_UNIQ_VISITORS],
                            Piwik_Archive::INDEX_NB_VISITS => $row[Piwik_Archive::INDEX_NB_VISITS],
                    ),
                    Piwik_DataTable_Row::METADATA => array(
                            'location_continent' => $row['location_continent'],
                            'location_country' => $row['location_country'],
                            'location_city' => $row['location_city'])
            ));

            $table->addRow($r);
        }
        $archiveProcessing->insertBlobRecord('GeoIPMap_mapv2', $table->getSerialized());
        destroy($table);
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
    public function activate(){
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
    protected function execSql($install = false) {

        require_once (__DIR__.'/Updates/1_8.php');
        if($install == true) {
            Piwik_GeoIPMap_1_8::update();
            return;
        }
        $sqlArr=Piwik_GeoIPMap_1_8::getSql();
        Piwik_Updater::updateDatabase(__FILE__, array(key($sqlArr) => true));
        return;
    }
}

