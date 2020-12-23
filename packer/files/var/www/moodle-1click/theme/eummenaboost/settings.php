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
 * Theme settings file.
 *
 * @package    theme_eummenaboost
 * @copyright  2020 Eummena (https://eummena.org/)
 * @credits    theme_boost - MoodleHQ
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This line protects the file from being accessed by a URL directly.                                                               
defined('MOODLE_INTERNAL') || die();                                                                                                
 
// This is used for performance, we don't need to know about these settings on every page in Moodle, only when                      
// we are looking at the admin settings pages.                                                                                      
if ($ADMIN->fulltree) {

    $settings = new theme_boost_admin_settingspage_tabs('themesettingeummenaboost', get_string('pluginname', 'theme_eummenaboost'));             
    
    $page = new admin_settingpage('theme_eummenaboost_general', get_string('generalsettings', 'theme_eummenaboost'));

    // Preset.
    $name = 'theme_eummenaboost/preset';
    $title = get_string('preset', 'theme_eummenaboost');
    $description = get_string('preset_desc', 'theme_eummenaboost');
    $default = 'default.scss';

    $context = context_system::instance();
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'theme_eummenaboost', 'preset', 0, 'itemid, filepath, filename', false);

    $choices = [];
    foreach ($files as $file) {
        $choices[$file->get_filename()] = $file->get_filename();
    }
    // These are the built in presets.
    $choices['default.scss'] = 'default.scss';
    $choices['plain.scss'] = 'plain.scss';

    $setting = new admin_setting_configthemepreset($name, $title, $description, $default, $choices, 'boost');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Preset files setting.
    $name = 'theme_eummenaboost/presetfiles';
    $title = get_string('presetfiles','theme_eummenaboost');
    $description = get_string('presetfiles_desc', 'theme_eummenaboost');

    $setting = new admin_setting_configstoredfile($name, $title, $description, 'preset', 0,
        array('maxfiles' => 20, 'accepted_types' => array('.scss')));
    $page->add($setting);

    // Background image setting.
    $name = 'theme_eummenaboost/backgroundimage';
    $title = get_string('backgroundimage', 'theme_eummenaboost');
    $description = get_string('backgroundimage_desc', 'theme_eummenaboost');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'backgroundimage');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Variable $body-color.
    // We use an empty default value because the default colour should come from the preset.
    $name = 'theme_eummenaboost/brandcolor';
    $title = get_string('brandcolor', 'theme_eummenaboost');
    $description = get_string('brandcolor_desc', 'theme_eummenaboost');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Must add the page after definiting all the settings!
    $settings->add($page);

    // Advanced settings.
    $page = new admin_settingpage('theme_eummenaboost_advanced', get_string('advancedsettings', 'theme_eummenaboost'));

    // Raw SCSS to include before the content.
    $setting = new admin_setting_scsscode('theme_eummenaboost/scsspre',
        get_string('rawscsspre', 'theme_eummenaboost'), get_string('rawscsspre_desc', 'theme_eummenaboost'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Raw SCSS to include after the content.
    $setting = new admin_setting_scsscode('theme_eummenaboost/scss', get_string('rawscss', 'theme_eummenaboost'),
        get_string('rawscss_desc', 'theme_eummenaboost'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
  

    // $settings = new theme_boost_admin_settingspage_tabs('themesettingeummenasupport', 
    //         get_string('supporttab', 'theme_eummenaboost'));             

    // Eummena Support tab.                                                                                                           
    $page = new admin_settingpage('theme_eummenaboost', get_string('supporttab', 'theme_eummenaboost'));                           
    // Support details.

    $supporthtml= "<img src='$CFG->wwwroot/theme/eummenaboost/pix/eummena_logo_small.png'/>" .
            "<p>The Moodle 1-click app for DigitalOcean MarketPlace " .
            "is supported by <a href='https://eummena.org/' target='_blank'>Eummena</a>, " .
            "Premium Moodle Partner.<br/> For any feedback or technical support, visit the tracker at: " .
            "<a href='https://github.com/eummena/moodle-1click-do' " .
            "target='_blank'>https://github.com/eummena/moodle-1click-do</a></p></div>";    
    // $page->add(new admin_setting_description('theme_eummenaboost/supporttitle', 
            // get_string('supporttitle', 'theme_eummenaboost'), $supportrttitle', 
    $page->add(new admin_setting_description('theme_eummenaboost/supporttitle', 
            $supporthtml, NULL));
    // Must add the page after definiting all the settings!
    $settings->add($page); 
}

