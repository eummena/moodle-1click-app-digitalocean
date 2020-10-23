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
 * Tests for manager.
 *
 * @package    tool_usertours
 * @copyright  2016 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/helper_trait.php');

/**
 * Tests for step.
 *
 * @package    tool_usertours
 * @copyright  2016 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_usertours_manager_testcase extends advanced_testcase {
    // There are shared helpers for these tests in the helper trait.
    use tool_usertours_helper_trait;

    /**
     * @var moodle_database
     */
    protected $db;

    /**
     * Setup to store the DB reference.
     */
    public function setUp() {
        global $DB;

        $this->db = $DB;
    }

    /**
     * Tear down to restore the original DB reference.
     */
    public function tearDown() {
        global $DB;

        $DB = $this->db;
    }

    /**
     * Helper to mock the database.
     *
     * @return moodle_database
     */
    public function mock_database() {
        global $DB;

        $DB = $this->getMockBuilder('moodle_database')->getMock();

        return $DB;
    }

    /**
     * Data provider to ensure that all modification actions require the session key.
     *
     * @return array
     */
    public function sesskey_required_provider() {
        $tourid = rand(1, 100);
        $stepid = rand(1, 100);

        return [
                'Tour removal' => [
                        'delete_tour',
                        [$tourid],
                    ],
                'Step removal' => [
                        'delete_step',
                        [$stepid],
                    ],
                'Tour visibility' => [
                        'show_hide_tour',
                        [$tourid, true],
                    ],
                'Move step' => [
                        'move_step',
                        [$stepid],
                    ],
            ];
    }

    /**
     * Ensure that all modification actions require the session key.
     *
     * @dataProvider sesskey_required_provider
     * @param   string  $function   The function to test
     * @param   array   $arguments  The arguments to pass with it
     */
    public function test_sesskey_required($function, $arguments) {
        $manager = new \tool_usertours\manager();

        $rc = new \ReflectionClass('\tool_usertours\manager');
        $rcm = $rc->getMethod($function);
        $rcm->setAccessible(true);

        $this->expectException('moodle_exception');
        $rcm->invokeArgs($manager, $arguments);
    }

    /**
     * Data provider for test_move_tour
     *
     * @return array
     */
    public function move_tour_provider() {
        $alltours = [
            ['name' => 'Tour 1'],
            ['name' => 'Tour 2'],
            ['name' => 'Tour 3'],
        ];

        return [
            'Move up' => [
                $alltours,
                'Tour 2',
                \tool_usertours\helper::MOVE_UP,
                0,
            ],
            'Move down' => [
                $alltours,
                'Tour 2',
                \tool_usertours\helper::MOVE_DOWN,
                2,
            ],
            'Move up (first)' => [
                $alltours,
                'Tour 1',
                \tool_usertours\helper::MOVE_UP,
                0,
            ],
            'Move down (last)' => [
                $alltours,
                'Tour 3',
                \tool_usertours\helper::MOVE_DOWN,
                2,
            ],
        ];
    }

    /**
     * Test moving tours (changing sortorder)
     *
     * @dataProvider move_tour_provider
     *
     * @param array $alltours
     * @param string $movetourname
     * @param int $direction
     * @param int $expectedsortorder
     * @return void
     */
    public function test_move_tour($alltours, $movetourname, $direction, $expectedsortorder) {
        global $DB;

        $this->resetAfterTest();

        // Clear out existing tours so ours are the only ones, otherwise we can't predict the sortorder.
        $DB->delete_records('tool_usertours_tours');

        foreach ($alltours as $tourconfig) {
            $this->helper_create_tour((object) $tourconfig);
        }

        // Load our tour to move.
        $record = $DB->get_record('tool_usertours_tours', ['name' => $movetourname]);
        $tour = \tool_usertours\tour::load_from_record($record);

        // Call protected method via reflection.
        $class = new ReflectionClass(\tool_usertours\manager::class);
        $method = $class->getMethod('_move_tour');
        $method->setAccessible(true);
        $method->invokeArgs(null, [$tour, $direction]);

        // Assert expected sortorder.
        $this->assertEquals($expectedsortorder, $tour->get_sortorder());
    }

    /**
     * Data Provider for get_matching_tours tests.
     *
     * @return array
     */
    public function get_matching_tours_provider() {
        global $CFG;

        $alltours = [
            [
                    'pathmatch'     => '/my/%',
                    'enabled'       => false,
                    'name'          => 'Failure',
                    'description'   => '',
                    'configdata'    => '',
                ],
            [
                    'pathmatch'     => '/my/%',
                    'enabled'       => true,
                    'name'          => 'My tour enabled',
                    'description'   => '',
                    'configdata'    => '',
                ],
            [
                    'pathmatch'     => '/my/%',
                    'enabled'       => false,
                    'name'          => 'Failure',
                    'description'   => '',
                    'configdata'    => '',
                ],
            [
                    'pathmatch'     => '/course/?id=%foo=bar',
                    'enabled'       => false,
                    'name'          => 'Failure',
                    'description'   => '',
                    'configdata'    => '',
                ],
            [
                    'pathmatch'     => '/course/?id=%foo=bar',
                    'enabled'       => true,
                    'name'          => 'course tour with additional params enabled',
                    'description'   => '',
                    'configdata'    => '',
                ],
            [
                    'pathmatch'     => '/course/?id=%foo=bar',
                    'enabled'       => false,
                    'name'          => 'Failure',
                    'description'   => '',
                    'configdata'    => '',
                ],
            [
                    'pathmatch'     => '/course/?id=%',
                    'enabled'       => false,
                    'name'          => 'Failure',
                    'description'   => '',
                    'configdata'    => '',
                ],
            [
                    'pathmatch'     => '/course/?id=%',
                    'enabled'       => true,
                    'name'          => 'course tour enabled',
                    'description'   => '',
                    'configdata'    => '',
                ],
            [
                    'pathmatch'     => '/course/?id=%',
                    'enabled'       => false,
                    'name'          => 'Failure',
                    'description'   => '',
                    'configdata'    => '',
                ],
        ];

        return [
                'No matches found' => [
                        $alltours,
                        $CFG->wwwroot . '/some/invalid/value',
                        null,
                    ],
                'Never return a disabled tour' => [
                        $alltours,
                        $CFG->wwwroot . '/my/index.php',
                        'My tour enabled',
                    ],
                'My not course' => [
                        $alltours,
                        $CFG->wwwroot . '/my/index.php',
                        'My tour enabled',
                    ],
                'My with params' => [
                        $alltours,
                        $CFG->wwwroot . '/my/index.php?id=42',
                        'My tour enabled',
                    ],
                'Course with params' => [
                        $alltours,
                        $CFG->wwwroot . '/course/?id=42',
                        'course tour enabled',
                    ],
                'Course with params and trailing content' => [
                        $alltours,
                        $CFG->wwwroot . '/course/?id=42&foo=bar',
                        'course tour with additional params enabled',
                    ],
            ];
    }

    /**
     * Tests for the get_matching_tours function.
     *
     * @dataProvider get_matching_tours_provider
     * @param   array   $alltours   The list of tours to insert
     * @param   string  $url        The URL to test
     * @param   string  $expected   The name of the expected matching tour
     */
    public function test_get_matching_tours($alltours, $url, $expected) {
        $this->resetAfterTest();

        foreach ($alltours as $tourconfig) {
            $tour = $this->helper_create_tour((object) $tourconfig);
            $this->helper_create_step((object) ['tourid' => $tour->get_id()]);
        }

        $match = \tool_usertours\manager::get_matching_tours(new moodle_url($url));
        if ($expected === null) {
            $this->assertNull($match);
        } else {
            $this->assertNotNull($match);
            $this->assertEquals($expected, $match->get_name());
        }
    }
}
