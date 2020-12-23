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
 * EummenaBoost theme installation.
 *
 * @package    theme_eummenaboost
 * @copyright  2020 onwards Eummena (https://eummena.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


function xmldb_theme_eummenaboost_install() {
    // $currentsetting = get_config('theme_eummenaboost');
 
    // // Create a new config called settinga and give it settingone's value.
    // set_config('settinga', $currentsetting->settingone, 'theme_mythemename');
    // // Remove settingone
    // unset_config('settingone', 'theme_mythemename');
 
    // // Create a new config called settingb and give it settingtwo's value.
    // set_config('settingb', $currentsetting->settingtwo, 'theme_mythemename');
    // // Remove settingtwo
    // unset_config('settingtwo', 'theme_mythemename');
 
	global $DB;
	$theme = $DB->get_record('config', ['name' => 'theme']);
	$theme->value = 'eummenaboost';
	$DB->update_record('config', $theme);

    return true;
}

