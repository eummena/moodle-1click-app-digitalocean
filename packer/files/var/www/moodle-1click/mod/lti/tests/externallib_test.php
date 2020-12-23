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
 * External tool module external functions tests
 *
 * @package    mod_lti
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/lti/lib.php');

/**
 * External tool module external functions tests
 *
 * @package    mod_lti
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_lti_external_testcase extends externallib_advanced_testcase {

    /**
     * Set up for every test
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Sets up some basic test data including course, users, roles, and an lti instance, for use in some tests.
     * @return array
     */
    protected function setup_test_data() {
        global $DB;
        $this->setAdminUser();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $lti = $this->getDataGenerator()->create_module(
            'lti',
            ['course' => $course->id, 'toolurl' => 'http://localhost/not/real/tool.php']
        );
        $context = context_module::instance($lti->cmid);
        $cm = get_coursemodule_from_instance('lti', $lti->id);

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id, 'manual');

        return [
            'course' => $course,
            'lti' => $lti,
            'context' => $context,
            'cm' => $cm,
            'student' => $student,
            'teacher' => $teacher,
            'studentrole' => $studentrole,
            'teacherrole' => $teacherrole
        ];
    }

    /**
     * Generate a tool type.
     *
     * @param string $uniqueid Each tool type needs a different base url. Provide a unique string for every tool type created.
     * @param int|null $toolproxyid Optional proxy to associate with tool type.
     * @return stdClass A tool type.
     */
    protected function generate_tool_type(string $uniqueid, int $toolproxyid = null) : stdClass {
        // Create a tool type.
        $type = new stdClass();
        $type->state = LTI_TOOL_STATE_CONFIGURED;
        $type->name = "Test tool $uniqueid";
        $type->description = "Example description $uniqueid";
        $type->toolproxyid = $toolproxyid;
        $type->baseurl = $this->getExternalTestFileUrl("/test$uniqueid.html");
        lti_add_type($type, new stdClass());
        return $type;
    }

    /**
     * Generate a tool proxy.
     *
     * @param string $uniqueid Each tool proxy needs a different reg url. Provide a unique string for every tool proxy created.
     * @return stdClass A tool proxy.
     */
    protected function generate_tool_proxy(string $uniqueid) : stdClass {
        // Create a tool proxy.
        $proxy = mod_lti_external::create_tool_proxy("Test proxy $uniqueid",
                $this->getExternalTestFileUrl("/proxy$uniqueid.html"), array(), array());
        $proxy = (object)external_api::clean_returnvalue(mod_lti_external::create_tool_proxy_returns(), $proxy);
        return $proxy;
    }

    /**
     * Test get_tool_proxies.
     */
    public function test_mod_lti_get_tool_proxies() {
        // Create two tool proxies. One to associate with tool, and one to leave orphaned.
        $this->setAdminUser();
        $proxy = $this->generate_tool_proxy("1");
        $orphanedproxy = $this->generate_tool_proxy("2");
        $this->generate_tool_type("1", $proxy->id); // Associate proxy 1 with tool type.

        // Fetch all proxies.
        $proxies = mod_lti_external::get_tool_proxies(false);
        $proxies = external_api::clean_returnvalue(mod_lti_external::get_tool_proxies_returns(), $proxies);

        $this->assertCount(2, $proxies);
        $this->assertEqualsCanonicalizing([(array) $proxy, (array) $orphanedproxy], $proxies);
    }

    /**
     * Test get_tool_proxies with orphaned proxies only.
     */
    public function test_mod_lti_get_orphaned_tool_proxies() {
        // Create two tool proxies. One to associate with tool, and one to leave orphaned.
        $this->setAdminUser();
        $proxy = $this->generate_tool_proxy("1");
        $orphanedproxy = $this->generate_tool_proxy("2");
        $this->generate_tool_type("1", $proxy->id); // Associate proxy 1 with tool type.

        // Fetch all proxies.
        $proxies = mod_lti_external::get_tool_proxies(true);
        $proxies = external_api::clean_returnvalue(mod_lti_external::get_tool_proxies_returns(), $proxies);

        $this->assertCount(1, $proxies);
        $this->assertEqualsCanonicalizing([(array) $orphanedproxy], $proxies);
    }

    /**
     * Test get_tool_launch_data.
     */
    public function test_get_tool_launch_data() {
        global $USER;

        [
            'course' => $course,
            'lti' => $lti
        ] = $this->setup_test_data();

        $result = mod_lti_external::get_tool_launch_data($lti->id);
        $result = external_api::clean_returnvalue(mod_lti_external::get_tool_launch_data_returns(), $result);

        // Basic test, the function returns what it's expected.
        self::assertEquals($lti->toolurl, $result['endpoint']);
        self::assertCount(36, $result['parameters']);

        // Check some parameters.
        $parameters = array();
        foreach ($result['parameters'] as $param) {
            $parameters[$param['name']] = $param['value'];
        }
        self::assertEquals($lti->resourcekey, $parameters['oauth_consumer_key']);
        self::assertEquals($course->fullname, $parameters['context_title']);
        self::assertEquals($course->shortname, $parameters['context_label']);
        self::assertEquals($USER->id, $parameters['user_id']);
        self::assertEquals($USER->firstname, $parameters['lis_person_name_given']);
        self::assertEquals($USER->lastname, $parameters['lis_person_name_family']);
        self::assertEquals(fullname($USER), $parameters['lis_person_name_full']);
        self::assertEquals($USER->username, $parameters['ext_user_username']);
        self::assertEquals("phpunit", $parameters['tool_consumer_instance_name']);
        self::assertEquals("PHPUnit test site", $parameters['tool_consumer_instance_description']);
    }

    /**
     * Test get_ltis_by_courses.
     */
    public function test_mod_lti_get_ltis_by_courses() {
        [
            'course' => $course,
            'lti' => $lti,
            'student' => $student,
            'teacher' => $teacher,
            'studentrole' => $studentrole,
        ] = $this->setup_test_data();

        // Create additional course.
        $course2 = self::getDataGenerator()->create_course();

        // Second lti.
        $record = new stdClass();
        $record->course = $course2->id;
        $lti2 = self::getDataGenerator()->create_module('lti', $record);

        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $student->id, $studentrole->id);

        self::setUser($student);

        $returndescription = mod_lti_external::get_ltis_by_courses_returns();

        // Create what we expect to be returned when querying the two courses.
        // First for the student user.
        $expectedfields = array('id', 'coursemodule', 'course', 'name', 'intro', 'introformat', 'introfiles',
            'launchcontainer', 'showtitlelaunch', 'showdescriptionlaunch', 'icon', 'secureicon');

        // Add expected coursemodule and data.
        $lti1 = $lti;
        $lti1->coursemodule = $lti1->cmid;
        $lti1->introformat = 1;
        $lti1->section = 0;
        $lti1->visible = true;
        $lti1->groupmode = 0;
        $lti1->groupingid = 0;
        $lti1->introfiles = [];

        $lti2->coursemodule = $lti2->cmid;
        $lti2->introformat = 1;
        $lti2->section = 0;
        $lti2->visible = true;
        $lti2->groupmode = 0;
        $lti2->groupingid = 0;
        $lti2->introfiles = [];

        foreach ($expectedfields as $field) {
            $expected1[$field] = $lti1->{$field};
            $expected2[$field] = $lti2->{$field};
        }

        $expectedltis = array($expected2, $expected1);

        // Call the external function passing course ids.
        $result = mod_lti_external::get_ltis_by_courses(array($course2->id, $course->id));
        $result = external_api::clean_returnvalue($returndescription, $result);

        $this->assertEquals($expectedltis, $result['ltis']);
        $this->assertCount(0, $result['warnings']);

        // Call the external function without passing course id.
        $result = mod_lti_external::get_ltis_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedltis, $result['ltis']);
        $this->assertCount(0, $result['warnings']);

        // Unenrol user from second course and alter expected ltis.
        $enrol->unenrol_user($instance2, $student->id);
        array_shift($expectedltis);

        // Call the external function without passing course id.
        $result = mod_lti_external::get_ltis_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedltis, $result['ltis']);

        // Call for the second course we unenrolled the user from, expected warning.
        $result = mod_lti_external::get_ltis_by_courses(array($course2->id));
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('1', $result['warnings'][0]['warningcode']);
        $this->assertEquals($course2->id, $result['warnings'][0]['itemid']);

        // Now, try as a teacher for getting all the additional fields.
        self::setUser($teacher);

        $additionalfields = array('timecreated', 'timemodified', 'typeid', 'toolurl', 'securetoolurl',
            'instructorchoicesendname', 'instructorchoicesendemailaddr', 'instructorchoiceallowroster',
            'instructorchoiceallowsetting', 'instructorcustomparameters', 'instructorchoiceacceptgrades', 'grade',
            'resourcekey', 'password', 'debuglaunch', 'servicesalt', 'visible', 'groupmode', 'groupingid');

        foreach ($additionalfields as $field) {
            $expectedltis[0][$field] = $lti1->{$field};
        }

        $result = mod_lti_external::get_ltis_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedltis, $result['ltis']);

        // Admin also should get all the information.
        self::setAdminUser();

        $result = mod_lti_external::get_ltis_by_courses(array($course->id));
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedltis, $result['ltis']);

        // Now, prohibit capabilities.
        $this->setUser($student);
        $contextcourse1 = context_course::instance($course->id);
        // Prohibit capability = mod:lti:view on Course1 for students.
        assign_capability('mod/lti:view', CAP_PROHIBIT, $studentrole->id, $contextcourse1->id);
        // Empty all the caches that may be affected by this change.
        accesslib_clear_all_caches_for_unit_testing();
        course_modinfo::clear_instance_cache();

        $ltis = mod_lti_external::get_ltis_by_courses(array($course->id));
        $ltis = external_api::clean_returnvalue(mod_lti_external::get_ltis_by_courses_returns(), $ltis);
        $this->assertCount(0, $ltis['ltis']);
    }

    /**
     * Test view_lti with an invalid instance id.
     */
    public function test_view_lti_invalid_instanceid() {
        $this->expectException(moodle_exception::class);
        mod_lti_external::view_lti(0);
    }

    /**
     * Test view_lti as a user who is not enrolled in the course.
     */
    public function test_view_lti_no_enrolment() {
        [
            'lti' => $lti
        ] = $this->setup_test_data();

        // Test not-enrolled user.
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);

        $this->expectException(moodle_exception::class);
        mod_lti_external::view_lti($lti->id);
    }

    /**
     * Test view_lti for a user without the mod/lti:view capability.
     */
    public function test_view_lti_no_capability() {
        [
            'lti' => $lti,
            'student' => $student,
            'studentrole' => $studentrole,
            'context' => $context,
        ] = $this->setup_test_data();

        $this->setUser($student);

        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/lti:view', CAP_PROHIBIT, $studentrole->id, $context->id);
        // Empty all the caches that may be affected by this change.
        accesslib_clear_all_caches_for_unit_testing();
        course_modinfo::clear_instance_cache();

        $this->expectException(moodle_exception::class);
        mod_lti_external::view_lti($lti->id);
    }

    /**
     * Test view_lti for a user with the mod/lti:view capability in the course.
     */
    public function test_view_lti() {
        [
            'lti' => $lti,
            'context' => $context,
            'cm' => $cm,
            'student' => $student,
        ] = $this->setup_test_data();

        // Test user with full capabilities.
        $this->setUser($student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_lti_external::view_lti($lti->id);
        // The value of the result isn't needed but validation is.
        external_api::clean_returnvalue(mod_lti_external::view_lti_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_lti\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodlelti = new moodle_url('/mod/lti/view.php', array('id' => $cm->id));
        $this->assertEquals($moodlelti, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test create_tool_proxy.
     */
    public function test_mod_lti_create_tool_proxy() {
        $this->setAdminUser();
        $capabilities = ['AA', 'BB'];
        $proxy = mod_lti_external::create_tool_proxy('Test proxy', $this->getExternalTestFileUrl('/test.html'), $capabilities, []);
        $proxy = (object) external_api::clean_returnvalue(mod_lti_external::create_tool_proxy_returns(), $proxy);

        $this->assertEquals('Test proxy', $proxy->name);
        $this->assertEquals($this->getExternalTestFileUrl('/test.html'), $proxy->regurl);
        $this->assertEquals(LTI_TOOL_PROXY_STATE_PENDING, $proxy->state);
        $this->assertEquals(implode("\n", $capabilities), $proxy->capabilityoffered);
    }

    /**
     * Test create_tool_proxy with a duplicate url.
     */
    public function test_mod_lti_create_tool_proxy_duplicateurl() {
        $this->setAdminUser();
        mod_lti_external::create_tool_proxy('Test proxy 1', $this->getExternalTestFileUrl('/test.html'), array(), array());

        $this->expectException(moodle_exception::class);
        mod_lti_external::create_tool_proxy('Test proxy 2', $this->getExternalTestFileUrl('/test.html'), array(), array());
    }

    /**
     * Test create_tool_proxy for a user without the required capability.
     */
    public function test_mod_lti_create_tool_proxy_without_capability() {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $this->expectException(required_capability_exception::class);
        mod_lti_external::create_tool_proxy('Test proxy', $this->getExternalTestFileUrl('/test.html'), array(), array());
    }

    /**
     * Test delete_tool_proxy.
     */
    public function test_mod_lti_delete_tool_proxy() {
        $this->setAdminUser();
        $proxy = mod_lti_external::create_tool_proxy('Test proxy', $this->getExternalTestFileUrl('/test.html'), array(), array());
        $proxy = (object) external_api::clean_returnvalue(mod_lti_external::create_tool_proxy_returns(), $proxy);
        $this->assertNotEmpty(lti_get_tool_proxy($proxy->id));

        $proxy = mod_lti_external::delete_tool_proxy($proxy->id);
        $proxy = (object) external_api::clean_returnvalue(mod_lti_external::delete_tool_proxy_returns(), $proxy);

        $this->assertEquals('Test proxy', $proxy->name);
        $this->assertEquals($this->getExternalTestFileUrl('/test.html'), $proxy->regurl);
        $this->assertEquals(LTI_TOOL_PROXY_STATE_PENDING, $proxy->state);
        $this->assertEmpty(lti_get_tool_proxy($proxy->id));
    }

    /**
     * Test get_tool_proxy_registration_request.
     */
    public function test_mod_lti_get_tool_proxy_registration_request() {
        $this->setAdminUser();
        $proxy = mod_lti_external::create_tool_proxy('Test proxy', $this->getExternalTestFileUrl('/test.html'), array(), array());
        $proxy = (object) external_api::clean_returnvalue(mod_lti_external::create_tool_proxy_returns(), $proxy);

        $request = mod_lti_external::get_tool_proxy_registration_request($proxy->id);
        $request = external_api::clean_returnvalue(mod_lti_external::get_tool_proxy_registration_request_returns(),
            $request);

        $this->assertEquals('ToolProxyRegistrationRequest', $request['lti_message_type']);
        $this->assertEquals('LTI-2p0', $request['lti_version']);
    }

    /**
     * Test get_tool_types.
     */
    public function test_mod_lti_get_tool_types() {
        // Create a tool proxy.
        $this->setAdminUser();
        $proxy = mod_lti_external::create_tool_proxy('Test proxy', $this->getExternalTestFileUrl('/test.html'), array(), array());
        $proxy = (object) external_api::clean_returnvalue(mod_lti_external::create_tool_proxy_returns(), $proxy);

        // Create a tool type, associated with that proxy.
        $type = new stdClass();
        $data = new stdClass();
        $type->state = LTI_TOOL_STATE_CONFIGURED;
        $type->name = "Test tool";
        $type->description = "Example description";
        $type->toolproxyid = $proxy->id;
        $type->baseurl = $this->getExternalTestFileUrl('/test.html');
        lti_add_type($type, $data);

        $types = mod_lti_external::get_tool_types($proxy->id);
        $types = external_api::clean_returnvalue(mod_lti_external::get_tool_types_returns(), $types);

        $this->assertCount(1, $types);
        $type = $types[0];
        $this->assertEquals('Test tool', $type['name']);
        $this->assertEquals('Example description', $type['description']);
    }

    /**
     * Test create_tool_type.
     */
    public function test_mod_lti_create_tool_type() {
        $this->setAdminUser();
        $type = mod_lti_external::create_tool_type($this->getExternalTestFileUrl('/ims_cartridge_basic_lti_link.xml'), '', '');
        $type = external_api::clean_returnvalue(mod_lti_external::create_tool_type_returns(), $type);

        $this->assertEquals('Example tool', $type['name']);
        $this->assertEquals('Example tool description', $type['description']);
        $this->assertEquals('https://download.moodle.org/unittest/test.jpg', $type['urls']['icon']);
        $typeentry = lti_get_type($type['id']);
        $this->assertEquals('http://www.example.com/lti/provider.php', $typeentry->baseurl);
        $config = lti_get_type_config($type['id']);
        $this->assertTrue(isset($config['sendname']));
        $this->assertTrue(isset($config['sendemailaddr']));
        $this->assertTrue(isset($config['acceptgrades']));
        $this->assertTrue(isset($config['forcessl']));
    }

    /**
     * Test create_tool_type failure from non existent file.
     */
    public function test_mod_lti_create_tool_type_nonexistant_file() {
        $this->expectException(moodle_exception::class);
        mod_lti_external::create_tool_type($this->getExternalTestFileUrl('/doesntexist.xml'), '', '');
    }

    /**
     * Test create_tool_type failure from xml that is not a cartridge.
     */
    public function test_mod_lti_create_tool_type_bad_file() {
        $this->expectException(moodle_exception::class);
        mod_lti_external::create_tool_type($this->getExternalTestFileUrl('/rsstest.xml'), '', '');
    }

    /**
     * Test create_tool_type as a user without the required capability.
     */
    public function test_mod_lti_create_tool_type_without_capability() {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $this->expectException(required_capability_exception::class);
        mod_lti_external::create_tool_type($this->getExternalTestFileUrl('/ims_cartridge_basic_lti_link.xml'), '', '');
    }

    /**
     * Test update_tool_type.
     */
    public function test_mod_lti_update_tool_type() {
        $this->setAdminUser();
        $type = mod_lti_external::create_tool_type($this->getExternalTestFileUrl('/ims_cartridge_basic_lti_link.xml'), '', '');
        $type = external_api::clean_returnvalue(mod_lti_external::create_tool_type_returns(), $type);

        $type = mod_lti_external::update_tool_type($type['id'], 'New name', 'New description', LTI_TOOL_STATE_PENDING);
        $type = external_api::clean_returnvalue(mod_lti_external::update_tool_type_returns(), $type);

        $this->assertEquals('New name', $type['name']);
        $this->assertEquals('New description', $type['description']);
        $this->assertEquals('Pending', $type['state']['text']);
    }

    /**
     * Test delete_tool_type for a user with the required capability.
     */
    public function test_mod_lti_delete_tool_type() {
        $this->setAdminUser();
        $type = mod_lti_external::create_tool_type($this->getExternalTestFileUrl('/ims_cartridge_basic_lti_link.xml'), '', '');
        $type = external_api::clean_returnvalue(mod_lti_external::create_tool_type_returns(), $type);
        $this->assertNotEmpty(lti_get_type($type['id']));

        $type = mod_lti_external::delete_tool_type($type['id']);
        $type = external_api::clean_returnvalue(mod_lti_external::delete_tool_type_returns(), $type);
        $this->assertEmpty(lti_get_type($type['id']));
    }

    /**
     * Test delete_tool_type for a user without the required capability.
     */
    public function test_mod_lti_delete_tool_type_without_capability() {
        $this->setAdminUser();
        $type = mod_lti_external::create_tool_type($this->getExternalTestFileUrl('/ims_cartridge_basic_lti_link.xml'), '', '');
        $type = external_api::clean_returnvalue(mod_lti_external::create_tool_type_returns(), $type);
        $this->assertNotEmpty(lti_get_type($type['id']));

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $this->expectException(required_capability_exception::class);
        mod_lti_external::delete_tool_type($type['id']);
    }

    /**
     * Test is_cartridge.
     */
    public function test_mod_lti_is_cartridge() {
        $this->setAdminUser();
        $result = mod_lti_external::is_cartridge($this->getExternalTestFileUrl('/ims_cartridge_basic_lti_link.xml'));
        $result = external_api::clean_returnvalue(mod_lti_external::is_cartridge_returns(), $result);
        $this->assertTrue($result['iscartridge']);

        $result = mod_lti_external::is_cartridge($this->getExternalTestFileUrl('/test.html'));
        $result = external_api::clean_returnvalue(mod_lti_external::is_cartridge_returns(), $result);
        $this->assertFalse($result['iscartridge']);
    }
}
