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
 * Theme lib file.
 *
 * @package    theme_eummenaboost
 * @copyright  2020 Eummena (https://eummena.org/)
 * @credits    theme_boost - MoodleHQ
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
// This line protects the file from being accessed by a URL directly.                                                               
defined('MOODLE_INTERNAL') || die();

// Function to return the SCSS to prepend to our main SCSS for this theme.
// Note the function name starts with the component name because this is a global function and we don't want namespace clashes.

function theme_eummenaboost_get_main_scss_content($theme) {                                                                                
    global $CFG;                                                                                                                    
 
    $scss = '';                                                                                                                     
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;                                                 
    $fs = get_file_storage();                                                                                                       
 
    $context = context_system::instance();                                                                                          
    if ($filename == 'default.scss') {                                                                                              
        // We still load the default preset files directly from the boost theme. No sense in duplicating them.                      
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');                                        
    } else if ($filename == 'plain.scss') {                                                                                         
        // We still load the default preset files directly from the boost theme. No sense in duplicating them.                      
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/plain.scss');                                          
 
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_eummenaboost', 'preset', 0, '/', $filename))) {              
        // This preset file was fetched from the file area for theme_eummenaboost and not theme_boost (see the line above).                
        $scss .= $presetfile->get_content();                                                                                        
    } else {                                                                                                                        
        // Safety fallback - maybe new installs etc.                                                                                
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');                                        
    }                                                                                                                                       
 
    // Pre CSS - this is loaded AFTER any prescss from the setting but before the main scss.                                        
    $pre = file_get_contents($CFG->dirroot . '/theme/eummenaboost/scss/pre.scss'); // Pre SCSS for the theme_eummenaboost.

    // Post CSS - this is loaded AFTER the main scss but before the extra scss from the setting.                                    
    $post = file_get_contents($CFG->dirroot . '/theme/eummenaboost/scss/post.scss');   // Post SCSS for the theme_eummenaboost.                                                   
 
    // Combine them together.                                                                                                       
    return $pre . "\n" . $scss . "\n" . $post;    
}

// /**
//  * Inject additional SCSS.
//  *
//  * @param theme_config $theme The theme config object.
//  * @return string
//  */
// function theme_eummenaboost_get_extra_scss($theme) {
//     $content = '';
//     $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');

//     // Sets the background image, and its settings.
//     if (!empty($imageurl)) {
//         $content .= 'body { ';
//         $content .= "background-image: url('$imageurl'); background-size: cover;";
//         $content .= ' }';
        
//     }
//     // Always return the background image with the scss when we have it.
//     return !empty($theme->settings->scss) ? $theme->settings->scss . ' ' . $content  : $content ;
// }

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_eummenaboost_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'backgroundimage')) {
        $theme = theme_config::load('eummenaboost');
        // By default, theme files must be cache-able by both browsers and proxies.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}

/**
 * Copy the updated theme image to the correct location in dataroot for the image to be served
 * by /theme/image.php. Also clear theme caches.
 *
 * @param $settingname
 */

// function theme_eummenaboost_update_settings_images($settingname) {                                                                         
//     global $CFG;                                                                                                                    
 
//     // The setting name that was updated comes as a string like 's_theme_eummenaboost_loginbackgroundimage'.                               
//     // We split it on '_' characters.                                                                                               
//     $parts = explode('_', $settingname);                                                                                            
//     // And get the last one to get the setting name..                                                                               
//     $settingname = end($parts);                                                                                                     
 
//     // Admin settings are stored in system context.                                                                                 
//     $syscontext = context_system::instance();                                                                                       
//     // This is the component name the setting is stored in.                                                                         
//     $component = 'theme_eummenaboost';                                                                                                     
 
//     // This is the value of the admin setting which is the filename of the uploaded file.                                           
//     $filename = get_config($component, $settingname);                                                                               
//     // We extract the file extension because we want to preserve it.                                                                
//     $extension = substr($filename, strrpos($filename, '.') + 1);                                                                    
 
//     // This is the path in the moodle internal file system.                                                                         
//     $fullpath = "/{$syscontext->id}/{$component}/{$settingname}/0{$filename}";                                                      
//     // Get an instance of the moodle file storage.                                                                                  
//     $fs = get_file_storage();                                                                                                       
//     // This is an efficient way to get a file if we know the exact path.                                                            
//     if ($file = $fs->get_file_by_hash(sha1($fullpath))) {                                                                           
//         // We got the stored file - copy it to dataroot.                                                                            
//         // This location matches the searched for location in theme_config::resolve_image_location.                                 
//         $pathname = $CFG->dataroot . '/pix_plugins/theme/eummenaboost/' . $settingname . '.' . $extension;                                 
 
//         // This pattern matches any previous files with maybe different file extensions.                                            
//         $pathpattern = $CFG->dataroot . '/pix_plugins/theme/eummenaboost/' . $settingname . '.*';                                          
 
//         // Make sure this dir exists.                                                                                               
//         @mkdir($CFG->dataroot . '/pix_plugins/theme/eummenaboost/', $CFG->directorypermissions, true);                                      
 
//         // Delete any existing files for this setting.                                                                              
//         foreach (glob($pathpattern) as $filename) {                                                                                 
//             @unlink($filename);                                                                                                     
//         }                                                                                                                           
 
//         // Copy the current file to this location.                                                                                  
//         $file->copy_content_to($pathname);                                                                                          
//     }                                                                                                                               
 
//     // Reset theme caches.                                                                                                          
//     theme_reset_all_caches();                                                                                                       
// }