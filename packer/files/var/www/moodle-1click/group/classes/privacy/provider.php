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
 * Privacy Subsystem implementation for core_group.
 *
 * @package    core_group
 * @category   privacy
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_group\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;

/**
 * Privacy Subsystem implementation for core_group.
 *
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        // Groups store user data.
        \core_privacy\local\metadata\provider,

        // The group subsystem contains user's group memberships.
        \core_privacy\local\request\subsystem\provider,

        // The group subsystem can provide information to other plugins.
        \core_privacy\local\request\subsystem\plugin_provider,

        // This plugin is capable of determining which users have data within it.
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\shared_userlist_provider
    {

    /**
     * Returns meta data about this system.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table('groups_members', [
            'groupid' => 'privacy:metadata:groups:groupid',
            'userid' => 'privacy:metadata:groups:userid',
            'timeadded' => 'privacy:metadata:groups:timeadded',
        ], 'privacy:metadata:groups');

        $collection->link_subsystem('core_message', 'privacy:metadata:core_message');

        return $collection;
    }

    /**
     * Writes user data to the writer for the user to download.
     *
     * @param \context  $context    The context to export data for.
     * @param string    $component  The component that is calling this function. Empty string means no component.
     * @param array     $subcontext The sub-context in which to export this data.
     * @param int       $itemid     Optional itemid associated with component.
     */
    public static function export_groups(\context $context, string $component, array $subcontext = [], int $itemid = 0) {
        global $DB, $USER;

        if (!$context instanceof \context_course) {
            return;
        }

        $subcontext[] = get_string('groups', 'core_group');

        $sql = "SELECT gm.id, gm.timeadded, gm.userid, g.name, gm.groupid
                  FROM {groups_members} gm
                  JOIN {groups} g ON gm.groupid = g.id
                 WHERE g.courseid = :courseid
                       AND gm.component = :component
                       AND gm.userid = :userid";
        $params = [
            'courseid'  => $context->instanceid,
            'component' => $component,
            'userid'    => $USER->id
        ];

        if ($itemid) {
            $sql .= ' AND gm.itemid = :itemid';
            $params['itemid'] = $itemid;
        }

        $groups = $DB->get_records_sql($sql, $params);

        $groupstoexport = array_map(function($group) {
            return (object) [
                'name' => format_string($group->name),
                'timeadded' => transform::datetime($group->timeadded),
            ];
        }, $groups);

        if (!empty($groups)) {
            \core_privacy\local\request\writer::with_context($context)
                    ->export_data($subcontext, (object) [
                        'groups' => $groupstoexport,
                    ]);

            foreach ($groups as $group) {
                // Export associated conversations to this group.
                \core_message\privacy\provider::export_conversations($USER->id, 'core_group', 'groups',
                    $context, [], $group->groupid);
            }
        }
    }

    /**
     * Deletes all group memberships for a specified context and component.
     *
     * @param \context  $context    Details about which context to delete group memberships for.
     * @param string    $component  Component to delete. Empty string means no component (manual group memberships).
     * @param int       $itemid     Optional itemid associated with component.
     */
    public static function delete_groups_for_all_users(\context $context, string $component, int $itemid = 0) {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }

        if (!$DB->record_exists('groups', ['courseid' => $context->instanceid])) {
            return;
        }

        $select = "component = :component AND groupid IN (SELECT g.id FROM {groups} g WHERE courseid = :courseid)";
        $params = ['component' => $component, 'courseid' => $context->instanceid];

        if ($itemid) {
            $select .= ' AND itemid = :itemid';
            $params['itemid'] = $itemid;
        }

        // Delete the group conversations.
        $groups = $DB->get_records_select('groups_members', $select, $params);
        foreach ($groups as $group) {
            \core_message\privacy\provider::delete_conversations_for_all_users($context, 'core_group', 'groups', $group->groupid);
        }

        // Remove members from the group.
        $DB->delete_records_select('groups_members', $select, $params);

        // Purge the group and grouping cache for users.
        \cache_helper::purge_by_definition('core', 'user_group_groupings');
    }

    /**
     * Deletes all records for a user from a list of approved contexts.
     *
     * @param approved_contextlist  $contextlist    Contains the user ID and a list of contexts to be deleted from.
     * @param string                $component      Component to delete from. Empty string means no component (manual memberships).
     * @param int                   $itemid         Optional itemid associated with component.
     */
    public static function delete_groups_for_user(approved_contextlist $contextlist, string $component, int $itemid = 0) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $contextids = $contextlist->get_contextids();

        if (!$contextids) {
            return;
        }

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
        $contextparams += ['contextcourse' => CONTEXT_COURSE];
        $groupselect = "SELECT g.id
                          FROM {groups} g
                          JOIN {context} ctx ON g.courseid = ctx.instanceid AND ctx.contextlevel = :contextcourse
                         WHERE ctx.id $contextsql";

        if (!$DB->record_exists_sql($groupselect, $contextparams)) {
            return;
        }

        $select = "userid = :userid AND component = :component AND groupid IN ({$groupselect})";
        $params = ['userid' => $userid, 'component' => $component] + $contextparams;

        if ($itemid) {
            $select .= ' AND itemid = :itemid';
            $params['itemid'] = $itemid;
        }

        // Delete the group conversations.
        $groups = $DB->get_records_select('groups_members', $select, $params);
        foreach ($groups as $group) {
            \core_message\privacy\provider::delete_conversations_for_user($contextlist, 'core_group', 'groups', $group->groupid);
        }

        // Remove members from the group.
        $DB->delete_records_select('groups_members', $select, $params);

        // Invalidate the group and grouping cache for the user.
        \cache_helper::invalidate_by_definition('core', 'user_group_groupings', array(), array($userid));
    }

    /**
     * Add the list of users who are members of some groups in the specified constraints.
     *
     * @param   userlist    $userlist   The userlist to add the users to.
     * @param   string      $component  The component to check.
     * @param   int         $itemid     Optional itemid associated with component.
     */
    public static function get_group_members_in_context(userlist $userlist, string $component, int $itemid = 0) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        // Group members in the given context.
        $sql = "SELECT gm.userid
                  FROM {groups_members} gm
                  JOIN {groups} g ON gm.groupid = g.id
                 WHERE g.courseid = :courseid AND gm.component = :component";
        $params = [
            'courseid'      => $context->instanceid,
            'component'     => $component
        ];

        if ($itemid) {
            $sql .= ' AND gm.itemid = :itemid';
            $params['itemid'] = $itemid;
        }

        $userlist->add_from_sql('userid', $sql, $params);

        // Get the users with some group conversation in this context.
        \core_message\privacy\provider::add_conversations_in_context($userlist, 'core_group', 'groups', $itemid);
    }

    /**
     * Deletes all records for multiple users within a single context.
     *
     * @param approved_userlist $userlist   The approved context and user information to delete information for.
     * @param string            $component  Component to delete from. Empty string means no component (manual memberships).
     * @param int               $itemid     Optional itemid associated with component.
     */
    public static function delete_groups_for_users(approved_userlist $userlist, string $component, int $itemid = 0) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();

        if (!$context instanceof \context_course) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $groupselect = "SELECT id FROM {groups} WHERE courseid = :courseid";
        $groupparams = ['courseid' => $context->instanceid];

        $select = "component = :component AND userid {$usersql} AND groupid IN ({$groupselect})";
        $params = ['component' => $component] + $groupparams + $userparams;

        if ($itemid) {
            $select .= ' AND itemid = :itemid';
            $params['itemid'] = $itemid;
        }

        // Delete the group conversations for these users.
        $groups = $DB->get_records_select('groups_members', $select, $params);
        foreach ($groups as $group) {
            \core_message\privacy\provider::delete_conversations_for_users($userlist, 'core_group', 'groups', $group->groupid);
        }

        $DB->delete_records_select('groups_members', $select, $params);

        // Invalidate the group and grouping cache for the user.
        \cache_helper::invalidate_by_definition('core', 'user_group_groupings', array(), $userids);
    }

    /**
     * Get the list of contexts that contain group membership for the specified user.
     *
     * @param   int     $userid     The user to search.
     * @param   string  $component  The component to check.
     * @param   int     $itemid     Optional itemid associated with component.
     * @return  contextlist         The contextlist containing the list of contexts.
     */
    public static function get_contexts_for_group_member(int $userid, string $component, int $itemid = 0) {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {groups_members} gm
                  JOIN {groups} g ON gm.groupid = g.id
                  JOIN {context} ctx ON g.courseid = ctx.instanceid AND ctx.contextlevel = :contextcourse
                 WHERE gm.userid = :userid AND gm.component = :component";

        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'userid'        => $userid,
            'component'     => $component
        ];

        if ($itemid) {
            $sql .= ' AND gm.itemid = :itemid';
            $params['itemid'] = $itemid;
        }

        $contextlist->add_from_sql($sql, $params);

        // Get the contexts where the userid has group conversations.
        \core_message\privacy\provider::add_contexts_for_conversations($contextlist, $userid, 'core_group', 'groups', $itemid);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   int $userid The user to search.
     * @return  contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        return static::get_contexts_for_group_member($userid, '');
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        static::get_group_members_in_context($userlist, '');
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $contexts = $contextlist->get_contexts();

        foreach ($contexts as $context) {
            static::export_groups($context, '');
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        static::delete_groups_for_all_users($context, '');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        static::delete_groups_for_user($contextlist, '');
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist   $userlist   The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        static::delete_groups_for_users($userlist, '');
    }

}
