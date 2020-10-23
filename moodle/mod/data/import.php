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
 * This file is part of the Database module for Moodle
 *
 * @copyright 2005 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package mod_data
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/csvlib.class.php');
require_once('import_form.php');

$id              = optional_param('id', 0, PARAM_INT);  // course module id
$d               = optional_param('d', 0, PARAM_INT);   // database id
$rid             = optional_param('rid', 0, PARAM_INT); // record id
$fielddelimiter  = optional_param('fielddelimiter', ',', PARAM_CLEANHTML); // characters used as field delimiters for csv file import
$fieldenclosure = optional_param('fieldenclosure', '', PARAM_CLEANHTML);   // characters used as record delimiters for csv file import

$url = new moodle_url('/mod/data/import.php');
if ($rid !== 0) {
    $url->param('rid', $rid);
}
if ($fielddelimiter !== '') {
    $url->param('fielddelimiter', $fielddelimiter);
}
if ($fieldenclosure !== '') {
    $url->param('fieldenclosure', $fieldenclosure);
}

if ($id) {
    $url->param('id', $id);
    $PAGE->set_url($url);
    $cm     = get_coursemodule_from_id('data', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    $data   = $DB->get_record('data', array('id'=>$cm->instance), '*', MUST_EXIST);

} else {
    $url->param('d', $d);
    $PAGE->set_url($url);
    $data   = $DB->get_record('data', array('id'=>$d), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$data->course), '*', MUST_EXIST);
    $cm     = get_coursemodule_from_instance('data', $data->id, $course->id, false, MUST_EXIST);
}

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/data:manageentries', $context);
$form = new mod_data_import_form(new moodle_url('/mod/data/import.php'));

/// Print the page header
$PAGE->navbar->add(get_string('add', 'data'));
$PAGE->set_title($data->name);
$PAGE->set_heading($course->fullname);
navigation_node::override_active_url(new moodle_url('/mod/data/import.php', array('d' => $data->id)));
echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(get_string('uploadrecords', 'mod_data'), 'uploadrecords', 'mod_data');

/// Groups needed for Add entry tab
$currentgroup = groups_get_activity_group($cm);
$groupmode = groups_get_activity_groupmode($cm);

if (!$formdata = $form->get_data()) {
    /// Upload records section. Only for teachers and the admin.
    echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
    require_once('import_form.php');
    $form = new mod_data_import_form(new moodle_url('/mod/data/import.php'));
    $formdata = new stdClass();
    $formdata->d = $data->id;
    $form->set_data($formdata);
    $form->display();
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    die;
} else {
    $filecontent = $form->get_file_content('recordsfile');
    $recordsadded = data_import_csv($cm, $data, $filecontent, $formdata->encoding, $formdata->fielddelimiter);
}

if ($recordsadded > 0) {
    echo $OUTPUT->notification($recordsadded. ' '. get_string('recordssaved', 'data'), '');
} else {
    echo $OUTPUT->notification(get_string('recordsnotsaved', 'data'), 'notifysuccess');
}

echo $OUTPUT->continue_button('import.php?d='.$data->id);

/// Finish the page
echo $OUTPUT->footer();
