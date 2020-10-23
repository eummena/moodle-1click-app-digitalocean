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
 * Question external functions tests.
 *
 * @package    core_question
 * @category   external
 * @copyright  2016 Pau Ferrer <pau@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Question external functions tests
 *
 * @package    core_question
 * @category   external
 * @copyright  2016 Pau Ferrer <pau@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class core_question_external_testcase extends externallib_advanced_testcase {

    /**
     * Set up for every test
     */
    public function setUp() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course();

        // Create users.
        $this->student = self::getDataGenerator()->create_user();

        // Users enrolments.
        $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $this->studentrole->id, 'manual');
    }

    /**
     * Test update question flag
     */
    public function test_core_question_update_flag() {

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Create a question category.
        $cat = $questiongenerator->create_question_category();

        $quba = question_engine::make_questions_usage_by_activity('core_question_update_flag', context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $questiondata = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        $question = question_bank::load_question($questiondata->id);
        $slot = $quba->add_question($question);
        $qa = $quba->get_question_attempt($slot);

        self::setUser($this->student);

        $quba->start_all_questions();
        question_engine::save_questions_usage_by_activity($quba);

        $qubaid = $quba->get_id();
        $questionid = $question->id;
        $qaid = $qa->get_database_id();
        $checksum = md5($qubaid . "_" . $this->student->secret . "_" . $questionid . "_" . $qaid . "_" . $slot);

        $flag = core_question_external::update_flag($qubaid, $questionid, $qaid, $slot, $checksum, true);
        $this->assertTrue($flag['status']);

        // Test invalid checksum.
        try {
            // Using random_string to force failing.
            $checksum = md5($qubaid . "_" . random_string(11) . "_" . $questionid . "_" . $qaid . "_" . $slot);

            core_question_external::update_flag($qubaid, $questionid, $qaid, $slot, $checksum, true);
            $this->fail('Exception expected due to invalid checksum.');
        } catch (moodle_exception $e) {
            $this->assertEquals('errorsavingflags', $e->errorcode);
        }
    }

    /**
     * submit_tags_form should throw an exception when the question id doesn't match
     * a question.
     */
    public function test_submit_tags_form_incorrect_question_id() {
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        list ($category, $course, $qcat, $questions) = $questiongenerator->setup_course_and_questions();
        $questioncontext = context::instance_by_id($qcat->contextid);
        $editingcontext = $questioncontext;
        $question = $questions[0];
        // Generate an id for a question that doesn't exist.
        $missingquestionid = $questions[1]->id * 2;
        $question->id = $missingquestionid;
        $formdata = $this->generate_encoded_submit_tags_form_string($question, $qcat, $questioncontext, [], []);

        // We should receive an exception if the question doesn't exist.
        $this->expectException('moodle_exception');
        core_question_external::submit_tags_form($missingquestionid, $editingcontext->id, $formdata);
    }

    /**
     * submit_tags_form should throw an exception when the context id doesn't match
     * a context.
     */
    public function test_submit_tags_form_incorrect_context_id() {
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        list ($category, $course, $qcat, $questions) = $questiongenerator->setup_course_and_questions();
        $questioncontext = context::instance_by_id($qcat->contextid);
        $editingcontext = $questioncontext;
        $question = $questions[0];
        // Generate an id for a context that doesn't exist.
        $missingcontextid = $editingcontext->id * 200;
        $formdata = $this->generate_encoded_submit_tags_form_string($question, $qcat, $questioncontext, [], []);

        // We should receive an exception if the question doesn't exist.
        $this->expectException('moodle_exception');
        core_question_external::submit_tags_form($question->id, $missingcontextid, $formdata);
    }

    /**
     * submit_tags_form should return false when tags are disabled.
     */
    public function test_submit_tags_form_tags_disabled() {
        global $CFG;

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        list ($category, $course, $qcat, $questions) = $questiongenerator->setup_course_and_questions();
        $questioncontext = context::instance_by_id($qcat->contextid);
        $editingcontext = $questioncontext;
        $question = $questions[0];
        $user = $this->create_user_can_tag($course);
        $formdata = $this->generate_encoded_submit_tags_form_string($question, $qcat, $questioncontext, [], []);

        $this->setUser($user);
        $CFG->usetags = false;
        $result = core_question_external::submit_tags_form($question->id, $editingcontext->id, $formdata);
        $CFG->usetags = true;

        $this->assertFalse($result['status']);
    }

    /**
     * submit_tags_form should return false if the user does not have any capability
     * to tag the question.
     */
    public function test_submit_tags_form_no_tag_permissions() {
        global $DB;

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $questiongenerator = $generator->get_plugin_generator('core_question');
        list ($category, $course, $qcat, $questions) = $questiongenerator->setup_course_and_questions();
        $questioncontext = context::instance_by_id($qcat->contextid);
        $editingcontext = $questioncontext;
        $question = $questions[0];
        $formdata = $this->generate_encoded_submit_tags_form_string(
            $question,
            $qcat,
            $questioncontext,
            ['foo'],
            ['bar']
        );

        // Prohibit all of the tag capabilities.
        assign_capability('moodle/question:tagmine', CAP_PROHIBIT, $teacherrole->id, $questioncontext->id);
        assign_capability('moodle/question:tagall', CAP_PROHIBIT, $teacherrole->id, $questioncontext->id);

        $generator->enrol_user($user->id, $course->id, $teacherrole->id, 'manual');
        $user->ignoresesskey = true;
        $this->setUser($user);

        $result = core_question_external::submit_tags_form($question->id, $editingcontext->id, $formdata);

        $this->assertFalse($result['status']);
    }

    /**
     * submit_tags_form should return false if the user only has the capability to
     * tag their own questions and the question is not theirs.
     */
    public function test_submit_tags_form_tagmine_permission_non_owner_question() {
        global $DB;

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $questiongenerator = $generator->get_plugin_generator('core_question');
        list ($category, $course, $qcat, $questions) = $questiongenerator->setup_course_and_questions();
        $questioncontext = context::instance_by_id($qcat->contextid);
        $editingcontext = $questioncontext;
        $question = $questions[0];
        $formdata = $this->generate_encoded_submit_tags_form_string(
            $question,
            $qcat,
            $questioncontext,
            ['foo'],
            ['bar']
        );

        // Make sure the question isn't created by the user.
        $question->createdby = $user->id + 1;

        // Prohibit all of the tag capabilities.
        assign_capability('moodle/question:tagmine', CAP_ALLOW, $teacherrole->id, $questioncontext->id);
        assign_capability('moodle/question:tagall', CAP_PROHIBIT, $teacherrole->id, $questioncontext->id);

        $generator->enrol_user($user->id, $course->id, $teacherrole->id, 'manual');
        $user->ignoresesskey = true;
        $this->setUser($user);

        $result = core_question_external::submit_tags_form($question->id, $editingcontext->id, $formdata);

        $this->assertFalse($result['status']);
    }

    /**
     * Data provided for the submit_tags_form test to check that course tags are
     * only created in the correct editing and question context combinations.
     *
     * @return array Test cases
     */
    public function get_submit_tags_form_testcases() {
        return [
            'course - course' => [
                'editingcontext' => 'course',
                'questioncontext' => 'course',
                'questiontags' => ['foo'],
                'coursetags' => ['bar'],
                'expectcoursetags' => false
            ],
            'course - course - empty tags' => [
                'editingcontext' => 'course',
                'questioncontext' => 'course',
                'questiontags' => [],
                'coursetags' => ['bar'],
                'expectcoursetags' => false
            ],
            'course - course category' => [
                'editingcontext' => 'course',
                'questioncontext' => 'category',
                'questiontags' => ['foo'],
                'coursetags' => ['bar'],
                'expectcoursetags' => true
            ],
            'course - system' => [
                'editingcontext' => 'course',
                'questioncontext' => 'system',
                'questiontags' => ['foo'],
                'coursetags' => ['bar'],
                'expectcoursetags' => true
            ],
            'course category - course' => [
                'editingcontext' => 'category',
                'questioncontext' => 'course',
                'questiontags' => ['foo'],
                'coursetags' => ['bar'],
                'expectcoursetags' => false
            ],
            'course category - course category' => [
                'editingcontext' => 'category',
                'questioncontext' => 'category',
                'questiontags' => ['foo'],
                'coursetags' => ['bar'],
                'expectcoursetags' => false
            ],
            'course category - system' => [
                'editingcontext' => 'category',
                'questioncontext' => 'system',
                'questiontags' => ['foo'],
                'coursetags' => ['bar'],
                'expectcoursetags' => false
            ],
            'system - course' => [
                'editingcontext' => 'system',
                'questioncontext' => 'course',
                'questiontags' => ['foo'],
                'coursetags' => ['bar'],
                'expectcoursetags' => false
            ],
            'system - course category' => [
                'editingcontext' => 'system',
                'questioncontext' => 'category',
                'questiontags' => ['foo'],
                'coursetags' => ['bar'],
                'expectcoursetags' => false
            ],
            'system - system' => [
                'editingcontext' => 'system',
                'questioncontext' => 'system',
                'questiontags' => ['foo'],
                'coursetags' => ['bar'],
                'expectcoursetags' => false
            ],
        ];
    }

    /**
     * Tests that submit_tags_form only creates course tags when the correct combination
     * of editing context and question context is provided.
     *
     * Course tags can only be set on a course category or system context question that
     * is being editing in a course context.
     *
     * @dataProvider get_submit_tags_form_testcases()
     * @param string $editingcontext The type of the context the question is being edited in
     * @param string $questioncontext The type of the context the question belongs to
     * @param string[] $questiontags The tag names to set as question tags
     * @param string[] $coursetags The tag names to set as course tags
     * @param bool $expectcoursetags If the given course tags should have been set or not
     */
    public function test_submit_tags_form_context_combinations(
        $editingcontext,
        $questioncontext,
        $questiontags,
        $coursetags,
        $expectcoursetags
    ) {
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        list ($category, $course, $qcat, $questions) = $questiongenerator->setup_course_and_questions($questioncontext);
        $coursecontext = context_course::instance($course->id);
        $questioncontext = context::instance_by_id($qcat->contextid);

        switch($editingcontext) {
            case 'system':
                $editingcontext = context_system::instance();
                break;

            case 'category':
                $editingcontext = context_coursecat::instance($category->id);
                break;

            default:
                $editingcontext = context_course::instance($course->id);
        }

        $user = $this->create_user_can_tag($course);
        $question = $questions[0];
        $formdata = $this->generate_encoded_submit_tags_form_string(
            $question,
            $qcat,
            $questioncontext,
            $questiontags, // Question tags.
            $coursetags // Course tags.
        );

        $this->setUser($user);

        $result = core_question_external::submit_tags_form($question->id, $editingcontext->id, $formdata);

        $this->assertTrue($result['status']);

        $tagobjects = core_tag_tag::get_item_tags('core_question', 'question', $question->id);
        $coursetagobjects = [];
        $questiontagobjects = [];

        if ($expectcoursetags) {
            // If the use case is expecting course tags to be created then split
            // the tags into course tags and question tags and ensure we have
            // the correct number of course tags.

            while ($tagobject = array_shift($tagobjects)) {
                if ($tagobject->taginstancecontextid == $questioncontext->id) {
                    $questiontagobjects[] = $tagobject;
                } else if ($tagobject->taginstancecontextid == $coursecontext->id) {
                    $coursetagobjects[] = $tagobject;
                }
            }

            $this->assertCount(count($coursetags), $coursetagobjects);
        } else {
            $questiontagobjects = $tagobjects;
        }

        // Ensure the expected number of question tags was created.
        $this->assertCount(count($questiontags), $questiontagobjects);

        foreach ($questiontagobjects as $tagobject) {
            // If we have any question tags then make sure they are in the list
            // of expected tags and have the correct context.
            $this->assertContains($tagobject->name, $questiontags);
            $this->assertEquals($questioncontext->id, $tagobject->taginstancecontextid);
        }

        foreach ($coursetagobjects as $tagobject) {
            // If we have any course tags then make sure they are in the list
            // of expected course tags and have the correct context.
            $this->assertContains($tagobject->name, $coursetags);
            $this->assertEquals($coursecontext->id, $tagobject->taginstancecontextid);
        }
    }

    /**
     * Build the encoded form data expected by the submit_tags_form external function.
     *
     * @param  stdClass $question         The question record
     * @param  stdClass $questioncategory The question category record
     * @param  context  $questioncontext  Context for the question category
     * @param  array  $tags               A list of tag names for the question
     * @param  array  $coursetags         A list of course tag names for the question
     * @return string                    HTML encoded string of the data
     */
    protected function generate_encoded_submit_tags_form_string($question, $questioncategory,
            $questioncontext, $tags = [], $coursetags = []) {
        global $CFG;

        require_once($CFG->dirroot . '/question/type/tags_form.php');

        $data = [
            'id' => $question->id,
            'categoryid' => $questioncategory->id,
            'contextid' => $questioncontext->id,
            'questionname' => $question->name,
            'questioncategory' => $questioncategory->name,
            'context' => $questioncontext->get_context_name(false),
            'tags' => $tags,
            'coursetags' => $coursetags
        ];
        $data = core_question\form\tags::mock_generate_submit_keys($data);

        return http_build_query($data, '', '&');
    }

    /**
     * Create a user, enrol them in the course, and give them the capability to
     * tag all questions in the system context.
     *
     * @param  stdClass $course The course record to enrol in
     * @return stdClass         The user record
     */
    protected function create_user_can_tag($course) {
        global $DB;

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $roleid = $generator->create_role();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $systemcontext = context_system::instance();

        $generator->role_assign($roleid, $user->id, $systemcontext->id);
        $generator->enrol_user($user->id, $course->id, $teacherrole->id, 'manual');

        // Give the user global ability to tag questions.
        assign_capability('moodle/question:tagall', CAP_ALLOW, $roleid, $systemcontext, true);
        // Allow the user to submit form data.
        $user->ignoresesskey = true;

        return $user;
    }

    /**
     * Data provider for the get_random_question_summaries test.
     */
    public function get_random_question_summaries_test_cases() {
        return [
            'empty category' => [
                'categoryindex' => 'emptycat',
                'includesubcategories' => false,
                'usetagnames' => [],
                'expectedquestionindexes' => []
            ],
            'single category' => [
                'categoryindex' => 'cat1',
                'includesubcategories' => false,
                'usetagnames' => [],
                'expectedquestionindexes' => ['cat1q1', 'cat1q2']
            ],
            'include sub category' => [
                'categoryindex' => 'cat1',
                'includesubcategories' => true,
                'usetagnames' => [],
                'expectedquestionindexes' => ['cat1q1', 'cat1q2', 'subcatq1', 'subcatq2']
            ],
            'single category with tags' => [
                'categoryindex' => 'cat1',
                'includesubcategories' => false,
                'usetagnames' => ['cat1'],
                'expectedquestionindexes' => ['cat1q1']
            ],
            'include sub category with tag on parent' => [
                'categoryindex' => 'cat1',
                'includesubcategories' => true,
                'usetagnames' => ['cat1'],
                'expectedquestionindexes' => ['cat1q1']
            ],
            'include sub category with tag on sub' => [
                'categoryindex' => 'cat1',
                'includesubcategories' => true,
                'usetagnames' => ['subcat'],
                'expectedquestionindexes' => ['subcatq1']
            ],
            'include sub category with same tag on parent and sub' => [
                'categoryindex' => 'cat1',
                'includesubcategories' => true,
                'usetagnames' => ['foo'],
                'expectedquestionindexes' => ['cat1q1', 'subcatq1']
            ],
            'include sub category with tag not matching' => [
                'categoryindex' => 'cat1',
                'includesubcategories' => true,
                'usetagnames' => ['cat1', 'cat2'],
                'expectedquestionindexes' => []
            ]
        ];
    }

    /**
     * Test the get_random_question_summaries function with various parameter combinations.
     *
     * This function creates a data set as follows:
     *      Category: cat1
     *          Question: cat1q1
     *              Tags: 'cat1', 'foo'
     *          Question: cat1q2
     *      Category: cat2
     *          Question: cat2q1
     *              Tags: 'cat2', 'foo'
     *          Question: cat2q2
     *      Category: subcat
     *          Question: subcatq1
     *              Tags: 'subcat', 'foo'
     *          Question: subcatq2
     *          Parent: cat1
     *      Category: emptycat
     *
     * @dataProvider get_random_question_summaries_test_cases()
     * @param string $categoryindex The named index for the category to use
     * @param bool $includesubcategories If the search should include subcategories
     * @param string[] $usetagnames The tag names to include in the search
     * @param string[] $expectedquestionindexes The questions expected in the result
     */
    public function test_get_random_question_summaries_variations(
        $categoryindex,
        $includesubcategories,
        $usetagnames,
        $expectedquestionindexes
    ) {
        $this->resetAfterTest();

        $context = context_system::instance();
        $categories = [];
        $questions = [];
        $tagnames = [
            'cat1',
            'cat2',
            'subcat',
            'foo'
        ];
        $collid = core_tag_collection::get_default();
        $tags = core_tag_tag::create_if_missing($collid, $tagnames);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // First category and questions.
        list($category, $categoryquestions) = $this->create_category_and_questions(2, ['cat1', 'foo']);
        $categories['cat1'] = $category;
        $questions['cat1q1'] = $categoryquestions[0];
        $questions['cat1q2'] = $categoryquestions[1];
        // Second category and questions.
        list($category, $categoryquestions) = $this->create_category_and_questions(2, ['cat2', 'foo']);
        $categories['cat2'] = $category;
        $questions['cat2q1'] = $categoryquestions[0];
        $questions['cat2q2'] = $categoryquestions[1];
        // Sub category and questions.
        list($category, $categoryquestions) = $this->create_category_and_questions(2, ['subcat', 'foo'], $categories['cat1']);
        $categories['subcat'] = $category;
        $questions['subcatq1'] = $categoryquestions[0];
        $questions['subcatq2'] = $categoryquestions[1];
        // Empty category.
        list($category, $categoryquestions) = $this->create_category_and_questions(0);
        $categories['emptycat'] = $category;

        // Generate the arguments for the get_questions function.
        $category = $categories[$categoryindex];
        $tagids = array_map(function($tagname) use ($tags) {
            return $tags[$tagname]->id;
        }, $usetagnames);

        $result = core_question_external::get_random_question_summaries($category->id, $includesubcategories, $tagids, $context->id);
        $resultquestions = $result['questions'];
        $resulttotalcount = $result['totalcount'];
        // Generate the expected question set.
        $expectedquestions = array_map(function($index) use ($questions) {
            return $questions[$index];
        }, $expectedquestionindexes);

        // Ensure the resultquestions matches what was expected.
        $this->assertCount(count($expectedquestions), $resultquestions);
        $this->assertEquals(count($expectedquestions), $resulttotalcount);
        foreach ($expectedquestions as $question) {
            $this->assertEquals($resultquestions[$question->id]->id, $question->id);
            $this->assertEquals($resultquestions[$question->id]->category, $question->category);
        }
    }

    /**
     * get_random_question_summaries should throw an invalid_parameter_exception if not
     * given an integer for the category id.
     */
    public function test_get_random_question_summaries_invalid_category_id_param() {
        $this->resetAfterTest();

        $context = context_system::instance();
        $this->expectException('invalid_parameter_exception');
        core_question_external::get_random_question_summaries('invalid value', false, [], $context->id);
    }

    /**
     * get_random_question_summaries should throw an invalid_parameter_exception if not
     * given a boolean for the $includesubcategories parameter.
     */
    public function test_get_random_question_summaries_invalid_includesubcategories_param() {
        $this->resetAfterTest();

        $context = context_system::instance();
        $this->expectException('invalid_parameter_exception');
        core_question_external::get_random_question_summaries(1, 'invalid value', [], $context->id);
    }

    /**
     * get_random_question_summaries should throw an invalid_parameter_exception if not
     * given an array of integers for the tag ids parameter.
     */
    public function test_get_random_question_summaries_invalid_tagids_param() {
        $this->resetAfterTest();

        $context = context_system::instance();
        $this->expectException('invalid_parameter_exception');
        core_question_external::get_random_question_summaries(1, false, ['invalid', 'values'], $context->id);
    }

    /**
     * get_random_question_summaries should throw an invalid_parameter_exception if not
     * given a context.
     */
    public function test_get_random_question_summaries_invalid_context() {
        $this->resetAfterTest();

        $this->expectException('invalid_parameter_exception');
        core_question_external::get_random_question_summaries(1, false, [1, 2], 'context');
    }

    /**
     * get_random_question_summaries should throw an restricted_context_exception
     * if the given context is outside of the set of restricted contexts the user
     * is allowed to call external functions in.
     */
    public function test_get_random_question_summaries_restricted_context() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);
        $systemcontext = context_system::instance();
        // Restrict access to external functions for the logged in user to only
        // the course we just created. External functions should not be allowed
        // to execute in any contexts above the course context.
        core_question_external::set_context_restriction($coursecontext);

        // An exception should be thrown when we try to execute at the system context
        // since we're restricted to the course context.
        try {
            // Do this in a try/catch statement to allow the context restriction
            // to be reset afterwards.
            core_question_external::get_random_question_summaries(1, false, [], $systemcontext->id);
        } catch (Exception $e) {
            $this->assertInstanceOf('restricted_context_exception', $e);
        }
        // Reset the restriction so that other tests don't fail aftwards.
        core_question_external::set_context_restriction($systemcontext);
    }

    /**
     * get_random_question_summaries should return a question that is formatted correctly.
     */
    public function test_get_random_question_summaries_formats_returned_questions() {
        $this->resetAfterTest();

        list($category, $questions) = $this->create_category_and_questions(1);
        $context = context_system::instance();
        $question = $questions[0];
        $expected = (object) [
            'id' => $question->id,
            'category' => $question->category,
            'parent' => $question->parent,
            'name' => $question->name,
            'qtype' => $question->qtype
        ];

        $result = core_question_external::get_random_question_summaries($category->id, false, [], $context->id);
        $actual = $result['questions'][$question->id];

        $this->assertEquals($expected->id, $actual->id);
        $this->assertEquals($expected->category, $actual->category);
        $this->assertEquals($expected->parent, $actual->parent);
        $this->assertEquals($expected->name, $actual->name);
        $this->assertEquals($expected->qtype, $actual->qtype);
        // These values are added by the formatting. It doesn't matter what the
        // exact values are just that they are returned.
        $this->assertObjectHasAttribute('icon', $actual);
        $this->assertObjectHasAttribute('key', $actual->icon);
        $this->assertObjectHasAttribute('component', $actual->icon);
        $this->assertObjectHasAttribute('alttext', $actual->icon);
    }

    /**
     * get_random_question_summaries should allow limiting and offsetting of the result set.
     */
    public function test_get_random_question_summaries_with_limit_and_offset() {
        $this->resetAfterTest();
        $numberofquestions = 5;
        $includesubcategories = false;
        $tagids = [];
        $limit = 1;
        $offset = 0;
        $context = context_system::instance();
        list($category, $questions) = $this->create_category_and_questions($numberofquestions);

        // Sort the questions by id to match the ordering of the result.
        usort($questions, function($a, $b) {
            $aid = $a->id;
            $bid = $b->id;

            if ($aid == $bid) {
                return 0;
            }
            return $aid < $bid ? -1 : 1;
        });

        for ($i = 0; $i < $numberofquestions; $i++) {
            $result = core_question_external::get_random_question_summaries(
                $category->id,
                $includesubcategories,
                $tagids,
                $context->id,
                $limit,
                $offset
            );

            $resultquestions = $result['questions'];
            $totalcount = $result['totalcount'];

            $this->assertCount($limit, $resultquestions);
            $this->assertEquals($numberofquestions, $totalcount);
            $actual = array_shift($resultquestions);
            $expected = $questions[$i];
            $this->assertEquals($expected->id, $actual->id);
            $offset++;
        }
    }

    /**
     * get_random_question_summaries should throw an exception if the user doesn't
     * have the capability to use the questions in the requested category.
     */
    public function test_get_random_question_summaries_without_capability() {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $roleid = $generator->create_role();
        $systemcontext = context_system::instance();
        $numberofquestions = 5;
        $includesubcategories = false;
        $tagids = [];
        $context = context_system::instance();
        list($category, $questions) = $this->create_category_and_questions($numberofquestions);
        $categorycontext = context::instance_by_id($category->contextid);

        $generator->role_assign($roleid, $user->id, $systemcontext->id);
        // Prohibit all of the tag capabilities.
        assign_capability('moodle/question:viewall', CAP_PROHIBIT, $roleid, $categorycontext->id);

        $this->setUser($user);
        $this->expectException('moodle_exception');
        core_question_external::get_random_question_summaries(
            $category->id,
            $includesubcategories,
            $tagids,
            $context->id
        );
    }

    /**
     * Create a question category and create questions in that category. Tag
     * the first question in each category with the given tags.
     *
     * @param int $questioncount How many questions to create.
     * @param string[] $tagnames The list of tags to use.
     * @param stdClass|null $parentcategory The category to set as the parent of the created category.
     * @return array The category and questions.
     */
    protected function create_category_and_questions($questioncount, $tagnames = [], $parentcategory = null) {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');

        if ($parentcategory) {
            $catparams = ['parent' => $parentcategory->id];
        } else {
            $catparams = [];
        }

        $category = $generator->create_question_category($catparams);
        $questions = [];

        for ($i = 0; $i < $questioncount; $i++) {
            $questions[] = $generator->create_question('shortanswer', null, ['category' => $category->id]);
        }

        if (!empty($tagnames) && !empty($questions)) {
            $context = context::instance_by_id($category->contextid);
            core_tag_tag::set_item_tags('core_question', 'question', $questions[0]->id, $context, $tagnames);
        }

        return [$category, $questions];
    }
}
