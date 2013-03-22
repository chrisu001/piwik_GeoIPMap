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
 * Controller-class
 *
 * provides the generation and rendering of html-reports
 * @see Piwik_GeoIPMap::addMenus()
 */
class Piwik_GeoIPMap_Controller extends Piwik_Controller {

	/**
	 * Period to be used by rendering of reports
	 * @var string - store the requested period
	 */
	protected $_period;
	/**
	 * Filter the statistics by continent
	 * @var string -
	 */
	protected $_filterContinent;
	/**
	 * Limit the rows of ajaxData-request
	 * @var int
	 */
	protected $_limit;
	/**
	 * Set the disaplaymode [report|live]
	 * @var string
	 */
	protected $_displayMode;



	/**
	 * Constructor
	 * stores QUERY_STRING paramters for further processing
	 * @param void
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->_period = Piwik_Common::getRequestVar('period', 'day', 'string');
		$this->_filterContinent = Piwik_Common::getRequestVar('continent', '', 'string');
		$this->_displayMode = Piwik_Common::getRequestVar('dmode', 'report', 'string');
		$this->_limit = Piwik_Common::getRequestVar('limit', 20, 'integer');
		if ($this->_displayMode == 'live'
		or !in_array($this->_filterContinent, array('eur', 'afr', 'amn', 'ams', 'amc', 'asi','oce')))
		$this->_filterContinent = '';
	}

	/**
	 * Index-Page
	 * render the Iframe
	 * @param void
	 * @return void
	 */
	public function index() {
		$view = Piwik_View::factory('index');
		$view->innerUrl = 'index.php?module=GeoIPMap&action=innerFrame' . $this->_querystring();
		print $view->render();
	}

	/**
	 * Display live-data at start
	 * @param void
	 * @return void
	 */
	public function live() {
		$this->_displayMode = 'live';
		return $this->index();
	}

	/**
	 * "the page" itself
	 * @param void
	 * @return void
	 */
	public function innerFrame() {
		$view = Piwik_View::factory('innerFrame');
		$view->ajaxurl = 'index.php?module=GeoIPMap&action=ajaxData' . $this->_querystring();
		print $view->render();
	}

	/**
	 * Ajax interface for the XHR provides the statistics data gathered
	 * from the APInterface
	 * @param void
	 * @return void
	 */
	public function ajaxData() {

		Piwik::checkUserHasViewAccess($this->idSite);

		$locations = array();
		$currentTimezone = null;


		if ($this->_displayMode != 'live') {
			$lastts = Piwik_Common::getRequestVar('timestamp', 0, 'integer');
			$url = sprintf('method=GeoIPMap.getVisits&limit=50&format=php&serialize=0&disable_generic_filters=1&timestamp=%d%s',
			$lastts,
			$this->_querystring());
		} else {
			$currentSite = new Piwik_Site($this->idSite);
			$currentTimezone = $currentSite->getTimezone();
			$url = sprintf('method=GeoIPMap.getLiveVisits&limit=50&format=php&serialize=0&disable_generic_filters=1%s',
			$this->_querystring());
		}
		$api = new Piwik_API_Request($url);
		$visits = $api->process();

		// printf('<pre>%s</pre><br>', print_r($visits, true));
		$ts = time();
		// Transform the api-data to json-XHR
		foreach ($visits as $city) {
		    
		    // skip old data
		    if(empty($city['location_continent'])) continue; 
		    
		    
			if (!empty($this->_filterContinent) && $this->_filterContinent != $city['location_continent'])
			continue;
			if ($this->_limit <= 0)
			break;
			$this->_limit--;
			$locations[] = array(
                'latitude' => $city['location_latitude'],
                'longitude' => $city['location_longitude'],
                'title' => sprintf('%s (%s) %s', htmlspecialchars($city['location_city']),
			htmlspecialchars($city['location_country']),
			!empty($city['visit_last_action_time']) ? $city['visit_last_action_time'] :
			$city[Piwik_Archive::INDEX_NB_UNIQ_VISITORS] . '/' .
			$city[Piwik_Archive::INDEX_NB_VISITS]),
                'html' => sprintf('%s / <b>%s</b><br/>%s%d %s<br/>%d %s<br/>',
			$city['location_country'],
			$city['location_city'],
			empty($city['visit_last_action_time']) ? '' : 'Last: ' . $city['visit_last_action_time'] . "<br/>",
			$city[Piwik_Archive::INDEX_NB_UNIQ_VISITORS], Piwik_Translate('General_ColumnNbUniqVisitors'),
			$city[Piwik_Archive::INDEX_NB_VISITS], Piwik_Translate('General_ColumnNbVisits'))
			);
			if ($currentTimezone and !empty($city['visit_last_action_time'])) {
				$processedDate = Piwik_Date::factory($city['visit_last_action_time'], $currentTimezone);
				$ts = max($ts, $processedDate->getTimestampUTC());
			}
		}


		print json_encode(array('success' => true, 'timestamp' => $ts, 'data' => $locations));
	}

	/**
	 * Generate the default-part of the query_string to be used in api/html-requests
	 * @param bool $token
	 * @return string - concatenated query-string
	 */
	protected function _querystring($useToken = false) {
		return sprintf('&idSite=%d&period=%s&date=%s%s',
		$this->idSite, $this->_period, $this->strDate,
		$useToken ? '&token_auth=' . Piwik::getCurrentUserTokenAuth() : ''
		);
	}

	/**
	 * Converts a country-code to the url-location-path of the coresponding
	 * country-flag
	 * @param string $code - country-code (two chars) of the
	 * @return string - path tho the country-flag icon
	 */
	protected function getFlagFromCode($code) {
		$pathInPiwik = 'plugins/UserCountry/flags/%s.png';
		$pathWithCode = sprintf($pathInPiwik, $code);
		$absolutePath = PIWIK_INCLUDE_PATH . '/' . $pathWithCode;
		if (file_exists($absolutePath)) {
			return $pathWithCode;
		}
		return sprintf($pathInPiwik, 'xx');
	}

}
