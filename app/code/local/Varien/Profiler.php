<?php
/**
 * Magento Enterprise Edition
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Magento Enterprise Edition License
 * that is bundled with this package in the file LICENSE_EE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.magentocommerce.com/license/enterprise-edition
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Varien
 * @package     Varien_Profiler
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://www.magentocommerce.com/license/enterprise-edition
 */


class Varien_Profiler
{

    /**
     * Timers for code profiling
     *
     * @var array
     */
    static private $_timers = array();
    static private $_enabled = false;
    static private $_memory_get_usage = false;

    public static function enable()
    {
        self::$_enabled = true;
        self::$_memory_get_usage = function_exists('memory_get_usage');
    }

    public static function disable()
    {
        self::$_enabled = false;
    }

    public static function reset($timerName)
    {
        self::$_timers[$timerName] = array(
            'start'=>false,
            'count'=>0,
            'sum'=>0,
            'realmem'=>0,
            'emalloc'=>0,
        );
    }

    public static function resume($timerName)
    {
        if (!self::$_enabled) {
            return;
        }

        if (empty(self::$_timers[$timerName])) {
            self::reset($timerName);
        }
        if (self::$_memory_get_usage) {
            self::$_timers[$timerName]['realmem_start'] = memory_get_usage(true);
            self::$_timers[$timerName]['emalloc_start'] = memory_get_usage();
        }
        self::$_timers[$timerName]['start'] = microtime(true);
        self::$_timers[$timerName]['count'] ++;
    }

    public static function start($timerName)
    {
        self::resume($timerName);
    }

    public static function pause($timerName)
    {
        if (!self::$_enabled) {
            return;
        }

        $time = microtime(true); // Get current time as quick as possible to make more accurate calculations

        if (empty(self::$_timers[$timerName])) {
            self::reset($timerName);
        }
        if (false!==self::$_timers[$timerName]['start']) {
            self::$_timers[$timerName]['sum'] += $time-self::$_timers[$timerName]['start'];
            self::$_timers[$timerName]['start'] = false;
            if (self::$_memory_get_usage) {
                self::$_timers[$timerName]['realmem'] += memory_get_usage(true)-self::$_timers[$timerName]['realmem_start'];
                self::$_timers[$timerName]['emalloc'] += memory_get_usage()-self::$_timers[$timerName]['emalloc_start'];
            }
        }
    }

    public static function stop($timerName)
    {
        self::pause($timerName);
    }

    public static function fetch($timerName, $key='sum')
    {
        if (empty(self::$_timers[$timerName])) {
            return false;
        } elseif (empty($key)) {
            return self::$_timers[$timerName];
        }
        switch ($key) {
            case 'sum':
                $sum = self::$_timers[$timerName]['sum'];
                if (self::$_timers[$timerName]['start']!==false) {
                    $sum += microtime(true)-self::$_timers[$timerName]['start'];
                }
                return $sum;

            case 'count':
                $count = self::$_timers[$timerName]['count'];
                return $count;

            case 'realmem':
                if (!isset(self::$_timers[$timerName]['realmem'])) {
                    self::$_timers[$timerName]['realmem'] = -1;
                }
                return self::$_timers[$timerName]['realmem'];

            case 'emalloc':
                if (!isset(self::$_timers[$timerName]['emalloc'])) {
                    self::$_timers[$timerName]['emalloc'] = -1;
                }
                return self::$_timers[$timerName]['emalloc'];

            default:
                if (!empty(self::$_timers[$timerName][$key])) {
                    return self::$_timers[$timerName][$key];
                }
        }
        return false;
    }

    public static function getTimers()
    {
        return self::$_timers;
    }

    /**
     * Output sql
     *
     * @static
     * @param Zend_Db_Profiler|Magento_Db_Adapter_Pdo_Mysql $res
     * @return string
     */
    public static function getSqlProfiler($res) {
        if(!$res){
            return '';
        }
        $out = '';
        $extra = '';
        /** @var $profiler Zend_Db_Profiler */
        $profiler = $res->getProfiler();

        if($profiler->getEnabled()) {
            $totalTime    = $profiler->getTotalElapsedSecs();
            $queryCount   = $profiler->getTotalNumQueries();
            $longestTime  = 0;
            $longestQuery = null;
            $longestCounter = 0;

            $extra = '';

            $extra .=     '<style type="text/css">
                        .queryInfoDetails {
                            border-collapse:collapse;
                            border-top:solid 3px #E1E6FA;
                            font-family:"Lucida Sans Unicode","Lucida Grande",Sans-Serif;
                            font-size:12px;
                            text-align:left;
                            width:680px!important;
                            text-align:left;
                            margin:30px auto;
                        }
                        .queryInfoDetails th {
                            color:#003399;
                            font-size:14px;
                            font-weight:normal;
                            padding:5px;
                            border-top:1px solid #E8EDFF;
                        }

                        .queryInfoDetails tr:hover, .queryInfoDetails tr:hover td {
                            background:#EFF2FF none repeat scroll 0 0;
                            color:#333399;
                        }

                        .queryInfoDetails td {
                            border-top:1px solid #E8EDFF;
                            color:#666699;
                            padding:5px;
                        }
                        .queryInfoDetails.mainInfoDetails {
                            border-bottom-style: solid;
                            border-color: #f66;
                            border-bottom-width: 3px;
                        }

                    </style>';
            $counter = 0;
            foreach ($profiler->getQueryProfiles() as $query) {

            /** @var $query Zend_Db_Profiler_Query */

            $queryParams = $query->getQueryParams();
            $params = 'none';
            if(!empty($queryParams)) { $params = print_r($queryParams,1); }

            $queryType = (int)$query->getQueryType();

            switch ($queryType) {
                case Zend_Db_Profiler::CONNECT:
                    $queryType = 'CONNECT';
                    break;
                case Zend_Db_Profiler::QUERY:
                    $queryType = 'QUERY';
                    break;
                case Zend_Db_Profiler::INSERT:
                    $queryType = 'INSERT';
                    break;
                case Zend_Db_Profiler::UPDATE:
                    $queryType = 'UPDATE';
                    break;
                case Zend_Db_Profiler::DELETE:
                    $queryType = 'DELETE';
                    break;
                case Zend_Db_Profiler::SELECT:
                    $queryType = 'SELECT';
                    break;
                case Zend_Db_Profiler::TRANSACTION:
                    $queryType = 'TRANSACTION';
                    break;
            }

            $extra .=     '<table class="queryInfoDetails" cellpadding="0" cellspacing="0">
                            <tr><th>Query no.</th><td>'.++$counter.'</td></tr>
                            <tr><th>Query type</th><td>'.$queryType.'</td></tr>
                            <tr><th>Query params</th><td>'.$params.'</td></tr>
                            <tr><th>Elapsed seconds</th><td>'.$query->getElapsedSecs().'</td></tr>
                            <tr><th>Raw query</th><td>'.wordwrap($query->getQuery()).'</td></tr>
                        </table>';

            if ($query->getElapsedSecs() > $longestTime) {
                $longestTime  = $query->getElapsedSecs();
                $longestQuery = $query->getQuery();
                $longestCounter = $counter;
            }
            }

            $out .= '<table class="queryInfoDetails mainInfoDetails" cellpadding="0" cellspacing="0">';
            $out .= '<tr><th>Executed queries</th><td>' . $queryCount . '</td></tr>';
            $out .= '<tr><th>Total time (seconds)</th><td>' . $totalTime . '</td></tr>';
            $out .= '<tr><th>Average query length (seconds)</th><td>' . $totalTime / $queryCount . '</td></tr>';
            $out .= '<tr><th>Queries per second</th><td>' . $queryCount / $totalTime . '</td></tr>';
            $out .= '<tr><th>Longest query length</th><td>' . $longestTime . '</td></tr>';
            $out .= '<tr><th>Longest query # '.$longestCounter.'</th><td>' . wordwrap($longestQuery) . '</td></tr>';
            $out .= '</table>';
        }

        $out .= $extra;

        return $out;
    }
}
