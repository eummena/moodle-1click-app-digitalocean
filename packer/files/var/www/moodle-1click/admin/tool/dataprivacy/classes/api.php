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
 * Class containing helper methods for processing data requests.
 *
 * @package    tool_dataprivacy
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_dataprivacy;

use coding_exception;
use context_helper;
use context_system;
use core\invalid_persistent_exception;
use core\message\message;
use core\task\manager;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist_collection;
use core_user;
use dml_exception;
use moodle_exception;
use moodle_url;
use required_capability_exception;
use stdClass;
use tool_dataprivacy\external\data_request_exporter;
use tool_dataprivacy\local\helper;
use tool_dataprivacy\task\process_data_request_task;
use tool_dataprivacy\data_request;

defined('MOODLE_INTERNAL') || die();

/**
 * Class containing helper methods for processing data requests.
 *
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /** Data export request type. */
    const DATAREQUEST_TYPE_EXPORT = 1;

    /** Data deletion request type. */
    const DATAREQUEST_TYPE_DELETE = 2;

    /** Other request type. Usually of enquiries to the DPO. */
    const DATAREQUEST_TYPE_OTHERS = 3;

    /** Newly submitted and we haven't yet started finding out where they have data. */
    const DATAREQUEST_STATUS_PENDING = 0;

    /** Metadata ready and awaiting review and approval by the Data Protection officer. */
    const DATAREQUEST_STATUS_AWAITING_APPROVAL = 2;

    /** Request approved and will be processed soon. */
    const DATAREQUEST_STATUS_APPROVED = 3;

    /** The request is now being processed. */
    const DATAREQUEST_STATUS_PROCESSING = 4;

    /** Information/other request completed. */
    const DATAREQUEST_STATUS_COMPLETE = 5;

    /** Data request cancelled by the user. */
    const DATAREQUEST_STATUS_CANCELLED = 6;

    /** Data request rejected by the DPO. */
    const DATAREQUEST_STATUS_REJECTED = 7;

    /** Data request download ready. */
    const DATAREQUEST_STATUS_DOWNLOAD_READY = 8;

    /** Data request expired. */
    const DATAREQUEST_STATUS_EXPIRED = 9;

    /** Data delete request completed, account is removed. */
    const DATAREQUEST_STATUS_DELETED = 10;

    /** Approve data request. */
    const DATAREQUEST_ACTION_APPROVE = 1;

    /** Reject data request. */
    const DATAREQUEST_ACTION_REJECT = 2;

    /**
     * Determines whether the user can contact the site's Data Protection Officer via Moodle.
     *
     * @return boolean True when tool_dataprivacy|contactdataprotectionofficer is enabled.
     * @throws dml_exception
     */
    public static function can_contact_dpo() {
        return get_config('tool_dataprivacy', 'contactdataprotectionofficer') == 1;
    }

    /**
     * Checks whether the current user has the capability to manage data requests.
     *
     * @param int $userid The user ID.
     * @return bool
     */
    public static function can_manage_data_requests($userid) {
        // Privacy officers can manage data requests.
        return self::is_site_dpo($userid);
    }

    /**
     * Checks if the current user can manage the data registry at the provided id.
     *
     * @param int $contextid Fallback to system context id.
     * @throws \required_capability_exception
     * @return null
     */
    public static function check_can_manage_data_registry($contextid = false) {
        if ($contextid) {
            $context = \context_helper::instance_by_id($contextid);
        } else {
            $context = \context_system::instance();
        }

        require_capability('tool/dataprivacy:managedataregistry', $context);
    }

    /**
     * Fetches the list of configured privacy officer roles.
     *
     * Every time this function is called, it checks each role if they have the 'managedatarequests' capability and removes
     * any role that doesn't have the required capability anymore.
     *
     * @return int[]
     * @throws dml_exception
     */
    public static function get_assigned_privacy_officer_roles() {
        $roleids = [];

        // Get roles from config.
        $configroleids = explode(',', str_replace(' ', '', get_config('tool_dataprivacy', 'dporoles')));
        if (!empty($configroleids)) {
            // Fetch roles that have the capability to manage data requests.
            $capableroles = array_keys(get_roles_with_capability('tool/dataprivacy:managedatarequests'));

            // Extract the configured roles that have the capability from the list of capable roles.
            $roleids = array_intersect($capableroles, $configroleids);
        }

        return $roleids;
    }

    /**
     * Fetches the role shortnames of Data Protection Officer roles.
     *
     * @return array An array of the DPO role shortnames
     */
    public static function get_dpo_role_names() : array {
        global $DB;

        $dporoleids = self::get_assigned_privacy_officer_roles();
        $dponames = array();

        if (!empty($dporoleids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($dporoleids);
            $dponames = $DB->get_fieldset_select('role', 'shortname', "id {$insql}", $inparams);
        }

        return $dponames;
    }

    /**
     * Fetches the list of users with the Privacy Officer role.
     */
    public static function get_site_dpos() {
        // Get role(s) that can manage data requests.
        $dporoles = self::get_assigned_privacy_officer_roles();

        $dpos = [];
        $context = context_system::instance();
        foreach ($dporoles as $roleid) {
            $allnames = get_all_user_name_fields(true, 'u');
            $fields = 'u.id, u.confirmed, u.username, '. $allnames . ', ' .
                      'u.maildisplay, u.mailformat, u.maildigest, u.email, u.emailstop, u.city, '.
                      'u.country, u.picture, u.idnumber, u.department, u.institution, '.
                      'u.lang, u.timezone, u.lastaccess, u.mnethostid, u.auth, u.suspended, u.deleted, ' .
                      'r.name AS rolename, r.sortorder, '.
                      'r.shortname AS roleshortname, rn.name AS rolecoursealias';
            // Fetch users that can manage data requests.
            $dpos += get_role_users($roleid, $context, false, $fields);
        }

        // If the site has no data protection officer, defer to site admin(s).
        if (empty($dpos)) {
            $dpos = get_admins();
        }
        return $dpos;
    }

    /**
     * Checks whether a given user is a site Privacy Officer.
     *
     * @param int $userid The user ID.
     * @return bool
     */
    public static function is_site_dpo($userid) {
        $dpos = self::get_site_dpos();
        return array_key_exists($userid, $dpos) || is_siteadmin();
    }

    /**
     * Lodges a data request and sends the request details to the site Data Protection Officer(s).
     *
     * @param int $foruser The user whom the request is being made for.
     * @param int $type The request type.
     * @param string $comments Request comments.
     * @param int $creationmethod The creation method of the data request.
     * @param bool $notify Notify DPOs of this pending request.
     * @return data_request
     * @throws invalid_persistent_exception
     * @throws coding_exception
     */
    public static function create_data_request($foruser, $type, $comments = '',
            $creationmethod = data_request::DATAREQUEST_CREATION_MANUAL,
            $notify = null
        ) {
        global $USER;

        if (null === $notify) {
            // Only if notifications have not been decided by caller.
            if ( data_request::DATAREQUEST_CREATION_AUTO == $creationmethod) {
                // If the request was automatically created, then do not notify unless explicitly set.
                $notify = false;
            } else {
                $notify = true;
            }
        }

        $datarequest = new data_request();
        // The user the request is being made for.
        $datarequest->set('userid', $foruser);

        // The cron is considered to be a guest user when it creates a data request.
        // NOTE: This should probably be changed. We should leave the default value for $requestinguser if
        // the request is not explicitly created by a specific user.
        $requestinguser = (isguestuser() && $creationmethod == data_request::DATAREQUEST_CREATION_AUTO) ?
                get_admin()->id : $USER->id;
        // The user making the request.
        $datarequest->set('requestedby', $requestinguser);
        // Set status.
        $status = self::DATAREQUEST_STATUS_AWAITING_APPROVAL;
        if (self::is_automatic_request_approval_on($type)) {
            // Set status to approved if automatic data request approval is enabled.
            $status = self::DATAREQUEST_STATUS_APPROVED;
            // Set the privacy officer field if the one making the data request is a privacy officer.
            if (self::is_site_dpo($requestinguser)) {
                $datarequest->set('dpo', $requestinguser);
            }
            // Mark this request as system approved.
            $datarequest->set('systemapproved', true);
            // No need to notify privacy officer(s) about automatically approved data requests.
            $notify = false;
        }
        $datarequest->set('status', $status);
        // Set request type.
        $datarequest->set('type', $type);
        // Set request comments.
        $datarequest->set('comments', $comments);
        // Set the creation method.
        $datarequest->set('creationmethod', $creationmethod);

        // Store subject access request.
        $datarequest->create();

        // Queue the ad-hoc task for automatically approved data requests.
        if ($status == self::DATAREQUEST_STATUS_APPROVED) {
            $userid = null;
            if ($type == self::DATAREQUEST_TYPE_EXPORT) {
                $userid = $foruser;
            }
            self::queue_data_request_task($datarequest->get('id'), $userid);
        }

        if ($notify) {
            // Get the list of the site Data Protection Officers.
            $dpos = self::get_site_dpos();

            // Email the data request to the Data Protection Officer(s)/Admin(s).
            foreach ($dpos as $dpo) {
                self::notify_dpo($dpo, $datarequest);
            }
        }

        return $datarequest;
    }

    /**
     * Fetches the list of the data requests.
     *
     * If user ID is provided, it fetches the data requests for the user.
     * Otherwise, it fetches all of the data requests, provided that the user has the capability to manage data requests.
     * (e.g. Users with the Data Protection Officer roles)
     *
     * @param int $userid The User ID.
     * @param int[] $statuses The status filters.
     * @param int[] $types The request type filters.
     * @param int[] $creationmethods The request creation method filters.
     * @param string $sort The order by clause.
     * @param int $offset Amount of records to skip.
     * @param int $limit Amount of records to fetch.
     * @return data_request[]
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_data_requests($userid = 0, $statuses = [], $types = [], $creationmethods = [],
                                             $sort = '', $offset = 0, $limit = 0) {
        global $DB, $USER;
        $results = [];
        $sqlparams = [];
        $sqlconditions = [];

        // Set default sort.
        if (empty($sort)) {
            $sort = 'status ASC, timemodified ASC';
        }

        // Set status filters.
        if (!empty($statuses)) {
            list($statusinsql, $sqlparams) = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED);
            $sqlconditions[] = "status $statusinsql";
        }

        // Set request type filter.
        if (!empty($types)) {
            list($typeinsql, $typeparams) = $DB->get_in_or_equal($types, SQL_PARAMS_NAMED);
            $sqlconditions[] = "type $typeinsql";
            $sqlparams = array_merge($sqlparams, $typeparams);
        }

        // Set request creation method filter.
        if (!empty($creationmethods)) {
            list($typeinsql, $typeparams) = $DB->get_in_or_equal($creationmethods, SQL_PARAMS_NAMED);
            $sqlconditions[] = "creationmethod $typeinsql";
            $sqlparams = array_merge($sqlparams, $typeparams);
        }

        if ($userid) {
            // Get the data requests for the user or data requests made by the user.
            $sqlconditions[] = "(userid = :userid OR requestedby = :requestedby)";
            $params = [
                'userid' => $userid,
                'requestedby' => $userid
            ];

            // Build a list of user IDs that the user is allowed to make data requests for.
            // Of course, the user should be included in this list.
            $alloweduserids = [$userid];
            // Get any users that the user can make data requests for.
            if ($children = helper::get_children_of_user($userid)) {
                // Get the list of user IDs of the children and merge to the allowed user IDs.
                $alloweduserids = array_merge($alloweduserids, array_keys($children));
            }
            list($insql, $inparams) = $DB->get_in_or_equal($alloweduserids, SQL_PARAMS_NAMED);
            $sqlconditions[] .= "userid $insql";
            $select = implode(' AND ', $sqlconditions);
            $params = array_merge($params, $inparams, $sqlparams);

            $results = data_request::get_records_select($select, $params, $sort, '*', $offset, $limit);
        } else {
            // If the current user is one of the site's Data Protection Officers, then fetch all data requests.
            if (self::is_site_dpo($USER->id)) {
                if (!empty($sqlconditions)) {
                    $select = implode(' AND ', $sqlconditions);
                    $results = data_request::get_records_select($select, $sqlparams, $sort, '*', $offset, $limit);
                } else {
                    $results = data_request::get_records(null, $sort, '', $offset, $limit);
                }
            }
        }

        // If any are due to expire, expire them and re-fetch updated data.
        if (empty($statuses)
                || in_array(self::DATAREQUEST_STATUS_DOWNLOAD_READY, $statuses)
                || in_array(self::DATAREQUEST_STATUS_EXPIRED, $statuses)) {
            $expiredrequests = data_request::get_expired_requests($userid);

            if (!empty($expiredrequests)) {
                data_request::expire($expiredrequests);
                $results = self::get_data_requests($userid, $statuses, $types, $creationmethods, $sort, $offset, $limit);
            }
        }

        return $results;
    }

    /**
     * Fetches the count of data request records based on the given parameters.
     *
     * @param int $userid The User ID.
     * @param int[] $statuses The status filters.
     * @param int[] $types The request type filters.
     * @param int[] $creationmethods The request creation method filters.
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_data_requests_count($userid = 0, $statuses = [], $types = [], $creationmethods = []) {
        global $DB, $USER;
        $count = 0;
        $sqlparams = [];
        $sqlconditions = [];
        if (!empty($statuses)) {
            list($statusinsql, $sqlparams) = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED);
            $sqlconditions[] = "status $statusinsql";
        }
        if (!empty($types)) {
            list($typeinsql, $typeparams) = $DB->get_in_or_equal($types, SQL_PARAMS_NAMED);
            $sqlconditions[] = "type $typeinsql";
            $sqlparams = array_merge($sqlparams, $typeparams);
        }
        if (!empty($creationmethods)) {
            list($typeinsql, $typeparams) = $DB->get_in_or_equal($creationmethods, SQL_PARAMS_NAMED);
            $sqlconditions[] = "creationmethod $typeinsql";
            $sqlparams = array_merge($sqlparams, $typeparams);
        }
        if ($userid) {
            // Get the data requests for the user or data requests made by the user.
            $sqlconditions[] = "(userid = :userid OR requestedby = :requestedby)";
            $params = [
                'userid' => $userid,
                'requestedby' => $userid
            ];

            // Build a list of user IDs that the user is allowed to make data requests for.
            // Of course, the user should be included in this list.
            $alloweduserids = [$userid];
            // Get any users that the user can make data requests for.
            if ($children = helper::get_children_of_user($userid)) {
                // Get the list of user IDs of the children and merge to the allowed user IDs.
                $alloweduserids = array_merge($alloweduserids, array_keys($children));
            }
            list($insql, $inparams) = $DB->get_in_or_equal($alloweduserids, SQL_PARAMS_NAMED);
            $sqlconditions[] .= "userid $insql";
            $select = implode(' AND ', $sqlconditions);
            $params = array_merge($params, $inparams, $sqlparams);

            $count = data_request::count_records_select($select, $params);
        } else {
            // If the current user is one of the site's Data Protection Officers, then fetch all data requests.
            if (self::is_site_dpo($USER->id)) {
                if (!empty($sqlconditions)) {
                    $select = implode(' AND ', $sqlconditions);
                    $count = data_request::count_records_select($select, $sqlparams);
                } else {
                    $count = data_request::count_records();
                }
            }
        }

        return $count;
    }

    /**
     * Checks whether there is already an existing pending/in-progress data request for a user for a given request type.
     *
     * @param int $userid The user ID.
     * @param int $type The request type.
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function has_ongoing_request($userid, $type) {
        global $DB;

        // Check if the user already has an incomplete data request of the same type.
        $nonpendingstatuses = [
            self::DATAREQUEST_STATUS_COMPLETE,
            self::DATAREQUEST_STATUS_CANCELLED,
            self::DATAREQUEST_STATUS_REJECTED,
            self::DATAREQUEST_STATUS_DOWNLOAD_READY,
            self::DATAREQUEST_STATUS_EXPIRED,
            self::DATAREQUEST_STATUS_DELETED,
        ];
        list($insql, $inparams) = $DB->get_in_or_equal($nonpendingstatuses, SQL_PARAMS_NAMED, 'st', false);
        $select = "type = :type AND userid = :userid AND status {$insql}";
        $params = array_merge([
            'type' => $type,
            'userid' => $userid
        ], $inparams);

        return data_request::record_exists_select($select, $params);
    }

    /**
     * Find whether any ongoing requests exist for a set of users.
     *
     * @param   array   $userids
     * @return  array
     */
    public static function find_ongoing_request_types_for_users(array $userids) : array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        // Check if the user already has an incomplete data request of the same type.
        $nonpendingstatuses = [
            self::DATAREQUEST_STATUS_COMPLETE,
            self::DATAREQUEST_STATUS_CANCELLED,
            self::DATAREQUEST_STATUS_REJECTED,
            self::DATAREQUEST_STATUS_DOWNLOAD_READY,
            self::DATAREQUEST_STATUS_EXPIRED,
            self::DATAREQUEST_STATUS_DELETED,
        ];
        list($statusinsql, $statusparams) = $DB->get_in_or_equal($nonpendingstatuses, SQL_PARAMS_NAMED, 'st', false);
        list($userinsql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'us');

        $select = "userid {$userinsql} AND status {$statusinsql}";
        $params = array_merge($statusparams, $userparams);

        $requests = $DB->get_records_select(data_request::TABLE, $select, $params, 'userid', 'id, userid, type');

        $returnval = [];
        foreach ($userids as $userid) {
            $returnval[$userid] = (object) [];
        }

        foreach ($requests as $request) {
            $returnval[$request->userid]->{$request->type} = true;
        }

        return $returnval;
    }

    /**
     * Determines whether a request is active or not based on its status.
     *
     * @param int $status The request status.
     * @return bool
     */
    public static function is_active($status) {
        // List of statuses which doesn't require any further processing.
        $finalstatuses = [
            self::DATAREQUEST_STATUS_COMPLETE,
            self::DATAREQUEST_STATUS_CANCELLED,
            self::DATAREQUEST_STATUS_REJECTED,
            self::DATAREQUEST_STATUS_DOWNLOAD_READY,
            self::DATAREQUEST_STATUS_EXPIRED,
            self::DATAREQUEST_STATUS_DELETED,
        ];

        return !in_array($status, $finalstatuses);
    }

    /**
     * Cancels the data request for a given request ID.
     *
     * @param int $requestid The request identifier.
     * @param int $status The request status.
     * @param int $dpoid The user ID of the Data Protection Officer
     * @param string $comment The comment about the status update.
     * @return bool
     * @throws invalid_persistent_exception
     * @throws coding_exception
     */
    public static function update_request_status($requestid, $status, $dpoid = 0, $comment = '') {
        // Update the request.
        $datarequest = new data_request($requestid);
        $datarequest->set('status', $status);
        if ($dpoid) {
            $datarequest->set('dpo', $dpoid);
        }
        // Update the comment if necessary.
        if (!empty(trim($comment))) {
            $params = [
                'date' => userdate(time()),
                'comment' => $comment
            ];
            $commenttosave = get_string('datecomment', 'tool_dataprivacy', $params);
            // Check if there's an existing DPO comment.
            $currentcomment = trim($datarequest->get('dpocomment'));
            if ($currentcomment) {
                // Append the new comment to the current comment and give them 1 line space in between.
                $commenttosave = $currentcomment . PHP_EOL . PHP_EOL . $commenttosave;
            }
            $datarequest->set('dpocomment', $commenttosave);
        }

        return $datarequest->update();
    }

    /**
     * Fetches a request based on the request ID.
     *
     * @param int $requestid The request identifier
     * @return data_request
     */
    public static function get_request($requestid) {
        return new data_request($requestid);
    }

    /**
     * Approves a data request based on the request ID.
     *
     * @param int $requestid The request identifier
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     * @throws required_capability_exception
     * @throws moodle_exception
     */
    public static function approve_data_request($requestid) {
        global $USER;

        // Check first whether the user can manage data requests.
        if (!self::can_manage_data_requests($USER->id)) {
            $context = context_system::instance();
            throw new required_capability_exception($context, 'tool/dataprivacy:managedatarequests', 'nopermissions', '');
        }

        // Check if request is already awaiting for approval.
        $request = new data_request($requestid);
        if ($request->get('status') != self::DATAREQUEST_STATUS_AWAITING_APPROVAL) {
            throw new moodle_exception('errorrequestnotwaitingforapproval', 'tool_dataprivacy');
        }

        // Check if current user has permission to approve delete data request.
        if ($request->get('type') == self::DATAREQUEST_TYPE_DELETE && !self::can_create_data_deletion_request_for_other()) {
            throw new required_capability_exception(context_system::instance(),
                'tool/dataprivacy:requestdeleteforotheruser', 'nopermissions', '');
        }

        // Update the status and the DPO.
        $result = self::update_request_status($requestid, self::DATAREQUEST_STATUS_APPROVED, $USER->id);

        // Fire an ad hoc task to initiate the data request process.
        $userid = null;
        if ($request->get('type') == self::DATAREQUEST_TYPE_EXPORT) {
            $userid = $request->get('userid');
        }
        self::queue_data_request_task($requestid, $userid);

        return $result;
    }

    /**
     * Rejects a data request based on the request ID.
     *
     * @param int $requestid The request identifier
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_persistent_exception
     * @throws required_capability_exception
     * @throws moodle_exception
     */
    public static function deny_data_request($requestid) {
        global $USER;

        if (!self::can_manage_data_requests($USER->id)) {
            $context = context_system::instance();
            throw new required_capability_exception($context, 'tool/dataprivacy:managedatarequests', 'nopermissions', '');
        }

        // Check if request is already awaiting for approval.
        $request = new data_request($requestid);
        if ($request->get('status') != self::DATAREQUEST_STATUS_AWAITING_APPROVAL) {
            throw new moodle_exception('errorrequestnotwaitingforapproval', 'tool_dataprivacy');
        }

        // Check if current user has permission to reject delete data request.
        if ($request->get('type') == self::DATAREQUEST_TYPE_DELETE && !self::can_create_data_deletion_request_for_other()) {
            throw new required_capability_exception(context_system::instance(),
                'tool/dataprivacy:requestdeleteforotheruser', 'nopermissions', '');
        }

        // Update the status and the DPO.
        return self::update_request_status($requestid, self::DATAREQUEST_STATUS_REJECTED, $USER->id);
    }

    /**
     * Sends a message to the site's Data Protection Officer about a request.
     *
     * @param stdClass $dpo The DPO user record
     * @param data_request $request The data request
     * @return int|false
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function notify_dpo($dpo, data_request $request) {
        global $PAGE, $SITE;

        $output = $PAGE->get_renderer('tool_dataprivacy');

        $usercontext = \context_user::instance($request->get('requestedby'));
        $requestexporter = new data_request_exporter($request, ['context' => $usercontext]);
        $requestdata = $requestexporter->export($output);

        // Create message to send to the Data Protection Officer(s).
        $typetext = null;
        $typetext = $requestdata->typename;
        $subject = get_string('datarequestemailsubject', 'tool_dataprivacy', $typetext);

        $requestedby = $requestdata->requestedbyuser;
        $datarequestsurl = new moodle_url('/admin/tool/dataprivacy/datarequests.php');
        $message = new message();
        $message->courseid          = $SITE->id;
        $message->component         = 'tool_dataprivacy';
        $message->name              = 'contactdataprotectionofficer';
        $message->userfrom          = $requestedby->id;
        $message->replyto           = $requestedby->email;
        $message->replytoname       = $requestedby->fullname;
        $message->subject           = $subject;
        $message->fullmessageformat = FORMAT_HTML;
        $message->notification      = 1;
        $message->contexturl        = $datarequestsurl;
        $message->contexturlname    = get_string('datarequests', 'tool_dataprivacy');

        // Prepare the context data for the email message body.
        $messagetextdata = [
            'requestedby' => $requestedby->fullname,
            'requesttype' => $typetext,
            'requestdate' => userdate($requestdata->timecreated),
            'requestorigin' => format_string($SITE->fullname, true, ['context' => context_system::instance()]),
            'requestoriginurl' => new moodle_url('/'),
            'requestcomments' => $requestdata->messagehtml,
            'datarequestsurl' => $datarequestsurl
        ];
        $requestingfor = $requestdata->foruser;
        if ($requestedby->id == $requestingfor->id) {
            $messagetextdata['requestfor'] = $messagetextdata['requestedby'];
        } else {
            $messagetextdata['requestfor'] = $requestingfor->fullname;
        }

        // Email the data request to the Data Protection Officer(s)/Admin(s).
        $messagetextdata['dponame'] = fullname($dpo);
        // Render message email body.
        $messagehtml = $output->render_from_template('tool_dataprivacy/data_request_email', $messagetextdata);
        $message->userto = $dpo;
        $message->fullmessage = html_to_text($messagehtml);
        $message->fullmessagehtml = $messagehtml;

        // Send message.
        return message_send($message);
    }

    /**
     * Checks whether a non-DPO user can make a data request for another user.
     *
     * @param   int     $user The user ID of the target user.
     * @param   int     $requester The user ID of the user making the request.
     * @return  bool
     */
    public static function can_create_data_request_for_user($user, $requester = null) {
        $usercontext = \context_user::instance($user);

        return has_capability('tool/dataprivacy:makedatarequestsforchildren', $usercontext, $requester);
    }

    /**
     * Require that the current user can make a data request for the specified other user.
     *
     * @param   int     $user The user ID of the target user.
     * @param   int     $requester The user ID of the user making the request.
     * @return  bool
     */
    public static function require_can_create_data_request_for_user($user, $requester = null) {
        $usercontext = \context_user::instance($user);

        require_capability('tool/dataprivacy:makedatarequestsforchildren', $usercontext, $requester);

        return true;
    }

    /**
     * Check if user has permisson to create data deletion request for themselves.
     *
     * @param int|null $userid ID of the user.
     * @return bool
     * @throws coding_exception
     */
    public static function can_create_data_deletion_request_for_self(int $userid = null): bool {
        global $USER;
        $userid = $userid ?: $USER->id;
        return has_capability('tool/dataprivacy:requestdelete', \context_user::instance($userid), $userid)
            && !is_primary_admin($userid);
    }

    /**
     * Check if user has permission to create data deletion request for another user.
     *
     * @param int|null $userid ID of the user.
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function can_create_data_deletion_request_for_other(int $userid = null): bool {
        global $USER;
        $userid = $userid ?: $USER->id;
        return has_capability('tool/dataprivacy:requestdeleteforotheruser', context_system::instance(), $userid);
    }

    /**
     * Check if parent can create data deletion request for their children.
     *
     * @param int $userid ID of a user being requested.
     * @param int|null $requesterid ID of a user making request.
     * @return bool
     * @throws coding_exception
     */
    public static function can_create_data_deletion_request_for_children(int $userid, int $requesterid = null): bool {
        global $USER;
        $requesterid = $requesterid ?: $USER->id;
        return has_capability('tool/dataprivacy:makedatadeletionrequestsforchildren', \context_user::instance($userid),
            $requesterid) && !is_primary_admin($userid);
    }

    /**
     * Checks whether a user can download a data request.
     *
     * @param int $userid Target user id (subject of data request)
     * @param int $requesterid Requester user id (person who requsted it)
     * @param int|null $downloaderid Person who wants to download user id (default current)
     * @return bool
     * @throws coding_exception
     */
    public static function can_download_data_request_for_user($userid, $requesterid, $downloaderid = null) {
        global $USER;

        if (!$downloaderid) {
            $downloaderid = $USER->id;
        }

        $usercontext = \context_user::instance($userid);
        // If it's your own and you have the right capability, you can download it.
        if ($userid == $downloaderid && has_capability('tool/dataprivacy:downloadownrequest', $usercontext, $downloaderid)) {
            return true;
        }
        // If you can download anyone's in that context, you can download it.
        if (has_capability('tool/dataprivacy:downloadallrequests', $usercontext, $downloaderid)) {
            return true;
        }
        // If you can have the 'child access' ability to request in that context, and you are the one
        // who requested it, then you can download it.
        if ($requesterid == $downloaderid && self::can_create_data_request_for_user($userid, $requesterid)) {
            return true;
        }
        return false;
    }

    /**
     * Gets an action menu link to download a data request.
     *
     * @param \context_user $usercontext User context (of user who the data is for)
     * @param int $requestid Request id
     * @return \action_menu_link_secondary Action menu link
     * @throws coding_exception
     */
    public static function get_download_link(\context_user $usercontext, $requestid) {
        $downloadurl = moodle_url::make_pluginfile_url($usercontext->id,
                'tool_dataprivacy', 'export', $requestid, '/', 'export.zip', true);
        $downloadtext = get_string('download', 'tool_dataprivacy');
        return new \action_menu_link_secondary($downloadurl, null, $downloadtext);
    }

    /**
     * Creates a new data purpose.
     *
     * @param stdClass $record
     * @return \tool_dataprivacy\purpose.
     */
    public static function create_purpose(stdClass $record) {
        $purpose = new purpose(0, $record);
        $purpose->create();

        return $purpose;
    }

    /**
     * Updates an existing data purpose.
     *
     * @param stdClass $record
     * @return \tool_dataprivacy\purpose.
     */
    public static function update_purpose(stdClass $record) {
        if (!isset($record->sensitivedatareasons)) {
            $record->sensitivedatareasons = '';
        }

        $purpose = new purpose($record->id);
        $purpose->from_record($record);

        $result = $purpose->update();

        return $purpose;
    }

    /**
     * Deletes a data purpose.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_purpose($id) {
        $purpose = new purpose($id);
        if ($purpose->is_used()) {
            throw new \moodle_exception('Purpose with id ' . $id . ' can not be deleted because it is used.');
        }
        return $purpose->delete();
    }

    /**
     * Get all system data purposes.
     *
     * @return \tool_dataprivacy\purpose[]
     */
    public static function get_purposes() {
        return purpose::get_records([], 'name', 'ASC');
    }

    /**
     * Creates a new data category.
     *
     * @param stdClass $record
     * @return \tool_dataprivacy\category.
     */
    public static function create_category(stdClass $record) {
        $category = new category(0, $record);
        $category->create();

        return $category;
    }

    /**
     * Updates an existing data category.
     *
     * @param stdClass $record
     * @return \tool_dataprivacy\category.
     */
    public static function update_category(stdClass $record) {
        $category = new category($record->id);
        $category->from_record($record);

        $result = $category->update();

        return $category;
    }

    /**
     * Deletes a data category.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_category($id) {
        $category = new category($id);
        if ($category->is_used()) {
            throw new \moodle_exception('Category with id ' . $id . ' can not be deleted because it is used.');
        }
        return $category->delete();
    }

    /**
     * Get all system data categories.
     *
     * @return \tool_dataprivacy\category[]
     */
    public static function get_categories() {
        return category::get_records([], 'name', 'ASC');
    }

    /**
     * Sets the context instance purpose and category.
     *
     * @param \stdClass $record
     * @return \tool_dataprivacy\context_instance
     */
    public static function set_context_instance($record) {
        if ($instance = context_instance::get_record_by_contextid($record->contextid, false)) {
            // Update.
            $instance->from_record($record);

            if (empty($record->purposeid) && empty($record->categoryid)) {
                // We accept one of them to be null but we delete it if both are null.
                self::unset_context_instance($instance);
                return;
            }

        } else {
            // Add.
            $instance = new context_instance(0, $record);
        }
        $instance->save();

        return $instance;
    }

    /**
     * Unsets the context instance record.
     *
     * @param \tool_dataprivacy\context_instance $instance
     * @return null
     */
    public static function unset_context_instance(context_instance $instance) {
        $instance->delete();
    }

    /**
     * Sets the context level purpose and category.
     *
     * @throws \coding_exception
     * @param \stdClass $record
     * @return contextlevel
     */
    public static function set_contextlevel($record) {
        global $DB;

        if ($record->contextlevel != CONTEXT_SYSTEM && $record->contextlevel != CONTEXT_USER) {
            throw new \coding_exception('Only context system and context user can set a contextlevel ' .
                'purpose and retention');
        }

        if ($contextlevel = contextlevel::get_record_by_contextlevel($record->contextlevel, false)) {
            // Update.
            $contextlevel->from_record($record);
        } else {
            // Add.
            $contextlevel = new contextlevel(0, $record);
        }
        $contextlevel->save();

        // We sync with their defaults as we removed these options from the defaults page.
        $classname = \context_helper::get_class_for_level($record->contextlevel);
        list($purposevar, $categoryvar) = data_registry::var_names_from_context($classname);
        set_config($purposevar, $record->purposeid, 'tool_dataprivacy');
        set_config($categoryvar, $record->categoryid, 'tool_dataprivacy');

        return $contextlevel;
    }

    /**
     * Returns the effective category given a context instance.
     *
     * @param \context $context
     * @param int $forcedvalue Use this categoryid value as if this was this context instance category.
     * @return category|false
     */
    public static function get_effective_context_category(\context $context, $forcedvalue = false) {
        if (!data_registry::defaults_set()) {
            return false;
        }

        return data_registry::get_effective_context_value($context, 'category', $forcedvalue);
    }

    /**
     * Returns the effective purpose given a context instance.
     *
     * @param \context $context
     * @param int $forcedvalue Use this purposeid value as if this was this context instance purpose.
     * @return purpose|false
     */
    public static function get_effective_context_purpose(\context $context, $forcedvalue = false) {
        if (!data_registry::defaults_set()) {
            return false;
        }

        return data_registry::get_effective_context_value($context, 'purpose', $forcedvalue);
    }

    /**
     * Returns the effective category given a context level.
     *
     * @param int $contextlevel
     * @return category|false
     */
    public static function get_effective_contextlevel_category($contextlevel) {
        if (!data_registry::defaults_set()) {
            return false;
        }

        return data_registry::get_effective_contextlevel_value($contextlevel, 'category');
    }

    /**
     * Returns the effective purpose given a context level.
     *
     * @param int $contextlevel
     * @param int $forcedvalue Use this purposeid value as if this was this context level purpose.
     * @return purpose|false
     */
    public static function get_effective_contextlevel_purpose($contextlevel, $forcedvalue=false) {
        if (!data_registry::defaults_set()) {
            return false;
        }

        return data_registry::get_effective_contextlevel_value($contextlevel, 'purpose', $forcedvalue);
    }

    /**
     * Creates an expired context record for the provided context id.
     *
     * @param int $contextid
     * @return \tool_dataprivacy\expired_context
     */
    public static function create_expired_context($contextid) {
        $record = (object)[
            'contextid' => $contextid,
            'status' => expired_context::STATUS_EXPIRED,
        ];
        $expiredctx = new expired_context(0, $record);
        $expiredctx->save();

        return $expiredctx;
    }

    /**
     * Deletes an expired context record.
     *
     * @param int $id The tool_dataprivacy_ctxexpire id.
     * @return bool True on success.
     */
    public static function delete_expired_context($id) {
        $expiredcontext = new expired_context($id);
        return $expiredcontext->delete();
    }

    /**
     * Updates the status of an expired context.
     *
     * @param \tool_dataprivacy\expired_context $expiredctx
     * @param int $status
     * @return null
     */
    public static function set_expired_context_status(expired_context $expiredctx, $status) {
        $expiredctx->set('status', $status);
        $expiredctx->save();
    }

    /**
     * Finds all contextlists having at least one approved context, and returns them as in a contextlist_collection.
     *
     * @param   contextlist_collection  $collection The collection of unapproved contextlist objects.
     * @param   \stdClass               $foruser The target user
     * @param   int                     $type The purpose of the collection
     * @return  contextlist_collection  The collection of approved_contextlist objects.
     */
    public static function get_approved_contextlist_collection_for_collection(contextlist_collection $collection,
            \stdClass $foruser, int $type) : contextlist_collection {

        // Create the approved contextlist collection object.
        $approvedcollection = new contextlist_collection($collection->get_userid());
        $isconfigured = data_registry::defaults_set();

        foreach ($collection as $contextlist) {
            $contextids = [];
            foreach ($contextlist as $context) {
                if ($isconfigured && self::DATAREQUEST_TYPE_DELETE == $type) {
                    // Data can only be deleted from it if the context is either expired, or unprotected.
                    // Note: We can only check whether a context is expired or unprotected if the site is configured and
                    // defaults are set appropriately. If they are not, we treat all contexts as though they are
                    // unprotected.
                    $purpose = static::get_effective_context_purpose($context);
                    if (!expired_contexts_manager::is_context_expired_or_unprotected_for_user($context, $foruser)) {
                        continue;
                    }
                }

                $contextids[] = $context->id;
            }

            // The data for the last component contextlist won't have been written yet, so write it now.
            if (!empty($contextids)) {
                $approvedcollection->add_contextlist(
                        new approved_contextlist($foruser, $contextlist->get_component(), $contextids)
                    );
            }
        }

        return $approvedcollection;
    }

    /**
     * Updates the default category and purpose for a given context level (and optionally, a plugin).
     *
     * @param int $contextlevel The context level.
     * @param int $categoryid The ID matching the category.
     * @param int $purposeid The ID matching the purpose record.
     * @param int $activity The name of the activity that we're making a defaults configuration for.
     * @param bool $override Whether to override the purpose/categories of existing instances to these defaults.
     * @return boolean True if set/unset config succeeds. Otherwise, it throws an exception.
     */
    public static function set_context_defaults($contextlevel, $categoryid, $purposeid, $activity = null, $override = false) {
        global $DB;

        // Get the class name associated with this context level.
        $classname = context_helper::get_class_for_level($contextlevel);
        list($purposevar, $categoryvar) = data_registry::var_names_from_context($classname, $activity);

        // Check the default category to be set.
        if ($categoryid == context_instance::INHERIT) {
            unset_config($categoryvar, 'tool_dataprivacy');

        } else {
            // Make sure the given category ID exists first.
            $categorypersistent = new category($categoryid);
            $categorypersistent->read();

            // Then set the new default value.
            set_config($categoryvar, $categoryid, 'tool_dataprivacy');
        }

        // Check the default purpose to be set.
        if ($purposeid == context_instance::INHERIT) {
            // If the defaults is set to inherit, just unset the config value.
            unset_config($purposevar, 'tool_dataprivacy');

        } else {
            // Make sure the given purpose ID exists first.
            $purposepersistent = new purpose($purposeid);
            $purposepersistent->read();

            // Then set the new default value.
            set_config($purposevar, $purposeid, 'tool_dataprivacy');
        }

        // Unset instances that have been assigned with custom purpose and category, if override was specified.
        if ($override) {
            // We'd like to find context IDs that we want to unset.
            $statements = ["SELECT c.id as contextid FROM {context} c"];
            // Based on this context level.
            $params = ['contextlevel' => $contextlevel];

            if ($contextlevel == CONTEXT_MODULE) {
                // If we're deleting module context instances, we need to make sure the instance ID is in the course modules table.
                $statements[] = "JOIN {course_modules} cm ON cm.id = c.instanceid";
                // And that the module is listed on the modules table.
                $statements[] = "JOIN {modules} m ON m.id = cm.module";

                if ($activity) {
                    // If we're overriding for an activity module, make sure that the context instance matches that activity.
                    $statements[] = "AND m.name = :modname";
                    $params['modname'] = $activity;
                }
            }
            // Make sure this context instance exists in the tool_dataprivacy_ctxinstance table.
            $statements[] = "JOIN {tool_dataprivacy_ctxinstance} tdc ON tdc.contextid = c.id";
            // And that the context level of this instance matches the given context level.
            $statements[] = "WHERE c.contextlevel = :contextlevel";

            // Build our SQL query by gluing the statements.
            $sql = implode("\n", $statements);

            // Get the context records matching our query.
            $contextids = $DB->get_fieldset_sql($sql, $params);

            // Delete the matching context instances.
            foreach ($contextids as $contextid) {
                if ($instance = context_instance::get_record_by_contextid($contextid, false)) {
                    self::unset_context_instance($instance);
                }
            }
        }

        return true;
    }

    /**
     * Format the supplied date interval as a retention period.
     *
     * @param   \DateInterval   $interval
     * @return  string
     */
    public static function format_retention_period(\DateInterval $interval) : string {
        // It is one or another.
        if ($interval->y) {
            $formattedtime = get_string('numyears', 'moodle', $interval->format('%y'));
        } else if ($interval->m) {
            $formattedtime = get_string('nummonths', 'moodle', $interval->format('%m'));
        } else if ($interval->d) {
            $formattedtime = get_string('numdays', 'moodle', $interval->format('%d'));
        } else {
            $formattedtime = get_string('retentionperiodzero', 'tool_dataprivacy');
        }

        return $formattedtime;
    }

    /**
     * Whether automatic data request approval is turned on or not for the given request type.
     *
     * @param int $type The request type.
     * @return bool
     */
    public static function is_automatic_request_approval_on(int $type): bool {
        switch ($type) {
            case self::DATAREQUEST_TYPE_EXPORT:
                return !empty(get_config('tool_dataprivacy', 'automaticdataexportapproval'));
            case self::DATAREQUEST_TYPE_DELETE:
                return !empty(get_config('tool_dataprivacy', 'automaticdatadeletionapproval'));
        }
        return false;
    }

    /**
     * Creates an ad-hoc task for the data request.
     *
     * @param int $requestid The data request ID.
     * @param int $userid Optional. The user ID to run the task as, if necessary.
     */
    public static function queue_data_request_task(int $requestid, int $userid = null): void {
        $task = new process_data_request_task();
        $task->set_custom_data(['requestid' => $requestid]);
        if ($userid) {
            $task->set_userid($userid);
        }
        manager::queue_adhoc_task($task, true);
    }
}
