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
 * @package  Piwik_GeoIPMap
 */
namespace Piwik\Plugins\GeoIPMap;

use Piwik\Config;
use Piwik\Common;
use Piwik\DataTable\Manager;
use Piwik\DataTable\Row\DataTableSummaryRow;
use Piwik\Metrics;
use Piwik\DataTable;


/**
 * Archiver
 */

/**
 * Class encapsulating logic to process Day/Period Archiving for the Geolocation Reports
 *
 * @package Actions
 */
class Archiver extends \Piwik\Plugin\Archiver
{
    
    /**
     * Archive the most active 100 Cities
     *
     * @var int
     */
    const MAX_ROWS_TOARCHIVE = 100;
    
    /**
     * Recordname to be used in Blob-Table-Aggregates
     *
     * @var string
     */
    const RECORD_NAME = 'GeoIPMap_cities';
    
    /**
     * Compute the statistics of a day
     *
     * @return void
     */
    public function archivePeriod()
    {
        $this->getProcessor()
            ->aggregateDataTableReports(self::RECORD_NAME, null, null, Metrics::INDEX_NB_UNIQ_VISITORS);
    }
    
    /**
     * Compute the statistics of a day
     * generate daily stats based on GeoIP-Data (nb_visits and nb_uniq-visitors)
     *
     * @return void
     */
    public function archiveDay()
    {
        $maximumRowsInDataTable = $this->getQueryLimit();
        $mapTable = Common::prefixTable('continentcountry');
        
        $select = "
                continent as location_continent,
                location_country,
                location_city,
                location_latitude,
                location_longitude,
                count(distinct idvisitor) as `" . Metrics::INDEX_NB_UNIQ_VISITORS . "`,
                count(*)                  as `" . Metrics::INDEX_NB_VISITS . "`";
        
        $from = array(
                "log_visit",
                array(
                        "table" => "continentcountry",
                        "joinOn" => "location_country = country"));
        
        $where = "
                visit_last_action_time >= ?
                AND visit_last_action_time <= ?
                AND idsite = ?
				AND not location_latitude is null";
        
        $groupBy = "continent, location_country, location_city, location_latitude, location_longitude";
        
        $orderBy = "`" . Metrics::INDEX_NB_UNIQ_VISITORS . "` DESC
				LIMIT $maximumRowsInDataTable";
        
        
        $query = $this->getLogAggregator()
            ->generateQuery($select, $from, $where, $groupBy, $orderBy);
        
        $resultSet = $this->getLogAggregator()
            ->getDb()
            ->query($query['sql'], $query['bind']);
        
        /**
         * convert the result of the database query to piwik-archive-table
         */
        $table = new \Piwik\DataTable();
        while ($row = $resultSet->fetch()) {
            $r = new \Piwik\DataTable\Row(array(
                    \Piwik\DataTable\Row::COLUMNS => array(
                            'label' => $row['location_latitude'] . '|' . $row['location_longitude'],
                            Metrics::INDEX_NB_UNIQ_VISITORS => $row[Metrics::INDEX_NB_UNIQ_VISITORS],
                            Metrics::INDEX_NB_VISITS => $row[Metrics::INDEX_NB_VISITS]),
                    \Piwik\DataTable\Row::METADATA => array(
                            'location_continent' => $row['location_continent'],
                            'location_country' => $row['location_country'],
                            'location_city' => $row['location_city'])));
            $table->addRow($r);
        }
        
        $blob = $table->getSerialized();
        Common::destroy($table);
        $this->getProcessor()
            ->insertBlobRecord(self::RECORD_NAME, $blob);
        Common::destroy($blob);
    }
    
    /**
     * Fence the query limit of the most active cities
     * between 5 and $this->maximumRows, self::MAX_ROWS_TOARCHIVE
     *
     * @return integer
     */
    protected function getQueryLimit()
    {
        return max(5, min($this->maximumRows, self::MAX_ROWS_TOARCHIVE));
    }
}
