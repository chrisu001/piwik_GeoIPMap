<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://www.suenkel.de
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id$
 *
 * @category
 * @package Updates
 */

/**
 * @package Updates
 */



class Piwik_GeoIPMap_1_8 // extends Piwik_Updates
{

    // TODO: use Countries.php
    static function getSql($schema = 'Myisam')
    {
        $table = Piwik_Common::prefixTable('continentcountry');

        $sql = array(
                "   DROP TABLE IF EXISTS $table" => true,
                "
                CREATE TABLE $table (
                country char(3) NOT NULL,
                continent char(3) NOT NULL,
                PRIMARY KEY ( country ))
                " => true);

        include(PIWIK_USER_PATH . '/core/DataFiles/Countries.php');

        foreach($GLOBALS['Piwik_CountryList'] as $country => $continent) {
            $insertSQL=sprintf("insert into %s values('%s','%s')",$table,$country,$continent);
            $sql[$insertSQL] = true;
        }
        return $sql;
    }

    static function update()
    {
        try
        {
            Piwik_Updater::updateDatabase(__FILE__, self::getSql());
        }
        catch (Exception $e)
        {
            throw new RuntimeException('update failed',0, $e);
        }
    }
}
