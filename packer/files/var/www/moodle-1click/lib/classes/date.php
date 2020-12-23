<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Core date and time related code.
 *
 * @package   core
 * @copyright 2015 Totara Learning Solutions Ltd {@link http://www.totaralms.com/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Petr Skoda <petr.skoda@totaralms.com>
 */

/**
 * Core date and time related code.
 *
 * @since Moodle 2.9
 * @package   core
 * @copyright 2015 Totara Learning Solutions Ltd {@link http://www.totaralms.com/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Petr Skoda <petr.skoda@totaralms.com>
 */
class core_date {
    /** @var array list of recommended zones */
    protected static $goodzones = null;

    /** @var array list of BC zones supported by PHP */
    protected static $bczones = null;

    /** @var array mapping of timezones not supported by PHP */
    protected static $badzones = null;

    /** @var string the default PHP timezone right after config.php */
    protected static $defaultphptimezone = null;

    /**
     * Returns a localised list of timezones.
     * @param string $currentvalue
     * @param bool $include99 should the server timezone info be included?
     * @return array
     */
    public static function get_list_of_timezones($currentvalue = null, $include99 = false) {
        self::init_zones();

        // Localise first.
        $timezones = array();
        foreach (self::$goodzones as $tzkey => $ignored) {
            $timezones[$tzkey] = self::get_localised_timezone($tzkey);
        }
        core_collator::asort($timezones);

        // Add '99' if requested.
        if ($include99 or $currentvalue == 99) {
            $timezones['99'] = self::get_localised_timezone('99');
        }

        if (!isset($currentvalue) or isset($timezones[$currentvalue])) {
            return $timezones;
        }

        if (is_numeric($currentvalue)) {
            // UTC offset.
            if ($currentvalue == 0) {
                $a = 'UTC';
            } else {
                $modifier = ($currentvalue > 0) ? '+' : '';
                $a = 'UTC' . $modifier . number_format($currentvalue, 1);
            }
            $timezones[$currentvalue] = get_string('timezoneinvalid', 'core_admin', $a);
        } else {
            // Some string we don't recognise.
            $timezones[$currentvalue] = get_string('timezoneinvalid', 'core_admin', $currentvalue);
        }

        return $timezones;
    }

    /**
     * Returns localised timezone name.
     * @param string $tz
     * @return string
     */
    public static function get_localised_timezone($tz) {
        if ($tz == 99) {
            $tz = self::get_server_timezone();
            $tz = self::get_localised_timezone($tz);
            return get_string('timezoneserver', 'core_admin', $tz);
        }

        if (get_string_manager()->string_exists(strtolower($tz), 'core_timezones')) {
            $tz = get_string(strtolower($tz), 'core_timezones');
        } else if ($tz === 'GMT' or $tz === 'Etc/GMT' or $tz === 'Etc/UTC') {
            $tz = 'UTC';
        } else if (preg_match('|^Etc/GMT([+-])([0-9]+)$|', $tz, $matches)) {
            $sign = $matches[1] === '+' ? '-' : '+';
            $tz = 'UTC' . $sign . $matches[2];
        }

        return $tz;
    }

    /**
     * Normalise the timezone name. If timezone not supported
     * this method falls back to server timezone (if valid)
     * or default PHP timezone.
     *
     * @param int|string|float|DateTimeZone $tz
     * @return string timezone compatible with PHP
     */
    public static function normalise_timezone($tz) {
        global $CFG;

        if ($tz instanceof DateTimeZone) {
            return $tz->getName();
        }

        self::init_zones();
        $tz = (string)$tz;

        if (isset(self::$goodzones[$tz]) or isset(self::$bczones[$tz])) {
            return $tz;
        }

        $fixed = false;
        if (isset(self::$badzones[$tz])) {
            // Convert to known zone.
            $tz = self::$badzones[$tz];
            $fixed = true;
        } else if (is_numeric($tz)) {
            // Half hour numeric offsets were already tested, try rounding to integers here.
            $roundedtz = (string)(int)$tz;
            if (isset(self::$badzones[$roundedtz])) {
                $tz = self::$badzones[$roundedtz];
                $fixed = true;
            }
        }

        if ($fixed and isset(self::$goodzones[$tz]) or isset(self::$bczones[$tz])) {
            return $tz;
        }

        // Is server timezone usable?
        if (isset($CFG->timezone) and !is_numeric($CFG->timezone)) {
            $result = @timezone_open($CFG->timezone); // Hide notices if invalid.
            if ($result !== false) {
                return $result->getName();
            }
        }

        // Bad luck, use the php.ini default or value set in config.php.
        return self::get_default_php_timezone();
    }

    /**
     * Returns server timezone.
     * @return string normalised timezone name compatible with PHP
     **/
    public static function get_server_timezone() {
        global $CFG;

        if (!isset($CFG->timezone) or $CFG->timezone == 99 or $CFG->timezone === '') {
            return self::get_default_php_timezone();
        }

        return self::normalise_timezone($CFG->timezone);
    }

    /**
     * Returns server timezone.
     * @return DateTimeZone
     **/
    public static function get_server_timezone_object() {
        $tz = self::get_server_timezone();
        return new DateTimeZone($tz);
    }

    /**
     * Set PHP default timezone to $CFG->timezone.
     */
    public static function set_default_server_timezone() {
        global $CFG;

        if (!isset($CFG->timezone) or $CFG->timezone == 99 or $CFG->timezone === '') {
            date_default_timezone_set(self::get_default_php_timezone());
            return;
        }

        $current = date_default_timezone_get();
        if ($current === $CFG->timezone) {
            // Nothing to do.
            return;
        }

        if (!isset(self::$goodzones)) {
            // For better performance try do do this without full tz init,
            // because this is called from lib/setup.php file on each page.
            $result = @timezone_open($CFG->timezone); // Ignore error if setting invalid.
            if ($result !== false) {
                date_default_timezone_set($result->getName());
                return;
            }
        }

        // Slow way is the last option.
        date_default_timezone_set(self::get_server_timezone());
    }

    /**
     * Returns user timezone.
     *
     * Ideally the parameter should be a real user record,
     * unfortunately the legacy code is using 99 for both server
     * and default value.
     *
     * Example of using legacy API:
     *    // Date for other user via legacy API.
     *    $datestr = userdate($time, core_date::get_user_timezone($user));
     *
     * The coding style rules in Moodle are moronic,
     * why cannot the parameter names have underscores in them?
     *
     * @param mixed $userorforcedtz user object or legacy forced timezone string or tz object
     * @return string normalised timezone name compatible with PHP
     */
    public static function get_user_timezone($userorforcedtz = null) {
        global $USER, $CFG;

        if ($userorforcedtz instanceof DateTimeZone) {
            return $userorforcedtz->getName();
        }

        if (isset($userorforcedtz) and !is_object($userorforcedtz) and $userorforcedtz != 99) {
            // Legacy code is forcing timezone in legacy API.
            return self::normalise_timezone($userorforcedtz);
        }

        if (isset($CFG->forcetimezone) and $CFG->forcetimezone != 99) {
            // Override any user timezone.
            return self::normalise_timezone($CFG->forcetimezone);
        }

        if ($userorforcedtz === null) {
            $tz = isset($USER->timezone) ? $USER->timezone : 99;

        } else if (is_object($userorforcedtz)) {
            $tz = isset($userorforcedtz->timezone) ? $userorforcedtz->timezone : 99;

        } else {
            if ($userorforcedtz == 99) {
                $tz = isset($USER->timezone) ? $USER->timezone : 99;
            } else {
                $tz = $userorforcedtz;
            }
        }

        if ($tz == 99) {
            return self::get_server_timezone();
        }

        return self::normalise_timezone($tz);
    }

    /**
     * Return user timezone object.
     *
     * @param mixed $userorforcedtz
     * @return DateTimeZone
     */
    public static function get_user_timezone_object($userorforcedtz = null) {
        $tz = self::get_user_timezone($userorforcedtz);
        return new DateTimeZone($tz);
    }

    /**
     * Return default timezone set in php.ini or config.php.
     * @return string normalised timezone compatible with PHP
     */
    public static function get_default_php_timezone() {
        if (!isset(self::$defaultphptimezone)) {
            // This should not happen.
            self::store_default_php_timezone();
        }

        return self::$defaultphptimezone;
    }

    /**
     * To be called from lib/setup.php only!
     */
    public static function store_default_php_timezone() {
        if ((defined('PHPUNIT_TEST') and PHPUNIT_TEST)
            or defined('BEHAT_SITE_RUNNING') or defined('BEHAT_TEST') or defined('BEHAT_UTIL')) {
            // We want all test sites to be consistent by default.
            self::$defaultphptimezone = 'Australia/Perth';
            return;
        }
        if (!isset(self::$defaultphptimezone)) {
            self::$defaultphptimezone = date_default_timezone_get();
        }
    }

    /**
     * Do not use directly - use $this->setTimezone('xx', $tz) instead in your test case.
     * @param string $tz valid timezone name
     */
    public static function phpunit_override_default_php_timezone($tz) {
        if (!defined('PHPUNIT_TEST')) {
            throw new coding_exception('core_date::phpunit_override_default_php_timezone() must be used only from unit tests');
        }
        $result = timezone_open($tz); // This triggers error if $tz invalid.
        if ($result !== false) {
            self::$defaultphptimezone = $tz;
        } else {
            self::$defaultphptimezone = 'Australia/Perth';
        }
    }

    /**
     * To be called from phpunit reset only, after restoring $CFG.
     */
    public static function phpunit_reset() {
        global $CFG;
        if (!defined('PHPUNIT_TEST')) {
            throw new coding_exception('core_date::phpunit_reset() must be used only from unit tests');
        }
        self::store_default_php_timezone();
        date_default_timezone_set($CFG->timezone);
    }

    /**
     * Initialise timezone arrays, call before use.
     */
    protected static function init_zones() {
        if (isset(self::$goodzones)) {
            return;
        }

        $zones = DateTimeZone::listIdentifiers();
        self::$goodzones = array_fill_keys($zones, true);

        $zones = DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC);
        self::$bczones = array();
        foreach ($zones as $zone) {
            if (isset(self::$goodzones[$zone])) {
                continue;
            }
            self::$bczones[$zone] = true;
        }

        self::$badzones = array(
            // Windows time zones.
            'Dateline Standard Time' => 'Etc/GMT+12',
            'Hawaiian Standard Time' => 'Pacific/Honolulu',
            'Alaskan Standard Time' => 'America/Anchorage',
            'Pacific Standard Time (Mexico)' => 'America/Tijuana',
            'Pacific Standard Time' => 'America/Los_Angeles',
            'US Mountain Standard Time' => 'America/Phoenix',
            'Mountain Standard Time (Mexico)' => 'America/Chihuahua',
            'Mountain Standard Time' => 'America/Denver',
            'Central America Standard Time' => 'America/Guatemala',
            'Central Standard Time' => 'America/Chicago',
            'Central Standard Time (Mexico)' => 'America/Mexico_City',
            'Canada Central Standard Time' => 'America/Regina',
            'SA Pacific Standard Time' => 'America/Bogota',
            'S.A. Pacific Standard Time' => 'America/Bogota',
            'Eastern Standard Time' => 'America/New_York',
            'US Eastern Standard Time' => 'America/Indiana/Indianapolis',
            'U.S. Eastern Standard Time' => 'America/Indiana/Indianapolis',
            'Venezuela Standard Time' => 'America/Caracas',
            'Paraguay Standard Time' => 'America/Asuncion',
            'Atlantic Standard Time' => 'America/Halifax',
            'Central Brazilian Standard Time' => 'America/Cuiaba',
            'SA Western Standard Time' => 'America/La_Paz',
            'S.A. Western Standard Time' => 'America/La_Paz',
            'Pacific SA Standard Time' => 'America/Santiago',
            'Pacific S.A. Standard Time' => 'America/Santiago',
            'Newfoundland Standard Time' => 'America/St_Johns',
            'Newfoundland and Labrador Standard Time' => 'America/St_Johns',
            'E. South America Standard Time' => 'America/Sao_Paulo',
            'Argentina Standard Time' => 'America/Argentina/Buenos_Aires',
            'SA Eastern Standard Time' => 'America/Cayenne',
            'S.A. Eastern Standard Time' => 'America/Cayenne',
            'Greenland Standard Time' => 'America/Godthab',
            'Montevideo Standard Time' => 'America/Montevideo',
            'Bahia Standard Time' => 'America/Bahia',
            'Azores Standard Time' => 'Atlantic/Azores',
            'Cape Verde Standard Time' => 'Atlantic/Cape_Verde',
            'Morocco Standard Time' => 'Africa/Casablanca',
            'GMT Standard Time' => 'Europe/London',
            'Greenwich Standard Time' => 'Atlantic/Reykjavik',
            'W. Europe Standard Time' => 'Europe/Berlin',
            'Central Europe Standard Time' => 'Europe/Budapest',
            'Romance Standard Time' => 'Europe/Paris',
            'Central European Standard Time' => 'Europe/Warsaw',
            'W. Central Africa Standard Time' => 'Africa/Lagos',
            'Namibia Standard Time' => 'Africa/Windhoek',
            'Jordan Standard Time' => 'Asia/Amman',
            'GTB Standard Time' => 'Europe/Bucharest',
            'Middle East Standard Time' => 'Asia/Beirut',
            'Egypt Standard Time' => 'Africa/Cairo',
            'Syria Standard Time' => 'Asia/Damascus',
            'South Africa Standard Time' => 'Africa/Johannesburg',
            'FLE Standard Time' => 'Europe/Kiev',
            'Turkey Standard Time' => 'Europe/Istanbul',
            'Israel Standard Time' => 'Asia/Jerusalem',
            'Kaliningrad Standard Time' => 'Europe/Kaliningrad',
            'Libya Standard Time' => 'Africa/Tripoli',
            'Arabic Standard Time' => 'Asia/Baghdad',
            'Arab Standard Time' => 'Asia/Riyadh',
            'Belarus Standard Time' => 'Europe/Minsk',
            'Russian Standard Time' => 'Europe/Moscow',
            'E. Africa Standard Time' => 'Africa/Nairobi',
            'Iran Standard Time' => 'Asia/Tehran',
            'Arabian Standard Time' => 'Asia/Dubai',
            'Azerbaijan Standard Time' => 'Asia/Baku',
            'Russia Time Zone 3' => 'Europe/Samara',
            'Mauritius Standard Time' => 'Indian/Mauritius',
            'Georgian Standard Time' => 'Asia/Tbilisi',
            'Caucasus Standard Time' => 'Asia/Yerevan',
            'Afghanistan Standard Time' => 'Asia/Kabul',
            'West Asia Standard Time' => 'Asia/Tashkent',
            'Ekaterinburg Standard Time' => 'Asia/Yekaterinburg',
            'Pakistan Standard Time' => 'Asia/Karachi',
            'India Standard Time' => 'Asia/Kolkata', // PHP and Windows differ in spelling.
            'Sri Lanka Standard Time' => 'Asia/Colombo',
            'Nepal Standard Time' => 'Asia/Kathmandu',
            'Central Asia Standard Time' => 'Asia/Almaty',
            'Bangladesh Standard Time' => 'Asia/Dhaka',
            'N. Central Asia Standard Time' => 'Asia/Novosibirsk',
            'Myanmar Standard Time' => 'Asia/Yangon',
            'SE Asia Standard Time' => 'Asia/Bangkok',
            'S.E. Asia Standard Time' => 'Asia/Bangkok',
            'North Asia Standard Time' => 'Asia/Krasnoyarsk',
            'China Standard Time' => 'Asia/Shanghai',
            'North Asia East Standard Time' => 'Asia/Irkutsk',
            'Singapore Standard Time' => 'Asia/Singapore',
            'W. Australia Standard Time' => 'Australia/Perth',
            'Taipei Standard Time' => 'Asia/Taipei',
            'Ulaanbaatar Standard Time' => 'Asia/Ulaanbaatar',
            'Tokyo Standard Time' => 'Asia/Tokyo',
            'Korea Standard Time' => 'Asia/Seoul',
            'Yakutsk Standard Time' => 'Asia/Yakutsk',
            'Cen. Australia Standard Time' => 'Australia/Adelaide',
            'AUS Central Standard Time' => 'Australia/Darwin',
            'A.U.S. Central Standard Time' => 'Australia/Darwin',
            'E. Australia Standard Time' => 'Australia/Brisbane',
            'AUS Eastern Standard Time' => 'Australia/Sydney',
            'A.U.S. Eastern Standard Time' => 'Australia/Sydney',
            'West Pacific Standard Time' => 'Pacific/Port_Moresby',
            'Tasmania Standard Time' => 'Australia/Hobart',
            'Magadan Standard Time' => 'Asia/Magadan',
            'Vladivostok Standard Time' => 'Asia/Vladivostok',
            'Russia Time Zone 10' => 'Asia/Srednekolymsk',
            'Central Pacific Standard Time' => 'Pacific/Guadalcanal',
            'Russia Time Zone 11' => 'Asia/Kamchatka',
            'New Zealand Standard Time' => 'Pacific/Auckland',
            'Fiji Standard Time' => 'Pacific/Fiji',
            'Fiji Islands Standard Time' => 'Pacific/Fiji',
            'Tonga Standard Time' => 'Pacific/Tongatapu',
            'Samoa Standard Time' => 'Pacific/Apia',
            'Line Islands Standard Time' => 'Pacific/Kiritimati',
            'Mexico Standard Time 2' => 'America/Chihuahua',
            'Mexico Standard Time' => 'America/Mexico_City',
            'U.S. Mountain Standard Time' => 'America/Phoenix',
            'Mid-Atlantic Standard Time' => 'Atlantic/South_Georgia',
            'E. Europe Standard Time' => 'Europe/Minsk',
            'Transitional Islamic State of Afghanistan Standard Time' => 'Asia/Kabul',
            'Armenian Standard Time' => 'Asia/Yerevan',
            'Kamchatka Standard Time' => 'Asia/Kamchatka',

            // A lot more bad legacy (deprecated) time zones.
            'Australia/ACT' => 'Australia/Sydney',
            'Australia/LHI' => 'Australia/Lord_Howe',
            'Australia/North' => 'Australia/Darwin',
            'Australia/NSW' => 'Australia/Sydney',
            'Australia/Queensland' => 'Australia/Brisbane',
            'Australia/South' => 'Australia/Adelaide',
            'Australia/Tasmania' => 'Australia/Hobart',
            'Australia/Victoria' => 'Australia/Melbourne',
            'Australia/West' => 'Australia/Perth',
            'Brazil/Acre' => 'America/Rio_Branco',
            'Brazil/DeNoronha' => 'America/Noronha',
            'Brazil/East' => 'America/Sao_Paulo',
            'Brazil/West' => 'America/Manaus',
            'Canada/Atlantic' => 'America/Halifax',
            'Canada/Central' => 'America/Winnipeg',
            'Canada/Eastern' => 'America/Toronto',
            'Canada/Mountain' => 'America/Edmonton',
            'Canada/Newfoundland' => 'America/St_Johns',
            'Canada/Pacific' => 'America/Vancouver',
            'Canada/Saskatchewan' => 'America/Regina',
            'Canada/Yukon' => 'America/Whitehorse',
            'CDT' => 'America/Chicago',
            'CET' => 'Europe/Berlin',
            'Central European Time' => 'Europe/Berlin',
            'Central Time' => 'America/Chicago',
            'Chile/Continental' => 'America/Santiago',
            'Chile/EasterIsland' => 'Pacific/Easter',
            'China Time' => 'Asia/Shanghai',
            'CST' => 'America/Chicago',
            'CST6CDT' => 'America/Chicago',
            'Cuba' => 'America/Havana',
            'EDT' => 'America/New_York',
            'EET' => 'Europe/Kiev',
            'Egypt' => 'Africa/Cairo',
            'Eire' => 'Europe/Dublin',
            'EST' => 'America/Cancun',
            'EST5EDT' => 'America/New_York',
            'Eastern Time' => 'America/New_York',
            'Etc/Greenwich' => 'Etc/GMT',
            'Etc/UCT' => 'Etc/UTC',
            'Etc/Universal' => 'Etc/UTC',
            'Etc/Zulu' => 'Etc/UTC',
            'FET' => 'Europe/Minsk',
            'GB' => 'Europe/London',
            'GB-Eire' => 'Europe/London',
            'Greenwich' => 'Etc/GMT',
            'Hongkong' => 'Asia/Hong_Kong',
            'HST' => 'Pacific/Honolulu',
            'Iceland' => 'Atlantic/Reykjavik',
            'India Time' => 'Asia/Kolkata',
            'Iran' => 'Asia/Tehran',
            'Israel' => 'Asia/Jerusalem',
            'IST' => 'Asia/Kolkata',
            'Jamaica' => 'America/Jamaica',
            'Japan' => 'Asia/Tokyo',
            'Japan Standard Time' => 'Asia/Tokyo',
            'Japan Time' => 'Asia/Tokyo',
            'JST' => 'Asia/Tokyo',
            'Kwajalein' => 'Pacific/Kwajalein',
            'Libya' => 'Africa/Tripoli',
            'MDT' => 'America/Denver',
            'MET' => 'Europe/Paris',
            'Mexico/BajaNorte' => 'America/Tijuana',
            'Mexico/BajaSur' => 'America/Mazatlan',
            'Mexico/General' => 'America/Mexico_City',
            'MST' => 'America/Phoenix',
            'MST7MDT' => 'America/Denver',
            'Navajo' => 'America/Denver',
            'NZ' => 'Pacific/Auckland',
            'NZ-CHAT' => 'Pacific/Chatham',
            'Pacific Time' => 'America/Los_Angeles',
            'PDT' => 'America/Los_Angeles',
            'Poland' => 'Europe/Warsaw',
            'Portugal' => 'Europe/Lisbon',
            'PRC' => 'Asia/Shanghai',
            'PST' => 'America/Los_Angeles',
            'PST8PDT' => 'America/Los_Angeles',
            'ROC' => 'Asia/Taipei',
            'ROK' => 'Asia/Seoul',
            'Singapore' => 'Asia/Singapore',
            'Turkey' => 'Europe/Istanbul',
            'UCT' => 'Etc/UTC',
            'Universal' => 'Etc/UTC',
            'US/Alaska' => 'America/Anchorage',
            'US/Aleutian' => 'America/Adak',
            'US/Arizona' => 'America/Phoenix',
            'US/Central' => 'America/Chicago',
            'US/East-Indiana' => 'America/Indiana/Indianapolis',
            'US/Eastern' => 'America/New_York',
            'US/Hawaii' => 'Pacific/Honolulu',
            'US/Indiana-Starke' => 'America/Indiana/Knox',
            'US/Michigan' => 'America/Detroit',
            'US/Mountain' => 'America/Denver',
            'US/Pacific' => 'America/Los_Angeles',
            'US/Pacific-New' => 'America/Los_Angeles',
            'US/Samoa' => 'Pacific/Pago_Pago',
            'W-SU' => 'Europe/Moscow',
            'WET' => 'Europe/London',
            'Zulu' => 'Etc/UTC',

            // Some UTC variations.
            'UTC-01' => 'Etc/GMT+1',
            'UTC-02' => 'Etc/GMT+2',
            'UTC-03' => 'Etc/GMT+3',
            'UTC-04' => 'Etc/GMT+4',
            'UTC-05' => 'Etc/GMT+5',
            'UTC-06' => 'Etc/GMT+6',
            'UTC-07' => 'Etc/GMT+7',
            'UTC-08' => 'Etc/GMT+8',
            'UTC-09' => 'Etc/GMT+9',

            // Some weird GMTs.
            'Etc/GMT+0' => 'Etc/GMT',
            'Etc/GMT-0' => 'Etc/GMT',
            'Etc/GMT0' => 'Etc/GMT',

            // Link old timezone names with their new names.
            'Africa/Asmera' => 'Africa/Asmara',
            'Africa/Timbuktu' => 'Africa/Abidjan',
            'America/Argentina/ComodRivadavia' => 'America/Argentina/Catamarca',
            'America/Atka' => 'America/Adak',
            'America/Buenos_Aires' => 'America/Argentina/Buenos_Aires',
            'America/Catamarca' => 'America/Argentina/Catamarca',
            'America/Coral_Harbour' => 'America/Atikokan',
            'America/Cordoba' => 'America/Argentina/Cordoba',
            'America/Ensenada' => 'America/Tijuana',
            'America/Fort_Wayne' => 'America/Indiana/Indianapolis',
            'America/Indianapolis' => 'America/Indiana/Indianapolis',
            'America/Jujuy' => 'America/Argentina/Jujuy',
            'America/Knox_IN' => 'America/Indiana/Knox',
            'America/Louisville' => 'America/Kentucky/Louisville',
            'America/Mendoza' => 'America/Argentina/Mendoza',
            'America/Montreal' => 'America/Toronto',
            'America/Porto_Acre' => 'America/Rio_Branco',
            'America/Rosario' => 'America/Argentina/Cordoba',
            'America/Santa_Isabel' => 'America/Tijuana',
            'America/Shiprock' => 'America/Denver',
            'America/Virgin' => 'America/Port_of_Spain',
            'Antarctica/South_Pole' => 'Pacific/Auckland',
            'Asia/Ashkhabad' => 'Asia/Ashgabat',
            'Asia/Calcutta' => 'Asia/Kolkata',
            'Asia/Chongqing' => 'Asia/Shanghai',
            'Asia/Chungking' => 'Asia/Shanghai',
            'Asia/Dacca' => 'Asia/Dhaka',
            'Asia/Harbin' => 'Asia/Shanghai',
            'Asia/Istanbul' => 'Europe/Istanbul',
            'Asia/Kashgar' => 'Asia/Urumqi',
            'Asia/Katmandu' => 'Asia/Kathmandu',
            'Asia/Macao' => 'Asia/Macau',
            'Asia/Rangoon' => 'Asia/Yangon',
            'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
            'Asia/Tel_Aviv' => 'Asia/Jerusalem',
            'Asia/Thimbu' => 'Asia/Thimphu',
            'Asia/Ujung_Pandang' => 'Asia/Makassar',
            'Asia/Ulan_Bator' => 'Asia/Ulaanbaatar',
            'Atlantic/Faeroe' => 'Atlantic/Faroe',
            'Atlantic/Jan_Mayen' => 'Europe/Oslo',
            'Australia/Canberra' => 'Australia/Sydney',
            'Australia/Yancowinna' => 'Australia/Broken_Hill',
            'Europe/Belfast' => 'Europe/London',
            'Europe/Nicosia' => 'Asia/Nicosia',
            'Europe/Tiraspol' => 'Europe/Chisinau',
            'Pacific/Johnston' => 'Pacific/Honolulu',
            'Pacific/Ponape' => 'Pacific/Pohnpei',
            'Pacific/Samoa' => 'Pacific/Pago_Pago',
            'Pacific/Truk' => 'Pacific/Chuuk',
            'Pacific/Yap' => 'Pacific/Chuuk',
        );

        // Legacy GMT fallback.
        for ($i = -12; $i <= 14; $i++) {
            $off = abs($i);
            if ($i < 0) {
                $mapto = 'Etc/GMT+' . $off;
                $utc = 'UTC-' . $off;
                $gmt = 'GMT-' . $off;
            } else if ($i > 0) {
                $mapto = 'Etc/GMT-' . $off;
                $utc = 'UTC+' . $off;
                $gmt = 'GMT+' . $off;
            } else {
                $mapto = 'Etc/GMT';
                $utc = 'UTC';
                $gmt = 'GMT';
            }
            if (isset(self::$bczones[$mapto])) {
                self::$badzones[$i . ''] = $mapto;
                self::$badzones[$i . '.0'] = $mapto;
                self::$badzones[$utc] = $mapto;
                self::$badzones[$gmt] = $mapto;
            }
        }

        // Legacy Moodle half an hour offsets - pick any city nearby, ideally without DST.
        self::$badzones['4.5'] = 'Asia/Kabul';
        self::$badzones['5.5'] = 'Asia/Kolkata';
        self::$badzones['6.5'] = 'Asia/Rangoon';
        self::$badzones['9.5'] = 'Australia/Darwin';

        // Remove bad zones that are elsewhere.
        foreach (self::$bczones as $zone => $unused) {
            if (isset(self::$badzones[$zone])) {
                unset(self::$badzones[$zone]);
            }
        }
        foreach (self::$goodzones as $zone => $unused) {
            if (isset(self::$badzones[$zone])) {
                unset(self::$badzones[$zone]);
            }
        }
    }
}
