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
 * lock unit tests
 *
 * @package    core
 * @category   test
 * @copyright  2013 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Unit tests for our locking implementations.
 *
 * @package    core
 * @category   test
 * @copyright  2013 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lock_testcase extends advanced_testcase {

    /**
     * Some lock types will store data in the database.
     */
    protected function setUp() {
        $this->resetAfterTest(true);
    }

    /**
     * Run a suite of tests on a lock factory class.
     *
     * @param class $lockfactoryclass - A lock factory class to test
     */
    protected function run_on_lock_factory($lockfactoryclass) {

        $modassignfactory = new $lockfactoryclass('mod_assign');
        $tooltaskfactory = new $lockfactoryclass('tool_task');

        // Test for lock clashes between lock stores.
        $assignlock = $modassignfactory->get_lock('abc', 0);
        $this->assertNotEmpty($assignlock, 'Get a lock "abc" from store "mod_assign"');

        $tasklock = $tooltaskfactory->get_lock('abc', 0);
        $this->assertNotEmpty($tasklock, 'Get a lock "abc" from store "tool_task"');

        $assignlock->release();
        $tasklock->release();

        $lockfactory = new $lockfactoryclass('default');
        if ($lockfactory->is_available()) {
            // This should work.
            $lock1 = $lockfactory->get_lock('abc', 2);
            $this->assertNotEmpty($lock1, 'Get a lock');

            if ($lockfactory->supports_timeout()) {
                if ($lockfactory->supports_recursion()) {
                    $lock2 = $lockfactory->get_lock('abc', 2);
                    $this->assertNotEmpty($lock2, 'Get a stacked lock');
                    $this->assertTrue($lock2->release(), 'Release a stacked lock');

                    // This stacked lock should be gained almost instantly.
                    $duration = -microtime(true);
                    $lock3 = $lockfactory->get_lock('abc', 0);
                    $duration += microtime(true);
                    $lock3->release();
                    $this->assertTrue($duration < 0.100, 'Lock should be gained almost instantly');

                    // We should also assert that locks fail instantly if locked
                    // from another process but this is hard to unit test.

                } else {
                    // This should timeout after 2 seconds.
                    $duration = -microtime(true);
                    $lock2 = $lockfactory->get_lock('abc', 2);
                    $duration += microtime(true);
                    $this->assertFalse($lock2, 'Cannot get a stacked lock');
                    $this->assertTrue($duration < 2.5, 'Lock should timeout after no more than 2 seconds');

                    // This should timeout almost instantly.
                    $duration = -microtime(true);
                    $lock2 = $lockfactory->get_lock('abc', 0);
                    $duration += microtime(true);
                    $this->assertFalse($lock2, 'Cannot get a stacked lock');
                    $this->assertTrue($duration < 0.100, 'Lock should timeout almost instantly < 100ms');
                }
            }
            // Release the lock.
            $this->assertTrue($lock1->release(), 'Release a lock');
            // Get it again.
            $lock3 = $lockfactory->get_lock('abc', 2);

            $this->assertNotEmpty($lock3, 'Get a lock again');
            // Release the lock again.
            $this->assertTrue($lock3->release(), 'Release a lock again');
            // Release the lock again (shouldn't hurt).
            $this->assertFalse($lock3->release(), 'Release a lock that is not held');
            if (!$lockfactory->supports_auto_release()) {
                // Test that a lock can be claimed after the timeout period.
                $lock4 = $lockfactory->get_lock('abc', 2, 2);
                $this->assertNotEmpty($lock4, 'Get a lock');
                sleep(3);

                $lock5 = $lockfactory->get_lock('abc', 2, 2);
                $this->assertNotEmpty($lock5, 'Get another lock after a timeout');
                $this->assertTrue($lock5->release(), 'Release the lock');
                $this->assertTrue($lock4->release(), 'Release the lock');
            }
        }
    }

    /**
     * Tests the testable lock factories classes.
     * @return void
     */
    public function test_locks() {
        // Run the suite on the current configured default (may be non-core).
        $this->run_on_lock_factory(\core\lock\lock_config::get_lock_factory_class());

        // Manually create the core no-configuration factories.
        $this->run_on_lock_factory(\core\lock\db_record_lock_factory::class);
        $this->run_on_lock_factory(\core\lock\file_lock_factory::class);

    }

}

