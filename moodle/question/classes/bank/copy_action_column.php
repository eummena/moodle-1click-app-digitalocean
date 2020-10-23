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
 * Question bank column for the duplicate action icon.
 *
 * @package   core_question
 * @copyright 2013 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_question\bank;
defined('MOODLE_INTERNAL') || die();


/**
 * Question bank column for the duplicate action icon.
 *
 * @copyright 2013 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class copy_action_column extends menu_action_column_base {
    /** @var string avoids repeated calls to get_string('duplicate'). */
    protected $strcopy;

    public function init() {
        parent::init();
        $this->strcopy = get_string('duplicate');
    }

    public function get_name() {
        return 'copyaction';
    }

    protected function get_url_icon_and_label(\stdClass $question): array {
        if (!\question_bank::is_qtype_installed($question->qtype)) {
            // It sometimes happens that people end up with junk questions
            // in their question bank of a type that is no longer installed.
            // We cannot do most actions on them, because that leads to errors.
            return [null, null, null];
        }

        // To copy a question, you need permission to add a question in the same
        // category as the existing question, and ability to access the details of
        // the question being copied.
        if (question_has_capability_on($question, 'add') &&
                (question_has_capability_on($question, 'edit') || question_has_capability_on($question, 'view'))) {
            return [$this->qbank->copy_question_moodle_url($question->id), 't/copy', $this->strcopy];
        }
        return [null, null, null];
    }
}
