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
 * Group external PHPunit tests
 *
 * @package    core_group
 * @category   external
 * @copyright  2012 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.4
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/group/externallib.php');
require_once($CFG->dirroot . '/group/lib.php');

class core_group_externallib_testcase extends externallib_advanced_testcase {

    /**
     * Test create_groups
     */
    public function test_create_groups() {
        global $DB;

        $this->resetAfterTest(true);

        $course  = self::getDataGenerator()->create_course();

        $group1 = array();
        $group1['courseid'] = $course->id;
        $group1['name'] = 'Group Test 1';
        $group1['description'] = 'Group Test 1 description';
        $group1['descriptionformat'] = FORMAT_MOODLE;
        $group1['enrolmentkey'] = 'Test group enrol secret phrase';
        $group1['idnumber'] = 'TEST1';
        $group2 = array();
        $group2['courseid'] = $course->id;
        $group2['name'] = 'Group Test 2';
        $group2['description'] = 'Group Test 2 description';
        $group3 = array();
        $group3['courseid'] = $course->id;
        $group3['name'] = 'Group Test 3';
        $group3['description'] = 'Group Test 3 description';
        $group3['idnumber'] = 'TEST1';
        $group4 = array();
        $group4['courseid'] = $course->id;
        $group4['name'] = 'Group Test 4';
        $group4['description'] = 'Group Test 4 description';

        // Set the required capabilities by the external function
        $context = context_course::instance($course->id);
        $roleid = $this->assignUserCapability('moodle/course:managegroups', $context->id);
        $this->assignUserCapability('moodle/course:view', $context->id, $roleid);

        // Call the external function.
        $groups = core_group_external::create_groups(array($group1, $group2));

        // We need to execute the return values cleaning process to simulate the web service server.
        $groups = external_api::clean_returnvalue(core_group_external::create_groups_returns(), $groups);

        // Checks against DB values
        $this->assertEquals(2, count($groups));
        foreach ($groups as $group) {
            $dbgroup = $DB->get_record('groups', array('id' => $group['id']), '*', MUST_EXIST);
            switch ($dbgroup->name) {
                case $group1['name']:
                    $groupdescription = $group1['description'];
                    $groupcourseid = $group1['courseid'];
                    $this->assertEquals($dbgroup->descriptionformat, $group1['descriptionformat']);
                    $this->assertEquals($dbgroup->enrolmentkey, $group1['enrolmentkey']);
                    $this->assertEquals($dbgroup->idnumber, $group1['idnumber']);
                    break;
                case $group2['name']:
                    $groupdescription = $group2['description'];
                    $groupcourseid = $group2['courseid'];
                    break;
                default:
                    throw new moodle_exception('unknowgroupname');
                    break;
            }
            $this->assertEquals($dbgroup->description, $groupdescription);
            $this->assertEquals($dbgroup->courseid, $groupcourseid);
        }

        try {
            $froups = core_group_external::create_groups(array($group3));
            $this->fail('Exception expected due to already existing idnumber.');
        } catch (moodle_exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $this->assertEquals(get_string('idnumbertaken', 'error'), $e->getMessage());
        }

        // Call without required capability
        $this->unassignUserCapability('moodle/course:managegroups', $context->id, $roleid);

        $this->expectException(\required_capability_exception::class);
        $froups = core_group_external::create_groups(array($group4));
    }

    /**
     * Test update_groups
     */
    public function test_update_groups() {
        global $DB;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();

        $group1data = array();
        $group1data['courseid'] = $course->id;
        $group1data['name'] = 'Group Test 1';
        $group1data['description'] = 'Group Test 1 description';
        $group1data['descriptionformat'] = FORMAT_MOODLE;
        $group1data['enrolmentkey'] = 'Test group enrol secret phrase';
        $group1data['idnumber'] = 'TEST1';
        $group2data = array();
        $group2data['courseid'] = $course->id;
        $group2data['name'] = 'Group Test 2';
        $group2data['description'] = 'Group Test 2 description';
        $group2data['idnumber'] = 'TEST2';

        // Set the required capabilities by the external function.
        $context = context_course::instance($course->id);
        $roleid = $this->assignUserCapability('moodle/course:managegroups', $context->id);
        $this->assignUserCapability('moodle/course:view', $context->id, $roleid);

        // Create the test groups.
        $group1 = self::getDataGenerator()->create_group($group1data);
        $group2 = self::getDataGenerator()->create_group($group2data);

        $group1data['id'] = $group1->id;
        unset($group1data['courseid']);
        $group2data['id'] = $group2->id;
        unset($group2data['courseid']);

        // No exceptions should be triggered.
        $group1data['idnumber'] = 'CHANGED';
        core_group_external::update_groups(array($group1data));
        $group2data['description'] = 'Group Test 2 description CHANGED';
        core_group_external::update_groups(array($group2data));

        foreach ([$group1, $group2] as $group) {
            $dbgroup = $DB->get_record('groups', array('id' => $group->id), '*', MUST_EXIST);
            switch ($dbgroup->name) {
                case $group1data['name']:
                    $this->assertEquals($dbgroup->idnumber, $group1data['idnumber']);
                    $groupdescription = $group1data['description'];
                    break;
                case $group2data['name']:
                    $this->assertEquals($dbgroup->idnumber, $group2data['idnumber']);
                    $groupdescription = $group2data['description'];
                    break;
                default:
                    throw new moodle_exception('unknowngroupname');
                    break;
            }
            $this->assertEquals($dbgroup->description, $groupdescription);
        }

        // Taken idnumber exception.
        $group1data['idnumber'] = 'TEST2';
        try {
            $groups = core_group_external::update_groups(array($group1data));
            $this->fail('Exception expected due to already existing idnumber.');
        } catch (moodle_exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $this->assertEquals(get_string('idnumbertaken', 'error'), $e->getMessage());
        }

        // Call without required capability.
        $group1data['idnumber'] = 'TEST1';
        $this->unassignUserCapability('moodle/course:managegroups', $context->id, $roleid);

        $this->expectException(\required_capability_exception::class);
        $groups = core_group_external::update_groups(array($group1data));
    }

    /**
     * Test get_groups
     */
    public function test_get_groups() {
        global $DB;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $group1data = array();
        $group1data['courseid'] = $course->id;
        $group1data['name'] = 'Group Test 1';
        $group1data['description'] = 'Group Test 1 description';
        $group1data['descriptionformat'] = FORMAT_MOODLE;
        $group1data['enrolmentkey'] = 'Test group enrol secret phrase';
        $group1data['idnumber'] = 'TEST1';
        $group2data = array();
        $group2data['courseid'] = $course->id;
        $group2data['name'] = 'Group Test 2';
        $group2data['description'] = 'Group Test 2 description';
        $group1 = self::getDataGenerator()->create_group($group1data);
        $group2 = self::getDataGenerator()->create_group($group2data);

        // Set the required capabilities by the external function
        $context = context_course::instance($course->id);
        $roleid = $this->assignUserCapability('moodle/course:managegroups', $context->id);
        $this->assignUserCapability('moodle/course:view', $context->id, $roleid);

        // Call the external function.
        $groups = core_group_external::get_groups(array($group1->id, $group2->id));

        // We need to execute the return values cleaning process to simulate the web service server.
        $groups = external_api::clean_returnvalue(core_group_external::get_groups_returns(), $groups);

        // Checks against DB values
        $this->assertEquals(2, count($groups));
        foreach ($groups as $group) {
            $dbgroup = $DB->get_record('groups', array('id' => $group['id']), '*', MUST_EXIST);
            switch ($dbgroup->name) {
                case $group1->name:
                    $groupdescription = $group1->description;
                    $groupcourseid = $group1->courseid;
                    $this->assertEquals($dbgroup->descriptionformat, $group1->descriptionformat);
                    $this->assertEquals($dbgroup->enrolmentkey, $group1->enrolmentkey);
                    $this->assertEquals($dbgroup->idnumber, $group1->idnumber);
                    break;
                case $group2->name:
                    $groupdescription = $group2->description;
                    $groupcourseid = $group2->courseid;
                    break;
                default:
                    throw new moodle_exception('unknowgroupname');
                    break;
            }
            $this->assertEquals($dbgroup->description, $groupdescription);
            $this->assertEquals($dbgroup->courseid, $groupcourseid);
        }

        // Call without required capability
        $this->unassignUserCapability('moodle/course:managegroups', $context->id, $roleid);

        $this->expectException(\required_capability_exception::class);
        $groups = core_group_external::get_groups(array($group1->id, $group2->id));
    }

    /**
     * Test delete_groups
     */
    public function test_delete_groups() {
        global $DB;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $group1data = array();
        $group1data['courseid'] = $course->id;
        $group1data['name'] = 'Group Test 1';
        $group1data['description'] = 'Group Test 1 description';
        $group1data['descriptionformat'] = FORMAT_MOODLE;
        $group1data['enrolmentkey'] = 'Test group enrol secret phrase';
        $group2data = array();
        $group2data['courseid'] = $course->id;
        $group2data['name'] = 'Group Test 2';
        $group2data['description'] = 'Group Test 2 description';
        $group3data['courseid'] = $course->id;
        $group3data['name'] = 'Group Test 3';
        $group3data['description'] = 'Group Test 3 description';
        $group1 = self::getDataGenerator()->create_group($group1data);
        $group2 = self::getDataGenerator()->create_group($group2data);
        $group3 = self::getDataGenerator()->create_group($group3data);

        // Set the required capabilities by the external function
        $context = context_course::instance($course->id);
        $roleid = $this->assignUserCapability('moodle/course:managegroups', $context->id);
        $this->assignUserCapability('moodle/course:view', $context->id, $roleid);

        // Checks against DB values
        $groupstotal = $DB->count_records('groups', array());
        $this->assertEquals(3, $groupstotal);

        // Call the external function.
        core_group_external::delete_groups(array($group1->id, $group2->id));

        // Checks against DB values
        $groupstotal = $DB->count_records('groups', array());
        $this->assertEquals(1, $groupstotal);

        // Call without required capability
        $this->unassignUserCapability('moodle/course:managegroups', $context->id, $roleid);

        $this->expectException(\required_capability_exception::class);
        $froups = core_group_external::delete_groups(array($group3->id));
    }

    /**
     * Test create and update groupings.
     * @return void
     */
    public function test_create_update_groupings() {
        global $DB;

        $this->resetAfterTest(true);

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();

        $grouping1data = array();
        $grouping1data['courseid'] = $course->id;
        $grouping1data['name'] = 'Grouping 1 Test';
        $grouping1data['description'] = 'Grouping 1 Test description';
        $grouping1data['descriptionformat'] = FORMAT_MOODLE;
        $grouping1data['idnumber'] = 'TEST';

        $grouping1 = self::getDataGenerator()->create_grouping($grouping1data);

        $grouping1data['name'] = 'Another group';

        try {
            $groupings = core_group_external::create_groupings(array($grouping1data));
            $this->fail('Exception expected due to already existing idnumber.');
        } catch (moodle_exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $this->assertEquals(get_string('idnumbertaken', 'error'), $e->getMessage());
        }

        // No exception should be triggered.
        $grouping1data['id'] = $grouping1->id;
        $grouping1data['idnumber'] = 'CHANGED';
        unset($grouping1data['courseid']);
        core_group_external::update_groupings(array($grouping1data));

        $grouping2data = array();
        $grouping2data['courseid'] = $course->id;
        $grouping2data['name'] = 'Grouping 2 Test';
        $grouping2data['description'] = 'Grouping 2 Test description';
        $grouping2data['descriptionformat'] = FORMAT_MOODLE;
        $grouping2data['idnumber'] = 'TEST';

        $grouping2 = self::getDataGenerator()->create_grouping($grouping2data);

        $grouping2data['id'] = $grouping2->id;
        $grouping2data['idnumber'] = 'CHANGED';
        unset($grouping2data['courseid']);
        try {
            $groupings = core_group_external::update_groupings(array($grouping2data));
            $this->fail('Exception expected due to already existing idnumber.');
        } catch (moodle_exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $this->assertEquals(get_string('idnumbertaken', 'error'), $e->getMessage());
        }
    }

    /**
     * Test get_groupings
     */
    public function test_get_groupings() {
        global $DB;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();

        $groupingdata = array();
        $groupingdata['courseid'] = $course->id;
        $groupingdata['name'] = 'Grouping Test';
        $groupingdata['description'] = 'Grouping Test description';
        $groupingdata['descriptionformat'] = FORMAT_MOODLE;

        $grouping = self::getDataGenerator()->create_grouping($groupingdata);

        // Set the required capabilities by the external function.
        $context = context_course::instance($course->id);
        $roleid = $this->assignUserCapability('moodle/course:managegroups', $context->id);
        $this->assignUserCapability('moodle/course:view', $context->id, $roleid);

        // Call the external function without specifying the optional parameter.
        $groupings = core_group_external::get_groupings(array($grouping->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $groupings = external_api::clean_returnvalue(core_group_external::get_groupings_returns(), $groupings);

        $this->assertEquals(1, count($groupings));

        $group1data = array();
        $group1data['courseid'] = $course->id;
        $group1data['name'] = 'Group Test 1';
        $group1data['description'] = 'Group Test 1 description';
        $group1data['descriptionformat'] = FORMAT_MOODLE;
        $group2data = array();
        $group2data['courseid'] = $course->id;
        $group2data['name'] = 'Group Test 2';
        $group2data['description'] = 'Group Test 2 description';
        $group2data['descriptionformat'] = FORMAT_MOODLE;

        $group1 = self::getDataGenerator()->create_group($group1data);
        $group2 = self::getDataGenerator()->create_group($group2data);

        groups_assign_grouping($grouping->id, $group1->id);
        groups_assign_grouping($grouping->id, $group2->id);

        // Call the external function specifying that groups are returned.
        $groupings = core_group_external::get_groupings(array($grouping->id), true);
        // We need to execute the return values cleaning process to simulate the web service server.
        $groupings = external_api::clean_returnvalue(core_group_external::get_groupings_returns(), $groupings);
        $this->assertEquals(1, count($groupings));
        $this->assertEquals(2, count($groupings[0]['groups']));
        foreach ($groupings[0]['groups'] as $group) {
            $dbgroup = $DB->get_record('groups', array('id' => $group['id']), '*', MUST_EXIST);
            $dbgroupinggroups = $DB->get_record('groupings_groups',
                                                array('groupingid' => $groupings[0]['id'],
                                                      'groupid' => $group['id']),
                                                '*', MUST_EXIST);
            switch ($dbgroup->name) {
                case $group1->name:
                    $groupdescription = $group1->description;
                    $groupcourseid = $group1->courseid;
                    break;
                case $group2->name:
                    $groupdescription = $group2->description;
                    $groupcourseid = $group2->courseid;
                    break;
                default:
                    throw new moodle_exception('unknowgroupname');
                    break;
            }
            $this->assertEquals($dbgroup->description, $groupdescription);
            $this->assertEquals($dbgroup->courseid, $groupcourseid);
        }
    }

    /**
     * Test delete_groupings.
     */
    public function test_delete_groupings() {
        global $DB;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();

        $groupingdata1 = array();
        $groupingdata1['courseid'] = $course->id;
        $groupingdata1['name'] = 'Grouping Test';
        $groupingdata1['description'] = 'Grouping Test description';
        $groupingdata1['descriptionformat'] = FORMAT_MOODLE;
        $groupingdata2 = array();
        $groupingdata2['courseid'] = $course->id;
        $groupingdata2['name'] = 'Grouping Test';
        $groupingdata2['description'] = 'Grouping Test description';
        $groupingdata2['descriptionformat'] = FORMAT_MOODLE;
        $groupingdata3 = array();
        $groupingdata3['courseid'] = $course->id;
        $groupingdata3['name'] = 'Grouping Test';
        $groupingdata3['description'] = 'Grouping Test description';
        $groupingdata3['descriptionformat'] = FORMAT_MOODLE;

        $grouping1 = self::getDataGenerator()->create_grouping($groupingdata1);
        $grouping2 = self::getDataGenerator()->create_grouping($groupingdata2);
        $grouping3 = self::getDataGenerator()->create_grouping($groupingdata3);

        // Set the required capabilities by the external function.
        $context = context_course::instance($course->id);
        $roleid = $this->assignUserCapability('moodle/course:managegroups', $context->id);
        $this->assignUserCapability('moodle/course:view', $context->id, $roleid);

        // Checks against DB values.
        $groupingstotal = $DB->count_records('groupings', array());
        $this->assertEquals(3, $groupingstotal);

        // Call the external function.
        core_group_external::delete_groupings(array($grouping1->id, $grouping2->id));

        // Checks against DB values.
        $groupingstotal = $DB->count_records('groupings', array());
        $this->assertEquals(1, $groupingstotal);

        // Call without required capability.
        $this->unassignUserCapability('moodle/course:managegroups', $context->id, $roleid);

        $this->expectException(\required_capability_exception::class);
        core_group_external::delete_groupings(array($grouping3->id));
    }

    /**
     * Test get_groups
     */
    public function test_get_course_user_groups() {
        global $DB;

        $this->resetAfterTest(true);

        $student1 = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        $course = self::getDataGenerator()->create_course();
        $anothercourse = self::getDataGenerator()->create_course();
        $emptycourse = self::getDataGenerator()->create_course();

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student1->id, $anothercourse->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, $studentrole->id);

        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($teacher->id, $emptycourse->id, $teacherrole->id);

        $group1data = array();
        $group1data['courseid'] = $course->id;
        $group1data['name'] = 'Group Test 1';
        $group1data['description'] = 'Group Test 1 description';
        $group1data['idnumber'] = 'TEST1';
        $group2data = array();
        $group2data['courseid'] = $course->id;
        $group2data['name'] = 'Group Test 2';
        $group2data['description'] = 'Group Test 2 description';
        $group3data = array();
        $group3data['courseid'] = $anothercourse->id;
        $group3data['name'] = 'Group Test 3';
        $group3data['description'] = 'Group Test 3 description';
        $group3data['idnumber'] = 'TEST3';
        $group1 = self::getDataGenerator()->create_group($group1data);
        $group2 = self::getDataGenerator()->create_group($group2data);
        $group3 = self::getDataGenerator()->create_group($group3data);

        groups_add_member($group1->id, $student1->id);
        groups_add_member($group1->id, $student2->id);
        groups_add_member($group2->id, $student1->id);
        groups_add_member($group3->id, $student1->id);

        // Create a grouping.
        $groupingdata = array();
        $groupingdata['courseid'] = $course->id;
        $groupingdata['name'] = 'Grouping Test';
        $groupingdata['description'] = 'Grouping Test description';
        $groupingdata['descriptionformat'] = FORMAT_MOODLE;

        $grouping = self::getDataGenerator()->create_grouping($groupingdata);
        // Grouping only containing group1.
        groups_assign_grouping($grouping->id, $group1->id);

        $this->setUser($student1);

        $groups = core_group_external::get_course_user_groups($course->id, $student1->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_course_user_groups_returns(), $groups);
        // Check that I see my groups.
        $this->assertCount(2, $groups['groups']);
        $this->assertEquals($course->id, $groups['groups'][0]['courseid']);
        $this->assertEquals($course->id, $groups['groups'][1]['courseid']);

        // Check that I only see my groups inside the given grouping.
        $groups = core_group_external::get_course_user_groups($course->id, $student1->id, $grouping->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_course_user_groups_returns(), $groups);
        // Check that I see my groups in the grouping.
        $this->assertCount(1, $groups['groups']);
        $this->assertEquals($group1->id, $groups['groups'][0]['id']);


        // Check optional parameters (all student 1 courses and current user).
        $groups = core_group_external::get_course_user_groups();
        $groups = external_api::clean_returnvalue(core_group_external::get_course_user_groups_returns(), $groups);
        // Check that I see my groups in all my courses.
        $this->assertCount(3, $groups['groups']);

        $this->setUser($student2);
        $groups = core_group_external::get_course_user_groups($course->id, $student2->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_course_user_groups_returns(), $groups);
        // Check that I see my groups.
        $this->assertCount(1, $groups['groups']);

        $this->assertEquals($group1data['name'], $groups['groups'][0]['name']);
        $this->assertEquals($group1data['description'], $groups['groups'][0]['description']);
        $this->assertEquals($group1data['idnumber'], $groups['groups'][0]['idnumber']);

        $this->setUser($teacher);
        $groups = core_group_external::get_course_user_groups($course->id, $student1->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_course_user_groups_returns(), $groups);
        // Check that a teacher can see student groups in given course.
        $this->assertCount(2, $groups['groups']);

        $groups = core_group_external::get_course_user_groups($course->id, $student2->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_course_user_groups_returns(), $groups);
        // Check that a teacher can see student groups in given course.
        $this->assertCount(1, $groups['groups']);

        $groups = core_group_external::get_course_user_groups(0, $student1->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_course_user_groups_returns(), $groups);
        // Check that a teacher can see student groups in all the user courses if the teacher is enrolled in the course.
        $this->assertCount(2, $groups['groups']); // Teacher only see groups in first course.
        $this->assertCount(1, $groups['warnings']); // Enrolment warnings.
        $this->assertEquals('1', $groups['warnings'][0]['warningcode']);

        // Enrol teacher in second course.
        $this->getDataGenerator()->enrol_user($teacher->id, $anothercourse->id, $teacherrole->id);
        $groups = core_group_external::get_course_user_groups(0, $student1->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_course_user_groups_returns(), $groups);
        // Check that a teacher can see student groups in all the user courses if the teacher is enrolled in the course.
        $this->assertCount(3, $groups['groups']);

        // Check permissions.
        $this->setUser($student1);

        // Student can's see other students group.
        $groups = core_group_external::get_course_user_groups($course->id, $student2->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_course_user_groups_returns(), $groups);
        $this->assertCount(1, $groups['warnings']);
        $this->assertEquals('cannotmanagegroups', $groups['warnings'][0]['warningcode']);

        // Not enrolled course.
        $groups = core_group_external::get_course_user_groups($emptycourse->id, $student2->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_course_user_groups_returns(), $groups);
        $this->assertCount(1, $groups['warnings']);
        $this->assertEquals('1', $groups['warnings'][0]['warningcode']);

        $this->setUser($teacher);
        // Check user checking not enrolled in given course.
        $groups = core_group_external::get_course_user_groups($emptycourse->id, $student1->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_course_user_groups_returns(), $groups);
        $this->assertCount(1, $groups['warnings']);
        $this->assertEquals('notenrolled', $groups['warnings'][0]['warningcode']);
    }

    /**
     * Test get_activity_allowed_groups
     */
    public function test_get_activity_allowed_groups() {
        global $DB;

        $this->resetAfterTest(true);

        $generator = self::getDataGenerator();

        $student = $generator->create_user();
        $otherstudent = $generator->create_user();
        $teacher = $generator->create_user();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $generator->enrol_user($student->id, $course->id, $studentrole->id);
        $generator->enrol_user($otherstudent->id, $othercourse->id, $studentrole->id);
        $generator->enrol_user($teacher->id, $course->id, $teacherrole->id);

        $forum1 = $generator->create_module("forum", array('course' => $course->id), array('groupmode' => VISIBLEGROUPS));
        $forum2 = $generator->create_module("forum", array('course' => $othercourse->id));
        $forum3 = $generator->create_module("forum", array('course' => $course->id), array('visible' => 0));

        // Request data for tests.
        $cm1 = get_coursemodule_from_instance("forum", $forum1->id);
        $cm2 = get_coursemodule_from_instance("forum", $forum2->id);
        $cm3 = get_coursemodule_from_instance("forum", $forum3->id);

        $group1data = array();
        $group1data['courseid'] = $course->id;
        $group1data['name'] = 'Group Test 1';
        $group1data['description'] = 'Group Test 1 description';
        $group1data['idnumber'] = 'TEST1';
        $group2data = array();
        $group2data['courseid'] = $course->id;
        $group2data['name'] = 'Group Test 2';
        $group2data['description'] = 'Group Test 2 description';
        $group2data['idnumber'] = 'TEST2';
        $group1 = $generator->create_group($group1data);
        $group2 = $generator->create_group($group2data);

        groups_add_member($group1->id, $student->id);
        groups_add_member($group2->id, $student->id);

        $this->setUser($student);

        // First try possible errors.
        try {
            $data = core_group_external::get_activity_allowed_groups($cm2->id);
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        try {
            $data = core_group_external::get_activity_allowed_groups($cm3->id);
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Retrieve my groups.
        $groups = core_group_external::get_activity_allowed_groups($cm1->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_activity_allowed_groups_returns(), $groups);
        $this->assertCount(2, $groups['groups']);
        $this->assertFalse($groups['canaccessallgroups']);

        foreach ($groups['groups'] as $group) {
            if ($group['name'] == $group1data['name']) {
                $this->assertEquals($group1data['description'], $group['description']);
                $this->assertEquals($group1data['idnumber'], $group['idnumber']);
            } else {
                $this->assertEquals($group2data['description'], $group['description']);
                $this->assertEquals($group2data['idnumber'], $group['idnumber']);
            }
        }

        $this->setUser($teacher);
        // Retrieve other users groups.
        $groups = core_group_external::get_activity_allowed_groups($cm1->id, $student->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_activity_allowed_groups_returns(), $groups);
        $this->assertCount(2, $groups['groups']);
        // We are checking the $student passed as parameter so this will return false.
        $this->assertFalse($groups['canaccessallgroups']);

        // Check warnings. Trying to get groups for a user not enrolled in course.
        $groups = core_group_external::get_activity_allowed_groups($cm1->id, $otherstudent->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_activity_allowed_groups_returns(), $groups);
        $this->assertCount(1, $groups['warnings']);
        $this->assertFalse($groups['canaccessallgroups']);

        // Checking teacher groups.
        $groups = core_group_external::get_activity_allowed_groups($cm1->id);
        $groups = external_api::clean_returnvalue(core_group_external::get_activity_allowed_groups_returns(), $groups);
        $this->assertCount(2, $groups['groups']);
        // Teachers by default can access all groups.
        $this->assertTrue($groups['canaccessallgroups']);
    }

    /**
     * Test get_activity_groupmode
     */
    public function test_get_activity_groupmode() {
        global $DB;

        $this->resetAfterTest(true);

        $generator = self::getDataGenerator();

        $student = $generator->create_user();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $generator->enrol_user($student->id, $course->id, $studentrole->id);

        $forum1 = $generator->create_module("forum", array('course' => $course->id), array('groupmode' => VISIBLEGROUPS));
        $forum2 = $generator->create_module("forum", array('course' => $othercourse->id));
        $forum3 = $generator->create_module("forum", array('course' => $course->id), array('visible' => 0));

        // Request data for tests.
        $cm1 = get_coursemodule_from_instance("forum", $forum1->id);
        $cm2 = get_coursemodule_from_instance("forum", $forum2->id);
        $cm3 = get_coursemodule_from_instance("forum", $forum3->id);

        $this->setUser($student);

        $data = core_group_external::get_activity_groupmode($cm1->id);
        $data = external_api::clean_returnvalue(core_group_external::get_activity_groupmode_returns(), $data);
        $this->assertEquals(VISIBLEGROUPS, $data['groupmode']);

        try {
            $data = core_group_external::get_activity_groupmode($cm2->id);
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        try {
            $data = core_group_external::get_activity_groupmode($cm3->id);
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

    }

    /**
     * Test add_group_members.
     */
    public function test_add_group_members() {
        global $DB;

        $this->resetAfterTest(true);

        $student1 = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $student3 = self::getDataGenerator()->create_user();

        $course = self::getDataGenerator()->create_course();

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student3->id, $course->id, $studentrole->id);

        $group1data = array();
        $group1data['courseid'] = $course->id;
        $group1data['name'] = 'Group Test 1';
        $group1data['description'] = 'Group Test 1 description';
        $group1data['idnumber'] = 'TEST1';
        $group1 = self::getDataGenerator()->create_group($group1data);

        // Checks against DB values.
        $memberstotal = $DB->count_records('groups_members', ['groupid' => $group1->id]);
        $this->assertEquals(0, $memberstotal);

        // Set the required capabilities by the external function.
        $context = context_course::instance($course->id);
        $roleid = $this->assignUserCapability('moodle/course:managegroups', $context->id);
        $this->assignUserCapability('moodle/course:view', $context->id, $roleid);

        core_group_external::add_group_members([
            'members' => [
                'groupid' => $group1->id,
                'userid' => $student1->id,
            ]
        ]);
        core_group_external::add_group_members([
            'members' => [
                'groupid' => $group1->id,
                'userid' => $student2->id,
            ]
        ]);
        core_group_external::add_group_members([
            'members' => [
                'groupid' => $group1->id,
                'userid' => $student3->id,
            ]
        ]);

        // Checks against DB values.
        $memberstotal = $DB->count_records('groups_members', ['groupid' => $group1->id]);
        $this->assertEquals(3, $memberstotal);
    }

    /**
     * Test delete_group_members.
     */
    public function test_delete_group_members() {
        global $DB;

        $this->resetAfterTest(true);

        $student1 = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $student3 = self::getDataGenerator()->create_user();

        $course = self::getDataGenerator()->create_course();

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student3->id, $course->id, $studentrole->id);

        $group1data = array();
        $group1data['courseid'] = $course->id;
        $group1data['name'] = 'Group Test 1';
        $group1data['description'] = 'Group Test 1 description';
        $group1data['idnumber'] = 'TEST1';
        $group1 = self::getDataGenerator()->create_group($group1data);

        groups_add_member($group1->id, $student1->id);
        groups_add_member($group1->id, $student2->id);
        groups_add_member($group1->id, $student3->id);

        // Checks against DB values.
        $memberstotal = $DB->count_records('groups_members', ['groupid' => $group1->id]);
        $this->assertEquals(3, $memberstotal);

        // Set the required capabilities by the external function.
        $context = context_course::instance($course->id);
        $roleid = $this->assignUserCapability('moodle/course:managegroups', $context->id);
        $this->assignUserCapability('moodle/course:view', $context->id, $roleid);

        core_group_external::delete_group_members([
            'members' => [
                'groupid' => $group1->id,
                'userid' => $student2->id,
            ]
        ]);

        // Checks against DB values.
        $memberstotal = $DB->count_records('groups_members', ['groupid' => $group1->id]);
        $this->assertEquals(2, $memberstotal);
    }
}
