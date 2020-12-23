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
 * Form for editing tag_youtube block instances.
 *
 * @package    block_tag_youtube
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Form for editing tag_youtube block instances.
 *
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_tag_youtube_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        global $OUTPUT;

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_tag_youtube'));
        $mform->setType('config_title', PARAM_TEXT);

        $mform->addElement('text', 'config_numberofvideos', get_string('numberofvideos', 'block_tag_youtube'), array('size' => 5));
        $mform->setType('config_numberofvideos', PARAM_INT);

        // Category setting.
        $categorychoices = ['0' => get_string('anycategory', 'block_tag_youtube')];
        $categoryerror = '';

        try {
            // Get all video categories through an API call and add them to the category list.
            $categorychoices += $this->block->get_categories();
        } catch (Exception $e) {
            $categoryerror = $e->getMessage();
        }
        $mform->addElement('select', 'config_category', get_string('category', 'block_tag_youtube'),
            $categorychoices);
        $mform->setDefault('config_category', 0);

        if ($categoryerror) {
            $notification = $OUTPUT->notification(get_string('categoryerror', 'block_tag_youtube', $categoryerror),
                'error');
            $mform->addElement('static', 'config_category_error', '', $notification);
        }

        $mform->addElement('text', 'config_playlist', get_string('includeonlyvideosfromplaylist', 'block_tag_youtube'));
        $mform->setType('config_playlist', PARAM_ALPHANUM);
    }
}
