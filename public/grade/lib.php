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
 * Functions used by gradebook plugins and reports.
 *
 * @package   core_grades
 * @copyright 2009 Petr Skoda and Nicolas Connault
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/export/lib.php');

use \core_grades\output\action_bar;
use \core_grades\output\general_action_bar;
use \core\output\single_select;

/**
 * This class iterates over all users that are graded in a course.
 * Returns detailed info about users and their grades.
 *
 * @author Petr Skoda <skodak@moodle.org>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class graded_users_iterator {

    /**
     * The couse whose users we are interested in
     */
    protected $course;

    /**
     * An array of grade items or null if only user data was requested
     */
    protected $grade_items;

    /**
     * The group ID we are interested in. 0 means all groups.
     */
    protected $groupid;

    /**
     * A recordset of graded users
     */
    protected $users_rs;

    /**
     * A recordset of user grades (grade_grade instances)
     */
    protected $grades_rs;

    /**
     * Array used when moving to next user while iterating through the grades recordset
     */
    protected $gradestack;

    /**
     * The first field of the users table by which the array of users will be sorted
     */
    protected $sortfield1;

    /**
     * Should sortfield1 be ASC or DESC
     */
    protected $sortorder1;

    /**
     * The second field of the users table by which the array of users will be sorted
     */
    protected $sortfield2;

    /**
     * Should sortfield2 be ASC or DESC
     */
    protected $sortorder2;

    /**
     * Should users whose enrolment has been suspended be ignored?
     */
    protected $onlyactive = false;

    /**
     * Enable user custom fields
     */
    protected $allowusercustomfields = false;

    /**
     * List of suspended users in course. This includes users whose enrolment status is suspended
     * or enrolment has expired or not started.
     */
    protected $suspendedusers = array();

    /**
     * Constructor
     *
     * @param object $course A course object
     * @param array  $grade_items array of grade items, if not specified only user info returned
     * @param int    $groupid iterate only group users if present
     * @param string $sortfield1 The first field of the users table by which the array of users will be sorted
     * @param string $sortorder1 The order in which the first sorting field will be sorted (ASC or DESC)
     * @param string $sortfield2 The second field of the users table by which the array of users will be sorted
     * @param string $sortorder2 The order in which the second sorting field will be sorted (ASC or DESC)
     */
    public function __construct($course, $grade_items=null, $groupid=0,
                                          $sortfield1='lastname', $sortorder1='ASC',
                                          $sortfield2='firstname', $sortorder2='ASC') {
        $this->course      = $course;
        $this->grade_items = $grade_items;
        $this->groupid     = $groupid;
        $this->sortfield1  = $sortfield1;
        $this->sortorder1  = $sortorder1;
        $this->sortfield2  = $sortfield2;
        $this->sortorder2  = $sortorder2;

        $this->gradestack  = array();
    }

    /**
     * Initialise the iterator
     *
     * @return boolean success
     */
    public function init() {
        global $CFG, $DB;

        $this->close();

        export_verify_grades($this->course->id);
        $course_item = grade_item::fetch_course_item($this->course->id);
        if ($course_item->needsupdate) {
            // Can not calculate all final grades - sorry.
            return false;
        }

        $coursecontext = context_course::instance($this->course->id);

        list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal($coursecontext->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');
        list($gradebookroles_sql, $params) = $DB->get_in_or_equal(explode(',', $CFG->gradebookroles), SQL_PARAMS_NAMED, 'grbr');
        list($enrolledsql, $enrolledparams) = get_enrolled_sql($coursecontext, '', 0, $this->onlyactive);

        $params = array_merge($params, $enrolledparams, $relatedctxparams);

        if ($this->groupid) {
            $groupsql = "INNER JOIN {groups_members} gm ON gm.userid = u.id";
            $groupwheresql = "AND gm.groupid = :groupid";
            // $params contents: gradebookroles
            $params['groupid'] = $this->groupid;
        } else {
            $groupsql = "";
            $groupwheresql = "";
        }

        if (empty($this->sortfield1)) {
            // We must do some sorting even if not specified.
            $ofields = ", u.id AS usrt";
            $order   = "usrt ASC";

        } else {
            $ofields = ", u.$this->sortfield1 AS usrt1";
            $order   = "usrt1 $this->sortorder1";
            if (!empty($this->sortfield2)) {
                $ofields .= ", u.$this->sortfield2 AS usrt2";
                $order   .= ", usrt2 $this->sortorder2";
            }
            if ($this->sortfield1 != 'id' and $this->sortfield2 != 'id') {
                // User order MUST be the same in both queries,
                // must include the only unique user->id if not already present.
                $ofields .= ", u.id AS usrt";
                $order   .= ", usrt ASC";
            }
        }

        $userfields = 'u.*';
        $customfieldssql = '';
        if ($this->allowusercustomfields && !empty($CFG->grade_export_customprofilefields)) {
            $customfieldscount = 0;
            $customfieldsarray = grade_helper::get_user_profile_fields($this->course->id, $this->allowusercustomfields);
            foreach ($customfieldsarray as $field) {
                if (!empty($field->customid)) {
                    $customfieldssql .= "
                            LEFT JOIN (SELECT * FROM {user_info_data}
                                WHERE fieldid = :cf$customfieldscount) cf$customfieldscount
                            ON u.id = cf$customfieldscount.userid";
                    $userfields .= ", cf$customfieldscount.data AS customfield_{$field->customid}";
                    $params['cf'.$customfieldscount] = $field->customid;
                    $customfieldscount++;
                }
            }
        }

        $users_sql = "SELECT $userfields $ofields
                        FROM {user} u
                        JOIN ($enrolledsql) je ON je.id = u.id
                             $groupsql $customfieldssql
                        JOIN (
                                  SELECT DISTINCT ra.userid
                                    FROM {role_assignments} ra
                                   WHERE ra.roleid $gradebookroles_sql
                                     AND ra.contextid $relatedctxsql
                             ) rainner ON rainner.userid = u.id
                         WHERE u.deleted = 0
                             $groupwheresql
                    ORDER BY $order";
        $this->users_rs = $DB->get_recordset_sql($users_sql, $params);

        if (!$this->onlyactive) {
            $context = context_course::instance($this->course->id);
            $this->suspendedusers = get_suspended_userids($context);
        } else {
            $this->suspendedusers = array();
        }

        if (!empty($this->grade_items)) {
            $itemids = array_keys($this->grade_items);
            list($itemidsql, $grades_params) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'items');
            $params = array_merge($params, $grades_params);

            $grades_sql = "SELECT g.* $ofields
                             FROM {grade_grades} g
                             JOIN {user} u ON g.userid = u.id
                             JOIN ($enrolledsql) je ON je.id = u.id
                                  $groupsql
                             JOIN (
                                      SELECT DISTINCT ra.userid
                                        FROM {role_assignments} ra
                                       WHERE ra.roleid $gradebookroles_sql
                                         AND ra.contextid $relatedctxsql
                                  ) rainner ON rainner.userid = u.id
                              WHERE u.deleted = 0
                              AND g.itemid $itemidsql
                              $groupwheresql
                         ORDER BY $order, g.itemid ASC";
            $this->grades_rs = $DB->get_recordset_sql($grades_sql, $params);
        } else {
            $this->grades_rs = false;
        }

        return true;
    }

    /**
     * Returns information about the next user
     * @return mixed array of user info, all grades and feedback or null when no more users found
     */
    public function next_user() {
        if (!$this->users_rs) {
            return false; // no users present
        }

        if (!$this->users_rs->valid()) {
            if ($current = $this->_pop()) {
                // this is not good - user or grades updated between the two reads above :-(
            }

            return false; // no more users
        } else {
            $user = $this->users_rs->current();
            $this->users_rs->next();
        }

        // find grades of this user
        $grade_records = array();
        while (true) {
            if (!$current = $this->_pop()) {
                break; // no more grades
            }

            if (empty($current->userid)) {
                break;
            }

            if ($current->userid != $user->id) {
                // grade of the next user, we have all for this user
                $this->_push($current);
                break;
            }

            $grade_records[$current->itemid] = $current;
        }

        $grades = array();
        $feedbacks = array();

        if (!empty($this->grade_items)) {
            foreach ($this->grade_items as $grade_item) {
                if (!isset($feedbacks[$grade_item->id])) {
                    $feedbacks[$grade_item->id] = new stdClass();
                }
                if (array_key_exists($grade_item->id, $grade_records)) {
                    $feedbacks[$grade_item->id]->feedback       = $grade_records[$grade_item->id]->feedback;
                    $feedbacks[$grade_item->id]->feedbackformat = $grade_records[$grade_item->id]->feedbackformat;
                    unset($grade_records[$grade_item->id]->feedback);
                    unset($grade_records[$grade_item->id]->feedbackformat);
                    $grades[$grade_item->id] = new grade_grade($grade_records[$grade_item->id], false);
                } else {
                    $feedbacks[$grade_item->id]->feedback       = '';
                    $feedbacks[$grade_item->id]->feedbackformat = FORMAT_MOODLE;
                    $grades[$grade_item->id] =
                        new grade_grade(array('userid'=>$user->id, 'itemid'=>$grade_item->id), false);
                }
                $grades[$grade_item->id]->grade_item = $grade_item;
            }
        }

        // Set user suspended status.
        $user->suspendedenrolment = isset($this->suspendedusers[$user->id]);
        $result = new stdClass();
        $result->user      = $user;
        $result->grades    = $grades;
        $result->feedbacks = $feedbacks;
        return $result;
    }

    /**
     * Close the iterator, do not forget to call this function
     */
    public function close() {
        if ($this->users_rs) {
            $this->users_rs->close();
            $this->users_rs = null;
        }
        if ($this->grades_rs) {
            $this->grades_rs->close();
            $this->grades_rs = null;
        }
        $this->gradestack = array();
    }

    /**
     * Should all enrolled users be exported or just those with an active enrolment?
     *
     * @param bool $onlyactive True to limit the export to users with an active enrolment
     */
    public function require_active_enrolment($onlyactive = true) {
        if (!empty($this->users_rs)) {
            debugging('Calling require_active_enrolment() has no effect unless you call init() again', DEBUG_DEVELOPER);
        }
        $this->onlyactive  = $onlyactive;
    }

    /**
     * Allow custom fields to be included
     *
     * @param bool $allow Whether to allow custom fields or not
     * @return void
     */
    public function allow_user_custom_fields($allow = true) {
        if ($allow) {
            $this->allowusercustomfields = true;
        } else {
            $this->allowusercustomfields = false;
        }
    }

    /**
     * Add a grade_grade instance to the grade stack
     *
     * @param grade_grade $grade Grade object
     *
     * @return void
     */
    private function _push($grade) {
        array_push($this->gradestack, $grade);
    }


    /**
     * Remove a grade_grade instance from the grade stack
     *
     * @return grade_grade current grade object
     */
    private function _pop() {
        global $DB;
        if (empty($this->gradestack)) {
            if (empty($this->grades_rs) || !$this->grades_rs->valid()) {
                return null; // no grades present
            }

            $current = $this->grades_rs->current();

            $this->grades_rs->next();

            return $current;
        } else {
            return array_pop($this->gradestack);
        }
    }
}

/**
 * Print a selection popup form of the graded users in a course.
 *
 * @deprecated since 2.0
 *
 * @param int    $course id of the course
 * @param string $actionpage The page receiving the data from the popoup form
 * @param int    $userid   id of the currently selected user (or 'all' if they are all selected)
 * @param int    $groupid id of requested group, 0 means all
 * @param int    $includeall bool include all option
 * @param bool   $return If true, will return the HTML, otherwise, will print directly
 * @return null
 * @todo Final deprecation on Moodle 6.0. See MDL-84680.
 */
#[\core\attribute\deprecated('grade_get_graded_users_select()', since: '5.0', mdl: 'MDL-84673')]
function print_graded_users_selector($course, $actionpage, $userid=0, $groupid=0, $includeall=true, $return=false) {
    \core\deprecation::emit_deprecation(__FUNCTION__);
    global $OUTPUT;
    return $OUTPUT->render(grade_get_graded_users_select(substr($actionpage, 0, strpos($actionpage, '/')), $course, $userid, $groupid, $includeall));
}

/**
 * Return a selection popup form of the graded users in a course.
 *
 * @param string $report name of the report
 * @param int    $course id of the course
 * @param int    $userid id of the currently selected user (or 'all' if they are all selected)
 * @param int    $groupid id of requested group, 0 means all
 * @param bool   $includeall bool include all option
 * @return single_select
 */
function grade_get_graded_users_select($report, $course, $userid, $groupid, $includeall) {
    global $USER, $CFG;

    if (is_null($userid)) {
        $userid = $USER->id;
    }
    $coursecontext = context_course::instance($course->id);
    $defaultgradeshowactiveenrol = !empty($CFG->grade_report_showonlyactiveenrol);
    $showonlyactiveenrol = get_user_preferences('grade_report_showonlyactiveenrol', $defaultgradeshowactiveenrol);
    $showonlyactiveenrol = $showonlyactiveenrol || !has_capability('moodle/course:viewsuspendedusers', $coursecontext);
    $menu = array(); // Will be a list of userid => user name
    $menususpendedusers = array(); // Suspended users go to a separate optgroup.
    $gui = new graded_users_iterator($course, null, $groupid);
    $gui->require_active_enrolment($showonlyactiveenrol);
    $gui->init();
    $label = get_string('selectauser', 'grades');
    if ($includeall) {
        $menu[0] = get_string('allusers', 'grades');
        $label = get_string('selectalloroneuser', 'grades');
    }
    while ($userdata = $gui->next_user()) {
        $user = $userdata->user;
        $userfullname = fullname($user);
        if ($user->suspendedenrolment) {
            $menususpendedusers[$user->id] = $userfullname;
        } else {
            $menu[$user->id] = $userfullname;
        }
    }
    $gui->close();

    if ($includeall) {
        $menu[0] .= " (" . (count($menu) + count($menususpendedusers) - 1) . ")";
    }

    if (!empty($menususpendedusers)) {
        $menu[] = array(get_string('suspendedusers') => $menususpendedusers);
    }
    $gpr = new grade_plugin_return(array('type' => 'report', 'course' => $course, 'groupid' => $groupid));
    $select = new single_select(
        new moodle_url('/grade/report/'.$report.'/index.php', $gpr->get_options()),
        'userid', $menu, $userid
    );
    $select->label = $label;
    $select->formid = 'choosegradeuser';
    return $select;
}

/**
 * Hide warning about changed grades during upgrade to 2.8.
 *
 * @param int $courseid The current course id.
 */
function hide_natural_aggregation_upgrade_notice($courseid) {
    unset_config('show_sumofgrades_upgrade_' . $courseid);
}

/**
 * Hide warning about changed grades during upgrade from 2.8.0-2.8.6 and 2.9.0.
 *
 * @param int $courseid The current course id.
 */
function grade_hide_min_max_grade_upgrade_notice($courseid) {
    unset_config('show_min_max_grades_changed_' . $courseid);
}

/**
 * Use the grade min and max from the grade_grade.
 *
 * This is reserved for core use after an upgrade.
 *
 * @param int $courseid The current course id.
 */
function grade_upgrade_use_min_max_from_grade_grade($courseid) {
    grade_set_setting($courseid, 'minmaxtouse', GRADE_MIN_MAX_FROM_GRADE_GRADE);

    grade_force_full_regrading($courseid);
    // Do this now, because it probably happened to late in the page load to be happen automatically.
    grade_regrade_final_grades($courseid);
}

/**
 * Use the grade min and max from the grade_item.
 *
 * This is reserved for core use after an upgrade.
 *
 * @param int $courseid The current course id.
 */
function grade_upgrade_use_min_max_from_grade_item($courseid) {
    grade_set_setting($courseid, 'minmaxtouse', GRADE_MIN_MAX_FROM_GRADE_ITEM);

    grade_force_full_regrading($courseid);
    // Do this now, because it probably happened to late in the page load to be happen automatically.
    grade_regrade_final_grades($courseid);
}

/**
 * Hide warning about changed grades during upgrade to 2.8.
 *
 * @param int $courseid The current course id.
 */
function hide_aggregatesubcats_upgrade_notice($courseid) {
    unset_config('show_aggregatesubcats_upgrade_' . $courseid);
}

/**
 * Hide warning about changed grades due to bug fixes
 *
 * @param int $courseid The current course id.
 */
function hide_gradebook_calculations_freeze_notice($courseid) {
    unset_config('gradebook_calculations_freeze_' . $courseid);
}

/**
 * Print warning about changed grades during upgrade to 2.8.
 *
 * @param int $courseid The current course id.
 * @param context $context The course context.
 * @param string $thispage The relative path for the current page. E.g. /grade/report/user/index.php
 * @param boolean $return return as string
 *
 * @return nothing or string if $return true
 */
function print_natural_aggregation_upgrade_notice($courseid, $context, $thispage, $return=false) {
    global $CFG, $OUTPUT;
    $html = '';

    // Do not do anything if they cannot manage the grades of this course.
    if (!has_capability('moodle/grade:manage', $context)) {
        return $html;
    }

    $hidesubcatswarning = optional_param('seenaggregatesubcatsupgradedgrades', false, PARAM_BOOL) && confirm_sesskey();
    $showsubcatswarning = get_config('core', 'show_aggregatesubcats_upgrade_' . $courseid);
    $hidenaturalwarning = optional_param('seensumofgradesupgradedgrades', false, PARAM_BOOL) && confirm_sesskey();
    $shownaturalwarning = get_config('core', 'show_sumofgrades_upgrade_' . $courseid);

    $hideminmaxwarning = optional_param('seenminmaxupgradedgrades', false, PARAM_BOOL) && confirm_sesskey();
    $showminmaxwarning = get_config('core', 'show_min_max_grades_changed_' . $courseid);

    $useminmaxfromgradeitem = optional_param('useminmaxfromgradeitem', false, PARAM_BOOL) && confirm_sesskey();
    $useminmaxfromgradegrade = optional_param('useminmaxfromgradegrade', false, PARAM_BOOL) && confirm_sesskey();

    $minmaxtouse = grade_get_setting($courseid, 'minmaxtouse', $CFG->grade_minmaxtouse);

    $gradebookcalculationsfreeze = get_config('core', 'gradebook_calculations_freeze_' . $courseid);
    $acceptgradebookchanges = optional_param('acceptgradebookchanges', false, PARAM_BOOL) && confirm_sesskey();

    // Hide the warning if the user told it to go away.
    if ($hidenaturalwarning) {
        hide_natural_aggregation_upgrade_notice($courseid);
    }
    // Hide the warning if the user told it to go away.
    if ($hidesubcatswarning) {
        hide_aggregatesubcats_upgrade_notice($courseid);
    }

    // Hide the min/max warning if the user told it to go away.
    if ($hideminmaxwarning) {
        grade_hide_min_max_grade_upgrade_notice($courseid);
        $showminmaxwarning = false;
    }

    if ($useminmaxfromgradegrade) {
        // Revert to the new behaviour, we now use the grade_grade for min/max.
        grade_upgrade_use_min_max_from_grade_grade($courseid);
        grade_hide_min_max_grade_upgrade_notice($courseid);
        $showminmaxwarning = false;

    } else if ($useminmaxfromgradeitem) {
        // Apply the new logic, we now use the grade_item for min/max.
        grade_upgrade_use_min_max_from_grade_item($courseid);
        grade_hide_min_max_grade_upgrade_notice($courseid);
        $showminmaxwarning = false;
    }


    if (!$hidenaturalwarning && $shownaturalwarning) {
        $message = get_string('sumofgradesupgradedgrades', 'grades');
        $hidemessage = get_string('upgradedgradeshidemessage', 'grades');
        $urlparams = array( 'id' => $courseid,
                            'seensumofgradesupgradedgrades' => true,
                            'sesskey' => sesskey());
        $goawayurl = new moodle_url($thispage, $urlparams);
        $goawaybutton = $OUTPUT->single_button($goawayurl, $hidemessage, 'get');
        $html .= $OUTPUT->notification($message, 'notifysuccess');
        $html .= $goawaybutton;
    }

    if (!$hidesubcatswarning && $showsubcatswarning) {
        $message = get_string('aggregatesubcatsupgradedgrades', 'grades');
        $hidemessage = get_string('upgradedgradeshidemessage', 'grades');
        $urlparams = array( 'id' => $courseid,
                            'seenaggregatesubcatsupgradedgrades' => true,
                            'sesskey' => sesskey());
        $goawayurl = new moodle_url($thispage, $urlparams);
        $goawaybutton = $OUTPUT->single_button($goawayurl, $hidemessage, 'get');
        $html .= $OUTPUT->notification($message, 'notifysuccess');
        $html .= $goawaybutton;
    }

    if ($showminmaxwarning) {
        $hidemessage = get_string('upgradedgradeshidemessage', 'grades');
        $urlparams = array( 'id' => $courseid,
                            'seenminmaxupgradedgrades' => true,
                            'sesskey' => sesskey());

        $goawayurl = new moodle_url($thispage, $urlparams);
        $hideminmaxbutton = $OUTPUT->single_button($goawayurl, $hidemessage, 'get');
        $moreinfo = html_writer::link(get_docs_url(get_string('minmaxtouse_link', 'grades')), get_string('moreinfo'),
            array('target' => '_blank'));

        if ($minmaxtouse == GRADE_MIN_MAX_FROM_GRADE_ITEM) {
            // Show the message that there were min/max issues that have been resolved.
            $message = get_string('minmaxupgradedgrades', 'grades') . ' ' . $moreinfo;

            $revertmessage = get_string('upgradedminmaxrevertmessage', 'grades');
            $urlparams = array('id' => $courseid,
                               'useminmaxfromgradegrade' => true,
                               'sesskey' => sesskey());
            $reverturl = new moodle_url($thispage, $urlparams);
            $revertbutton = $OUTPUT->single_button($reverturl, $revertmessage, 'get');

            $html .= $OUTPUT->notification($message);
            $html .= $revertbutton . $hideminmaxbutton;

        } else if ($minmaxtouse == GRADE_MIN_MAX_FROM_GRADE_GRADE) {
            // Show the warning that there are min/max issues that have not be resolved.
            $message = get_string('minmaxupgradewarning', 'grades') . ' ' . $moreinfo;

            $fixmessage = get_string('minmaxupgradefixbutton', 'grades');
            $urlparams = array('id' => $courseid,
                               'useminmaxfromgradeitem' => true,
                               'sesskey' => sesskey());
            $fixurl = new moodle_url($thispage, $urlparams);
            $fixbutton = $OUTPUT->single_button($fixurl, $fixmessage, 'get');

            $html .= $OUTPUT->notification($message);
            $html .= $fixbutton . $hideminmaxbutton;
        }
    }

    if ($gradebookcalculationsfreeze) {
        if ($acceptgradebookchanges) {
            // Accept potential changes in grades caused by extra credit bug MDL-49257.
            hide_gradebook_calculations_freeze_notice($courseid);
            $courseitem = grade_item::fetch_course_item($courseid);
            $courseitem->force_regrading();
            grade_regrade_final_grades($courseid);

            $html .= $OUTPUT->notification(get_string('gradebookcalculationsuptodate', 'grades'), 'notifysuccess');
        } else {
            // Show the warning that there may be extra credit weights problems.
            $a = new stdClass();
            $a->gradebookversion = $gradebookcalculationsfreeze;
            if (preg_match('/(\d{8,})/', $CFG->release, $matches)) {
                $a->currentversion = $matches[1];
            } else {
                $a->currentversion = $CFG->release;
            }
            $a->url = get_docs_url('Gradebook_calculation_changes');
            $message = get_string('gradebookcalculationswarning', 'grades', $a);

            $fixmessage = get_string('gradebookcalculationsfixbutton', 'grades');
            $urlparams = array('id' => $courseid,
                'acceptgradebookchanges' => true,
                'sesskey' => sesskey());
            $fixurl = new moodle_url($thispage, $urlparams);
            $fixbutton = $OUTPUT->single_button($fixurl, $fixmessage, 'get');

            $html .= $OUTPUT->notification($message);
            $html .= $fixbutton;
        }
    }

    if (!empty($html)) {
        $html = html_writer::tag('div', $html, array('class' => 'core_grades_notices'));
    }

    if ($return) {
        return $html;
    } else {
        echo $html;
    }
}

/**
 * grade_get_plugin_info
 *
 * @param int    $courseid The course id
 * @param string $active_type type of plugin on current page - import, export, report or edit
 * @param string $active_plugin active plugin type - grader, user, cvs, ...
 *
 * @return array
 */
function grade_get_plugin_info($courseid, $active_type, $active_plugin) {
    global $CFG, $SITE;

    $context = context_course::instance($courseid);

    $plugin_info = array();
    $count = 0;
    $active = '';
    $url_prefix = $CFG->wwwroot . '/grade/';

    // Language strings
    $plugin_info['strings'] = grade_helper::get_plugin_strings();

    if ($reports = grade_helper::get_plugins_reports($courseid)) {
        $plugin_info['report'] = $reports;
    }

    if ($settings = grade_helper::get_info_manage_settings($courseid)) {
        $plugin_info['settings'] = $settings;
    }

    if ($scale = grade_helper::get_info_scales($courseid)) {
        $plugin_info['scale'] = array('view'=>$scale);
    }

    if ($outcomes = grade_helper::get_info_outcomes($courseid)) {
        $plugin_info['outcome'] = $outcomes;
    }

    if ($letters = grade_helper::get_info_letters($courseid)) {
        $plugin_info['letter'] = $letters;
    }

    if ($imports = grade_helper::get_plugins_import($courseid)) {
        $plugin_info['import'] = $imports;
    }

    if ($exports = grade_helper::get_plugins_export($courseid)) {
        $plugin_info['export'] = $exports;
    }

    // Let other plugins add plugins here so that we get extra tabs
    // in the gradebook.
    $callbacks = get_plugins_with_function('extend_gradebook_plugininfo', 'lib.php');
    foreach ($callbacks as $plugins) {
        foreach ($plugins as $pluginfunction) {
            $plugin_info = $pluginfunction($plugin_info, $courseid);
        }
    }

    foreach ($plugin_info as $plugin_type => $plugins) {
        if (!empty($plugins->id) && $active_plugin == $plugins->id) {
            $plugin_info['strings']['active_plugin_str'] = $plugins->string;
            break;
        }
        foreach ($plugins as $plugin) {
            if (is_a($plugin, grade_plugin_info::class)) {
                if ($plugin_type === $active_type && $active_plugin == $plugin->id) {
                    $plugin_info['strings']['active_plugin_str'] = $plugin->string;
                }
            }
        }
    }

    return $plugin_info;
}

/**
 * Load a valid list of gradable users in a course.
 *
 * @param int $courseid The course ID.
 * @param int|null $groupid The group ID (optional).
 * @param bool $onlyactiveenrol Include only active enrolments.
 * @return array $users A list of enrolled gradable users.
 */
function get_gradable_users(int $courseid, ?int $groupid = null, bool $onlyactiveenrol = false): array {
    $course = get_course($courseid);
    // Create a graded_users_iterator because it will properly check the groups etc.
    $gui = new graded_users_iterator($course, null, $groupid);
    $gui->require_active_enrolment($onlyactiveenrol);
    $gui->init();

    // Flatten the users.
    $users = [];
    while ($user = $gui->next_user()) {
        $users[$user->user->id] = $user->user;
    }
    $gui->close();

    return $users;
}

/**
 * A simple class containing info about grade plugins.
 * Can be subclassed for special rules
 *
 * @package core_grades
 * @copyright 2009 Nicolas Connault
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_plugin_info {
    /**
     * A unique id for this plugin
     *
     * @var mixed
     */
    public $id;
    /**
     * A URL to access this plugin
     *
     * @var mixed
     */
    public $link;
    /**
     * The name of this plugin
     *
     * @var mixed
     */
    public $string;
    /**
     * Another grade_plugin_info object, parent of the current one
     *
     * @var mixed
     */
    public $parent;

    /**
     * Constructor
     *
     * @param int $id A unique id for this plugin
     * @param string $link A URL to access this plugin
     * @param string $string The name of this plugin
     * @param object $parent Another grade_plugin_info object, parent of the current one
     *
     * @return void
     */
    public function __construct($id, $link, $string, $parent=null) {
        $this->id = $id;
        $this->link = $link;
        $this->string = $string;
        $this->parent = $parent;
    }
}

/**
 * Prints the page headers, breadcrumb trail, page heading, (optional) navigation and for any gradebook page.
 * All gradebook pages MUST use these functions in favour of the usual print_header(), print_header_simple(),
 * print_heading() etc.
 *
 * @param int $courseid Course id
 * @param string $active_type The type of the current page (report, settings,
 *                            import, export, scales, outcomes, letters)
 * @param string|null $active_plugin The plugin of the current page (grader, fullview etc...)
 * @param string|bool $heading The heading of the page.
 * @param boolean $return Whether to return (true) or echo (false) the HTML generated by this function
 * @param string|bool $buttons Additional buttons to display on the page
 * @param boolean $shownavigation should the gradebook navigation be shown?
 * @param string|null $headerhelpidentifier The help string identifier if required.
 * @param string|null $headerhelpcomponent The component for the help string.
 * @param stdClass|null $user The user object for use with the user context header.
 * @param action_bar|null $actionbar The actions bar which will be displayed on the page if $shownavigation is set
 *                                  to true. If $actionbar is not explicitly defined, the general action bar
 *                                  (\core_grades\output\general_action_bar) will be used by default.
 * @param null $unused This parameter has been deprecated since 4.3 and should not be used anymore.
 * @return string HTML code or nothing if $return == false
 */
function print_grade_page_head(int $courseid, string $active_type, ?string $active_plugin = null, string|bool $heading = false,
       bool $return = false, $buttons = false, bool $shownavigation = true, ?string $headerhelpidentifier = null,
       ?string $headerhelpcomponent = null, ?stdClass $user = null, ?action_bar $actionbar = null, $unused = null) {
    global $CFG, $OUTPUT, $PAGE, $USER;

    if ($heading !== false) {
        // Make sure to trim heading, including the non-breaking space character.
        $heading = str_replace("&nbsp;", " ", $heading);
        $heading = trim($heading);
    }

    if ($unused !== null) {
        debugging('Deprecated argument passed to ' . __FUNCTION__, DEBUG_DEVELOPER);
    }

    // Put a warning on all gradebook pages if the course has modules currently scheduled for background deletion.
    require_once($CFG->dirroot . '/course/lib.php');
    if (course_modules_pending_deletion($courseid, true)) {
        \core\notification::add(get_string('gradesmoduledeletionpendingwarning', 'grades'),
            \core\output\notification::NOTIFY_WARNING);
    }

    if ($active_type === 'preferences') {
        // In Moodle 2.8 report preferences were moved under 'settings'. Allow backward compatibility for 3rd party grade reports.
        $active_type = 'settings';
    }

    $plugin_info = grade_get_plugin_info($courseid, $active_type, $active_plugin);

    // Determine the string of the active plugin.
    $stractive_type = $plugin_info['strings'][$active_type];
    $stractiveplugin = ($active_plugin) ? $plugin_info['strings']['active_plugin_str'] : $heading;

    if ($active_type == 'report') {
        $PAGE->set_pagelayout('report');
    } else {
        $PAGE->set_pagelayout('admin');
    }
    $coursecontext = context_course::instance($courseid);
    // Title will be constituted by information starting from the unique identifying information for the page.
    if ($heading) {
        // If heading is supplied, use this for the page title.
        $uniquetitle = $heading;
    } else if (in_array($active_type, ['report', 'settings'])) {
        // For grade reports or settings pages of grade plugins, use the plugin name for the unique title.
        $uniquetitle = $stractiveplugin;
        // But if editing mode is turned on, check if the report plugin has an editing mode title string and use it if present.
        if ($PAGE->user_is_editing() && $active_type === 'report') {
            $strcomponent = "gradereport_{$active_plugin}";
            if (get_string_manager()->string_exists('editingmode_title', $strcomponent)) {
                $uniquetitle = get_string('editingmode_title', $strcomponent);
            }
        }
    } else {
        $uniquetitle = $stractive_type . ': ' . $stractiveplugin;
    }
    $titlecomponents = [
        $uniquetitle,
        $coursecontext->get_context_name(false),
    ];
    $PAGE->set_title(implode(moodle_page::TITLE_SEPARATOR, $titlecomponents));
    $PAGE->set_heading($PAGE->course->fullname);
    $PAGE->set_secondary_active_tab('grades');

    if ($buttons instanceof single_button) {
        $buttons = $OUTPUT->render($buttons);
    }
    $PAGE->set_button($buttons);
    if ($courseid != SITEID) {
        grade_extend_settings($plugin_info, $courseid);
    }

    // Set the current report as active in the breadcrumbs.
    if ($active_plugin !== null && $reportnav = $PAGE->settingsnav->find($active_plugin, navigation_node::TYPE_SETTING)) {
        $reportnav->make_active();
    }

    $returnval = $OUTPUT->header();

    if (!$return) {
        echo $returnval;
    }

    if ($shownavigation) {
        $renderer = $PAGE->get_renderer('core_grades');
        // If the navigation action bar is not explicitly defined, use the general (default) action bar.
        if (!$actionbar) {
            $actionbar = new general_action_bar($PAGE->context, $PAGE->url, $active_type, $active_plugin);
        }

        if ($return) {
            $returnval .= $renderer->render_action_bar($actionbar);
        } else {
            echo $renderer->render_action_bar($actionbar);
        }
    }

    $output = '';
    // Add a help dialogue box if provided.
    if (isset($headerhelpidentifier) && !empty($heading)) {
        $output = $OUTPUT->heading_with_help($heading, $headerhelpidentifier, $headerhelpcomponent);
    } else if (isset($user)) {
        $renderer = $PAGE->get_renderer('core_grades');
        // If the user is viewing their own grade report, no need to show the "Message"
        // and "Add to contact" buttons in the user heading.
        $showuserbuttons = $user->id != $USER->id && !empty($CFG->messaging) &&
            has_capability('moodle/site:sendmessage', $PAGE->context);
        $output = $renderer->user_heading($user, $courseid, $showuserbuttons);
    } else if (!empty($heading)) {
        $output = $OUTPUT->heading($heading);
    }

    // If any grade penalty plugins are enabled, notify the user that grade penalties will not be applied to imported grades.
    if ($active_type === 'import') {
        foreach (core_plugin_manager::instance()->get_plugins_of_type('gradepenalty') as $plugin) {
            if ($plugin->is_enabled()) {
                $output .= $OUTPUT->notification(
                    get_string('gradepenalties', 'gradeimport_csv'),
                    \core\output\notification::NOTIFY_INFO,
                    false,
                );
                break;
            }
        }
    }

    if ($return) {
        $returnval .= $output;
    } else {
        echo $output;
    }

    $returnval .= print_natural_aggregation_upgrade_notice($courseid, $coursecontext, $PAGE->url, $return);

    if ($return) {
        return $returnval;
    }
}

/**
 * Utility class used for return tracking when using edit and other forms in grade plugins
 *
 * @package core_grades
 * @copyright 2009 Nicolas Connault
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_plugin_return {
    /**
     * Type of grade plugin (e.g. 'edit', 'report')
     *
     * @var string
     */
    public $type;
    /**
     * Name of grade plugin (e.g. 'grader', 'overview')
     *
     * @var string
     */
    public $plugin;
    /**
     * Course id being viewed
     *
     * @var int
     */
    public $courseid;
    /**
     * Id of user whose information is being viewed/edited
     *
     * @var int
     */
    public $userid;
    /**
     * Id of group for which information is being viewed/edited
     *
     * @var int
     */
    public $groupid;
    /**
     * Current page # within output
     *
     * @var int
     */
    public $page;
    /**
     * Search string
     *
     * @var string
     */
    public $search;

    /**
     * Constructor
     *
     * @param array $params - associative array with return parameters, if not supplied parameter are taken from _GET or _POST
     */
    public function __construct($params = []) {
        $this->type     = optional_param('gpr_type', null, PARAM_SAFEDIR);
        $this->plugin   = optional_param('gpr_plugin', null, PARAM_PLUGIN);
        $this->courseid = optional_param('gpr_courseid', null, PARAM_INT);
        $this->userid   = optional_param('gpr_userid', null, PARAM_INT);
        $this->groupid  = optional_param('gpr_groupid', null, PARAM_INT);
        $this->page     = optional_param('gpr_page', null, PARAM_INT);
        $this->search   = optional_param('gpr_search', '', PARAM_NOTAGS);

        foreach ($params as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        // Allow course object rather than id to be used to specify course
        // - avoid unnecessary use of get_course.
        if (array_key_exists('course', $params)) {
            $course = $params['course'];
            $this->courseid = $course->id;
        } else {
            $course = null;
        }
        // If group has been explicitly set in constructor parameters,
        // we should respect that.
        if (!array_key_exists('groupid', $params)) {
            // Otherwise, 'group' in request parameters is a request for a change.
            // In that case, or if we have no group at all, we should get groupid from
            // groups_get_course_group, which will do some housekeeping as well as
            // give us the correct value.
            $changegroup = optional_param('group', -1, PARAM_INT);
            if ($changegroup !== -1 or (empty($this->groupid) and !empty($this->courseid))) {
                if ($course === null) {
                    $course = get_course($this->courseid);
                }
                $this->groupid = groups_get_course_group($course, true);
            }
        }
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function grade_plugin_return($params = null) {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct($params);
    }

    /**
     * Returns return parameters as options array suitable for buttons.
     * @return array options
     */
    public function get_options() {
        if (empty($this->type)) {
            return array();
        }

        $params = array();

        if (!empty($this->plugin)) {
            $params['plugin'] = $this->plugin;
        }

        if (!empty($this->courseid)) {
            $params['id'] = $this->courseid;
        }

        if (!empty($this->userid)) {
            $params['userid'] = $this->userid;
        }

        if (!empty($this->groupid)) {
            $params['group'] = $this->groupid;
        }

        if (!empty($this->page)) {
            $params['page'] = $this->page;
        }

        return $params;
    }

    /**
     * Returns return url
     *
     * @param string $default default url when params not set
     * @param array  $extras Extra URL parameters
     *
     * @return string url
     */
    public function get_return_url($default, $extras=null) {
        global $CFG;

        if (empty($this->type) or empty($this->plugin)) {
            return $default;
        }

        $url = $CFG->wwwroot.'/grade/'.$this->type.'/'.$this->plugin.'/index.php';
        $glue = '?';

        if (!empty($this->courseid)) {
            $url .= $glue.'id='.$this->courseid;
            $glue = '&amp;';
        }

        if (!empty($this->userid)) {
            $url .= $glue.'userid='.$this->userid;
            $glue = '&amp;';
        }

        if (!empty($this->groupid)) {
            $url .= $glue.'group='.$this->groupid;
            $glue = '&amp;';
        }

        if (!empty($this->page)) {
            $url .= $glue.'page='.$this->page;
            $glue = '&amp;';
        }

        if (!empty($extras)) {
            foreach ($extras as $key=>$value) {
                $url .= $glue.$key.'='.$value;
                $glue = '&amp;';
            }
        }

        return $url;
    }

    /**
     * Returns string with hidden return tracking form elements.
     * @return string
     */
    public function get_form_fields() {
        if (empty($this->type)) {
            return '';
        }

        $result  = '<input type="hidden" name="gpr_type" value="'.$this->type.'" />';

        if (!empty($this->plugin)) {
            $result .= '<input type="hidden" name="gpr_plugin" value="'.$this->plugin.'" />';
        }

        if (!empty($this->courseid)) {
            $result .= '<input type="hidden" name="gpr_courseid" value="'.$this->courseid.'" />';
        }

        if (!empty($this->userid)) {
            $result .= '<input type="hidden" name="gpr_userid" value="'.$this->userid.'" />';
        }

        if (!empty($this->groupid)) {
            $result .= '<input type="hidden" name="gpr_groupid" value="'.$this->groupid.'" />';
        }

        if (!empty($this->page)) {
            $result .= '<input type="hidden" name="gpr_page" value="'.$this->page.'" />';
        }

        if (!empty($this->search)) {
            $result .= html_writer::empty_tag('input',
                ['type' => 'hidden', 'name' => 'gpr_search', 'value' => $this->search]);
        }

        return $result;
    }

    /**
     * Add hidden elements into mform
     *
     * @param object &$mform moodle form object
     *
     * @return void
     */
    public function add_mform_elements(&$mform) {
        if (empty($this->type)) {
            return;
        }

        $mform->addElement('hidden', 'gpr_type', $this->type);
        $mform->setType('gpr_type', PARAM_SAFEDIR);

        if (!empty($this->plugin)) {
            $mform->addElement('hidden', 'gpr_plugin', $this->plugin);
            $mform->setType('gpr_plugin', PARAM_PLUGIN);
        }

        if (!empty($this->courseid)) {
            $mform->addElement('hidden', 'gpr_courseid', $this->courseid);
            $mform->setType('gpr_courseid', PARAM_INT);
        }

        if (!empty($this->userid)) {
            $mform->addElement('hidden', 'gpr_userid', $this->userid);
            $mform->setType('gpr_userid', PARAM_INT);
        }

        if (!empty($this->groupid)) {
            $mform->addElement('hidden', 'gpr_groupid', $this->groupid);
            $mform->setType('gpr_groupid', PARAM_INT);
        }

        if (!empty($this->page)) {
            $mform->addElement('hidden', 'gpr_page', $this->page);
            $mform->setType('gpr_page', PARAM_INT);
        }
    }

    /**
     * Add return tracking params into url
     *
     * @param moodle_url $url A URL
     * @return moodle_url with return tracking params
     */
    public function add_url_params(moodle_url $url): moodle_url {
        if (empty($this->type)) {
            return $url;
        }

        $url->param('gpr_type', $this->type);

        if (!empty($this->plugin)) {
            $url->param('gpr_plugin', $this->plugin);
        }

        if (!empty($this->courseid)) {
            $url->param('gpr_courseid' ,$this->courseid);
        }

        if (!empty($this->userid)) {
            $url->param('gpr_userid', $this->userid);
        }

        if (!empty($this->groupid)) {
            $url->param('gpr_groupid', $this->groupid);
        }

        if (!empty($this->page)) {
            $url->param('gpr_page', $this->page);
        }

        return $url;
    }
}

/**
 * Function central to gradebook for building and printing the navigation (breadcrumb trail).
 *
 * @param string $path The path of the calling script (using __FILE__?)
 * @param string $pagename The language string to use as the last part of the navigation (non-link)
 * @param mixed  $id Either a plain integer (assuming the key is 'id') or
 *                   an array of keys and values (e.g courseid => $courseid, itemid...)
 *
 * @return string
 */
function grade_build_nav($path, $pagename=null, $id=null) {
    global $CFG, $COURSE, $PAGE;

    $strgrades = get_string('grades', 'grades');

    // Parse the path and build navlinks from its elements
    $dirroot_length = strlen($CFG->dirroot) + 1; // Add 1 for the first slash
    $path = substr($path, $dirroot_length);
    $path = str_replace('\\', '/', $path);

    $path_elements = explode('/', $path);

    $path_elements_count = count($path_elements);

    // First link is always 'grade'
    $PAGE->navbar->add($strgrades, new moodle_url('/grade/index.php', array('id'=>$COURSE->id)));

    $link = null;
    $numberofelements = 3;

    // Prepare URL params string
    $linkparams = array();
    if (!is_null($id)) {
        if (is_array($id)) {
            foreach ($id as $idkey => $idvalue) {
                $linkparams[$idkey] = $idvalue;
            }
        } else {
            $linkparams['id'] = $id;
        }
    }

    $navlink4 = null;

    // Remove file extensions from filenames
    foreach ($path_elements as $key => $filename) {
        $path_elements[$key] = str_replace('.php', '', $filename);
    }

    // Second level links
    switch ($path_elements[1]) {
        case 'edit': // No link
            if ($path_elements[3] != 'index.php') {
                $numberofelements = 4;
            }
            break;
        case 'import': // No link
            break;
        case 'export': // No link
            break;
        case 'report':
            // $id is required for this link. Do not print it if $id isn't given
            if (!is_null($id)) {
                $link = new moodle_url('/grade/report/index.php', $linkparams);
            }

            if ($path_elements[2] == 'grader') {
                $numberofelements = 4;
            }
            break;

        default:
            // If this element isn't among the ones already listed above, it isn't supported, throw an error.
            debugging("grade_build_nav() doesn't support ". $path_elements[1] .
                    " as the second path element after 'grade'.");
            return false;
    }
    $PAGE->navbar->add(get_string($path_elements[1], 'grades'), $link);

    // Third level links
    if (empty($pagename)) {
        $pagename = get_string($path_elements[2], 'grades');
    }

    switch ($numberofelements) {
        case 3:
            $PAGE->navbar->add($pagename, $link);
            break;
        case 4:
            if ($path_elements[2] == 'grader' AND $path_elements[3] != 'index.php') {
                $PAGE->navbar->add(get_string('pluginname', 'gradereport_grader'), new moodle_url('/grade/report/grader/index.php', $linkparams));
            }
            $PAGE->navbar->add($pagename);
            break;
    }

    return '';
}

/**
 * General structure representing grade items in course
 *
 * @package core_grades
 * @copyright 2009 Nicolas Connault
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_structure {
    public $context;

    public $courseid;

    /**
    * Reference to modinfo for current course (for performance, to save
    * retrieving it from courseid every time). Not actually set except for
    * the grade_tree type.
    * @var course_modinfo
    */
    public $modinfo;

    /**
     * 1D array of grade items only
     */
    public $items;

    /**
     * @deprecated since Moodle 4.4 - please use {@see grade_helper::get_element_icon()}
     */
    #[\core\attribute\deprecated('grade_helper::get_element_icon', since: '4.4', mdl: 'MDL-77326', final: true)]
    public function get_element_icon(): void {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
    }

    /**
     * @deprecated since Moodle 4.4 - please use {@see grade_helper::get_element_type_string()}
     */
    #[\core\attribute\deprecated('grade_helper::get_element_type_string', since: '4.4', mdl: 'MDL-77326', final: true)]
    public function get_element_type_string(): void {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
    }

    /**
     * @deprecated since Moodle 4.4 - please use {@see grade_helper::get_element_header()}
     */
    #[\core\attribute\deprecated('grade_helper::get_element_header', since: '4.4', mdl: 'MDL-77326', final: true)]
    public function get_element_header(): void {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
    }

    /**
     * @deprecated since Moodle 4.4 - please use {@see grade_helper::get_activity_link()}
     */
    #[\core\attribute\deprecated('grade_helper::get_activity_link', since: '4.4', mdl: 'MDL-77326', final: true)]
    private function get_activity_link(): void {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
    }

    /**
     * Returns URL of a page that is supposed to contain detailed grade analysis
     *
     * At the moment, only activity modules are supported. The method generates link
     * to the module's file grade.php with the parameters id (cmid), itemid, itemnumber,
     * gradeid and userid. If the grade.php does not exist, null is returned.
     *
     * @return moodle_url|null URL or null if unable to construct it
     */
    public function get_grade_analysis_url(grade_grade $grade) {
        global $CFG;
        /** @var array static cache of the grade.php file existence flags */
        static $hasgradephp = array();

        if (empty($grade->grade_item) or !($grade->grade_item instanceof grade_item)) {
            throw new coding_exception('Passed grade without the associated grade item');
        }
        $item = $grade->grade_item;

        if (!$item->is_external_item()) {
            // at the moment, only activity modules are supported
            return null;
        }
        if ($item->itemtype !== 'mod') {
            throw new coding_exception('Unknown external itemtype: '.$item->itemtype);
        }
        if (empty($item->iteminstance) or empty($item->itemmodule) or empty($this->modinfo)) {
            return null;
        }

        if (!array_key_exists($item->itemmodule, $hasgradephp)) {
            if (file_exists($CFG->dirroot . '/mod/' . $item->itemmodule . '/grade.php')) {
                $hasgradephp[$item->itemmodule] = true;
            } else {
                $hasgradephp[$item->itemmodule] = false;
            }
        }

        if (!$hasgradephp[$item->itemmodule]) {
            return null;
        }

        $instances = $this->modinfo->get_instances();
        if (empty($instances[$item->itemmodule][$item->iteminstance])) {
            return null;
        }
        $cm = $instances[$item->itemmodule][$item->iteminstance];
        if (!$cm->uservisible) {
            return null;
        }

        $url = new moodle_url('/mod/'.$item->itemmodule.'/grade.php', array(
            'id'         => $cm->id,
            'itemid'     => $item->id,
            'itemnumber' => $item->itemnumber,
            'gradeid'    => $grade->id,
            'userid'     => $grade->userid,
        ));

        return $url;
    }

    /**
     * @deprecated since Moodle 4.2 - The row is not shown anymore - we have {@see core\output\action_menu}.
     */
    #[\core\attribute\deprecated('core\output\action_menu', since: '4.2', mdl: 'MDL-77033', final: true)]
    public function get_grade_analysis_icon(grade_grade $grade) {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
    }

    /**
     * Returns a link leading to the grade analysis page
     *
     * @param grade_grade $grade
     * @return string|null
     */
    public function get_grade_analysis_link(grade_grade $grade): ?string {
        $url = $this->get_grade_analysis_url($grade);
        if (is_null($url)) {
            return null;
        }

        $gradeanalysisstring = get_string('gradeanalysis', 'grades');
        return html_writer::link($url, $gradeanalysisstring,
            ['class' => 'dropdown-item', 'aria-label' => $gradeanalysisstring, 'role' => 'menuitem']);
    }

    /**
     * Returns an action menu for the grade.
     *
     * @param grade_grade $grade A grade_grade object
     * @return string
     */
    public function get_grade_action_menu(grade_grade $grade): string {
        global $OUTPUT;

        $menuitems = [];

        $url = $this->get_grade_analysis_url($grade);
        if ($url) {
            $title = get_string('gradeanalysis', 'core_grades');
            $menuitems[] = new action_menu_link_secondary($url, null, $title);
        }

        if ($menuitems) {
            $menu = new action_menu($menuitems);
            $icon = $OUTPUT->pix_icon('i/moremenu', get_string('actions'));
            $extraclasses = 'btn btn-link btn-icon d-flex no-caret';
            $menu->set_menu_trigger($icon, $extraclasses);
            $menu->set_menu_left();

            return $OUTPUT->render($menu);
        } else {
            return '';
        }
    }

    /**
     * Returns the grade eid - the grade may not exist yet.
     *
     * @param grade_grade $grade_grade A grade_grade object
     *
     * @return string eid
     */
    public function get_grade_eid($grade_grade) {
        if (empty($grade_grade->id)) {
            return 'n'.$grade_grade->itemid.'u'.$grade_grade->userid;
        } else {
            return 'g'.$grade_grade->id;
        }
    }

    /**
     * Returns the grade_item eid
     * @param grade_item $grade_item A grade_item object
     * @return string eid
     */
    public function get_item_eid($grade_item) {
        return 'ig'.$grade_item->id;
    }

    /**
     * Given a grade_tree element, returns an array of parameters
     * used to build an icon for that element.
     *
     * @param array $element An array representing an element in the grade_tree
     *
     * @return array
     */
    public function get_params_for_iconstr($element) {
        $strparams = new stdClass();
        $strparams->category = '';
        $strparams->itemname = '';
        $strparams->itemmodule = '';

        if (!method_exists($element['object'], 'get_name')) {
            return $strparams;
        }

        $strparams->itemname = html_to_text($element['object']->get_name());

        // If element name is categorytotal, get the name of the parent category
        if ($strparams->itemname == get_string('categorytotal', 'grades')) {
            $parent = $element['object']->get_parent_category();
            $strparams->category = $parent->get_name() . ' ';
        } else {
            $strparams->category = '';
        }

        $strparams->itemmodule = null;
        if (isset($element['object']->itemmodule)) {
            $strparams->itemmodule = $element['object']->itemmodule;
        }
        return $strparams;
    }

    /**
     * @deprecated since Moodle 4.2 - The row is not shown anymore - we have {@see core\output\action_menu}.
     */
    #[\core\attribute\deprecated('core\output\action_menu', since: '4.2', mdl: 'MDL-77033', final: true)]
    public function get_reset_icon($element, $gpr, $returnactionmenulink = false) {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
    }

    /**
     * Returns a link to reset weights for the given element.
     *
     * @param array  $element An array representing an element in the grade_tree
     * @param object $gpr A grade_plugin_return object
     * @return string|null
     */
    public function get_reset_weights_link(array $element, object $gpr): ?string {

        // Limit to category items set to use the natural weights aggregation method, and users
        // with the capability to manage grades.
        if ($element['type'] != 'category' || $element['object']->aggregation != GRADE_AGGREGATE_SUM ||
                !has_capability('moodle/grade:manage', $this->context)) {
            return null;
        }

        $title = get_string('resetweightsshort', 'grades');
        $str = get_string('resetweights', 'grades', $this->get_params_for_iconstr($element));
        $url = new moodle_url('/grade/edit/tree/action.php', [
            'id' => $this->courseid,
            'action' => 'resetweights',
            'eid' => $element['eid'],
            'sesskey' => sesskey(),
        ]);
        $gpr->add_url_params($url);
        return html_writer::link($url, $title,
            ['class' => 'dropdown-item', 'aria-label' => $str, 'role' => 'menuitem']);
    }

    /**
     * Returns a link to delete a given element.
     *
     * @param array  $element An array representing an element in the grade_tree
     * @param object $gpr A grade_plugin_return object
     * @return string|null
     */
    public function get_delete_link(array $element, object $gpr): ?string {
        if ($element['type'] == 'item' || ($element['type'] == 'category' && $element['depth'] > 1)) {
            if (grade_edit_tree::element_deletable($element)) {
                $deleteconfirmationurl = new moodle_url('index.php', [
                    'id' => $this->courseid,
                    'action' => 'delete',
                    'confirm' => 1,
                    'eid' => $element['eid'],
                    'sesskey' => sesskey(),
                ]);
                $gpr->add_url_params($deleteconfirmationurl);
                $title = get_string('delete');
                return html_writer::link(
                    '',
                    $title,
                    [
                        'class' => 'dropdown-item',
                        'aria-label' => $title,
                        'role' => 'menuitem',
                        'data-modal' => 'confirmation',
                        'data-modal-title-str' => json_encode(['confirm', 'core']),
                        'data-modal-content-str' => json_encode([
                            'deletecheck',
                            '',
                            $element['object']->get_name()
                        ]),
                        'data-modal-yes-button-str' => json_encode(['delete', 'core']),
                        'data-modal-destination' => $deleteconfirmationurl->out(false),
                    ]
                );
            }
        }
        return null;
    }

    /**
     * Returns a link to duplicate a given element.
     *
     * @param array  $element An array representing an element in the grade_tree
     * @param object $gpr A grade_plugin_return object
     * @return string|null
     */
    public function get_duplicate_link(array $element, object $gpr): ?string {
        if ($element['type'] == 'item' || ($element['type'] == 'category' && $element['depth'] > 1)) {
            if (grade_edit_tree::element_duplicatable($element)) {
                $duplicateparams = [];
                $duplicateparams['id'] = $this->courseid;
                $duplicateparams['action'] = 'duplicate';
                $duplicateparams['eid'] = $element['eid'];
                $duplicateparams['sesskey'] = sesskey();
                $url = new moodle_url('index.php', $duplicateparams);
                $title = get_string('duplicate');
                $gpr->add_url_params($url);
                return html_writer::link($url, $title,
                    ['class' => 'dropdown-item', 'aria-label' => $title, 'role' => 'menuitem']);
            }
        }
        return null;
    }

    /**
     * @deprecated since Moodle 4.2 - The row is not shown anymore - we have {@see core\output\action_menu}.
     */
    #[\core\attribute\deprecated('core\output\action_menu', since: '4.2', mdl: 'MDL-77033', final: true)]
    public function get_edit_icon($element, $gpr, $returnactionmenulink = false) {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
    }

    /**
     * Returns a link leading to the edit grade/grade item/category page
     *
     * @param array  $element An array representing an element in the grade_tree
     * @param object $gpr A grade_plugin_return object
     * @return string|null
     */
    public function get_edit_link(array $element, object $gpr): ?string {
        global $CFG;

        $url = null;
        $title = '';
        if ((!has_capability('moodle/grade:manage', $this->context) &&
            (!($element['type'] == 'grade') || !has_capability('moodle/grade:edit', $this->context)))) {
                return null;
        }

        $object = $element['object'];

        if ($element['type'] == 'grade') {
            if (empty($object->id)) {
                $url = new moodle_url('/grade/edit/tree/grade.php',
                    ['courseid' => $this->courseid, 'itemid' => $object->itemid, 'userid' => $object->userid]);
            } else {
                $url = new moodle_url('/grade/edit/tree/grade.php',
                    ['courseid' => $this->courseid, 'id' => $object->id]);
            }
            $url = $gpr->add_url_params($url);
            $title = get_string('editgrade', 'grades');
        } else if (($element['type'] == 'item') || ($element['type'] == 'categoryitem') ||
            ($element['type'] == 'courseitem')) {
            $url = new moodle_url('#');
            if (empty($object->outcomeid) || empty($CFG->enableoutcomes)) {
                return html_writer::link($url, get_string('itemsedit', 'grades'), [
                    'class' => 'dropdown-item',
                    'aria-label' => get_string('itemsedit', 'grades'),
                    'role' => 'menuitem',
                    'data-gprplugin' => $gpr->plugin,
                    'data-courseid' => $this->courseid,
                    'data-itemid' => $object->id, 'data-trigger' => 'add-item-form'
                ]);
            } else if (count(grade_outcome::fetch_all_available($this->courseid)) > 0) {
                return html_writer::link($url, get_string('itemsedit', 'grades'), [
                    'class' => 'dropdown-item',
                    get_string('itemsedit', 'grades'),
                    'role' => 'menuitem',
                    'data-gprplugin' => $gpr->plugin,
                    'data-courseid' => $this->courseid,
                    'data-itemid' => $object->id, 'data-trigger' => 'add-outcome-form'
                ]);
            }
        } else if ($element['type'] == 'category') {
            $url = new moodle_url('#');
            $title = get_string('categoryedit', 'grades');
            return html_writer::link($url, $title, [
                'class' => 'dropdown-item',
                'aria-label' => $title,
                'role' => 'menuitem',
                'data-gprplugin' => $gpr->plugin,
                'data-courseid' => $this->courseid,
                'data-category' => $object->id,
                'data-trigger' => 'add-category-form'
            ]);
        }
        return html_writer::link($url, $title,
            ['class' => 'dropdown-item', 'aria-label' => $title, 'role' => 'menuitem']);
    }

    /**
     * Returns link to the advanced grading page
     *
     * @param array  $element An array representing an element in the grade_tree
     * @param object $gpr A grade_plugin_return object
     * @return string|null
     */
    public function get_advanced_grading_link(array $element, object $gpr): ?string {
        global $CFG;

        /** @var array static cache of the grade.php file existence flags */
        static $hasgradephp = [];

        $itemtype = $element['object']->itemtype;
        $itemmodule = $element['object']->itemmodule;
        $iteminstance = $element['object']->iteminstance;
        $itemnumber = $element['object']->itemnumber;

        // Links only for module items that have valid instance, module and are
        // called from grade_tree with valid modinfo.
        if ($itemtype == 'mod' && $iteminstance && $itemmodule && $this->modinfo) {

            // Get $cm efficiently and with visibility information using modinfo.
            $instances = $this->modinfo->get_instances();
            if (!empty($instances[$itemmodule][$iteminstance])) {
                $cm = $instances[$itemmodule][$iteminstance];

                // Do not add link if activity is not visible to the current user.
                if ($cm->uservisible) {
                    if (!array_key_exists($itemmodule, $hasgradephp)) {
                        if (file_exists($CFG->dirroot . '/mod/' . $itemmodule . '/grade.php')) {
                            $hasgradephp[$itemmodule] = true;
                        } else {
                            $hasgradephp[$itemmodule] = false;
                        }
                    }

                    // If module has grade.php, add link to that.
                    if ($hasgradephp[$itemmodule]) {
                        $args = array('id' => $cm->id, 'itemnumber' => $itemnumber);
                        if (isset($element['userid'])) {
                            $args['userid'] = $element['userid'];
                        }

                        $url = new moodle_url('/mod/' . $itemmodule . '/grade.php', $args);
                        $title = get_string('advancedgrading', 'gradereport_grader',
                            get_string('pluginname', "mod_{$itemmodule}"));
                        $gpr->add_url_params($url);
                        return html_writer::link($url, $title,
                            ['class' => 'dropdown-item', 'aria-label' => $title, 'role' => 'menuitem']);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @deprecated since Moodle 4.2 - The row is not shown anymore - we have {@see core\output\action_menu}.
     */
    #[\core\attribute\deprecated('core\output\action_menu', since: '4.2', mdl: 'MDL-77033', final: true)]
    public function get_hiding_icon($element, $gpr, $returnactionmenulink = false) {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
    }

    /**
     * Returns a link with url to hide/unhide grade/grade item/grade category
     *
     * @param array  $element An array representing an element in the grade_tree
     * @param object $gpr A grade_plugin_return object
     * @return string|null
     */
    public function get_hiding_link(array $element, object $gpr): ?string {
        if (!$element['object']->can_control_visibility() || !has_capability('moodle/grade:manage', $this->context) ||
            !has_capability('moodle/grade:hide', $this->context)) {
            return null;
        }

        $url = new moodle_url('/grade/edit/tree/action.php',
            ['id' => $this->courseid, 'sesskey' => sesskey(), 'eid' => $element['eid']]);
        $url = $gpr->add_url_params($url);

        if ($element['object']->is_hidden()) {
            $url->param('action', 'show');
            $title = get_string('show');
        } else {
            $url->param('action', 'hide');
            $title = get_string('hide');
        }

        $url = html_writer::link($url, $title,
            ['class' => 'dropdown-item', 'aria-label' => $title, 'role' => 'menuitem']);

        if ($element['type'] == 'grade') {
            $item = $element['object']->grade_item;
            if ($item->hidden) {
                $strparamobj = new stdClass();
                $strparamobj->itemname = $item->get_name(true, true);
                $strnonunhideable = get_string('nonunhideableverbose', 'grades', $strparamobj);
                $url = html_writer::span($title, 'text-muted dropdown-item',
                    ['title' => $strnonunhideable, 'aria-label' => $title, 'role' => 'menuitem']);
            }
        }

        return $url;
    }

    /**
     * @deprecated since Moodle 4.2 - The row is not shown anymore - we have {@see core\output\action_menu}.
     */
    #[\core\attribute\deprecated('core\output\action_menu', since: '4.2', mdl: 'MDL-77033', final: true)]
    public function get_locking_icon($element, $gpr) {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
    }

    /**
     * Returns link to lock/unlock grade/grade item/grade category
     *
     * @param array  $element An array representing an element in the grade_tree
     * @param object $gpr A grade_plugin_return object
     *
     * @return string|null
     */
    public function get_locking_link(array $element, object $gpr): ?string {

        if (has_capability('moodle/grade:manage', $this->context) && isset($element['object'])) {
            $title = '';
            $url = new moodle_url('/grade/edit/tree/action.php',
                ['id' => $this->courseid, 'sesskey' => sesskey(), 'eid' => $element['eid']]);
            $url = $gpr->add_url_params($url);

            if ($element['type'] == 'category') {
                // Grade categories themselves cannot be locked. We lock/unlock their grade items.
                $children = $element['object']->get_children(true);
                $alllocked = true;
                foreach ($children as $child) {
                    if (!$child['object']->is_locked()) {
                        $alllocked = false;
                        break;
                    }
                }
                if ($alllocked && has_capability('moodle/grade:unlock', $this->context)) {
                    $title = get_string('unlock', 'grades');
                    $url->param('action', 'unlock');
                } else if (!$alllocked && has_capability('moodle/grade:lock', $this->context)) {
                    $title = get_string('lock', 'grades');
                    $url->param('action', 'lock');
                } else {
                    return null;
                }
            } else if (($element['type'] == 'grade') && ($element['object']->grade_item->is_locked())) {
                // Don't allow an unlocking action for a grade whose grade item is locked: just print a state icon.
                $strparamobj = new stdClass();
                $strparamobj->itemname = $element['object']->grade_item->get_name(true, true);
                $strnonunlockable = get_string('nonunlockableverbose', 'grades', $strparamobj);
                $title = get_string('unlock', 'grades');
                return html_writer::span($title, 'text-muted dropdown-item', ['title' => $strnonunlockable,
                    'aria-label' => $title, 'role' => 'menuitem']);
            } else if ($element['object']->is_locked()) {
                if (has_capability('moodle/grade:unlock', $this->context)) {
                    $title = get_string('unlock', 'grades');
                    $url->param('action', 'unlock');
                } else {
                    return null;
                }
            } else {
                if (has_capability('moodle/grade:lock', $this->context)) {
                    $title = get_string('lock', 'grades');
                    $url->param('action', 'lock');
                } else {
                    return null;
                }
            }

            return html_writer::link($url, $title,
                ['class' => 'dropdown-item', 'aria-label' => $title, 'role' => 'menuitem']);
        } else {
            return null;
        }
    }

    /**
     * @deprecated since Moodle 4.2 - The row is not shown anymore - we have {@see core\output\action_menu}.
     */
    #[\core\attribute\deprecated('core\output\action_menu', since: '4.2', mdl: 'MDL-77033', final: true)]
    public function get_calculation_icon($element, $gpr, $returnactionmenulink = false) {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
    }

    /**
     * Returns link to edit calculation for a grade item.
     *
     * @param array  $element An array representing an element in the grade_tree
     * @param object $gpr A grade_plugin_return object
     *
     * @return string|null
     */
    public function get_edit_calculation_link(array $element, object $gpr): ?string {

        if (has_capability('moodle/grade:manage', $this->context) && isset($element['object'])) {
            $object = $element['object'];
            $isscale = $object->gradetype == GRADE_TYPE_SCALE;
            $isvalue = $object->gradetype == GRADE_TYPE_VALUE;

            // Show calculation icon only when calculation possible.
            if (!$object->is_external_item() && ($isscale || $isvalue)) {
                $editcalculationstring = get_string('editcalculation', 'grades');
                $url = new moodle_url('/grade/edit/tree/calculation.php',
                    ['courseid' => $this->courseid, 'id' => $object->id]);
                $url = $gpr->add_url_params($url);
                return html_writer::link($url, $editcalculationstring,
                    ['class' => 'dropdown-item', 'aria-label' => $editcalculationstring, 'role' => 'menuitem']);
            }
        }
        return null;
    }

    /**
     * Sets status icons for the grade.
     *
     * @param array $element array with grade item info
     * @return string|null status icons container HTML
     */
    public function set_grade_status_icons(array $element): ?string {
        global $OUTPUT;

        $context = [
            'hidden' => $element['object']->is_hidden(),
        ];

        if ($element['object'] instanceof grade_grade) {
            $grade = $element['object'];
            $context['overridden'] = $grade->is_overridden();
            $context['excluded'] = $grade->is_excluded();
            $context['feedback'] = !empty($grade->feedback) && $grade->load_grade_item()->gradetype != GRADE_TYPE_TEXT;
        }

        $context['classes'] = 'grade_icons data-collapse_gradeicons';

        if ($element['object'] instanceof grade_category) {
            $context['classes'] = 'category_grade_icons';

            $children = $element['object']->get_children(true);
            $alllocked = true;
            foreach ($children as $child) {
                if (!$child['object']->is_locked()) {
                    $alllocked = false;
                    break;
                }
            }
            if ($alllocked) {
                $context['locked'] = true;
            }
        } else {
            $context['locked'] = $element['object']->is_locked();
        }

        // Don't even attempt rendering if there is no status to show.
        if (in_array(true, $context)) {
            return $OUTPUT->render_from_template('core_grades/status_icons', $context);
        } else {
            return null;
        }
    }

    /**
     * Returns an action menu for the grade.
     *
     * @param array $element Array with cell info.
     * @param string $mode Mode - gradeitem or user
     * @param grade_plugin_return $gpr
     * @param moodle_url|null $baseurl
     * @return string
     */
    public function get_cell_action_menu(array $element, string $mode, grade_plugin_return $gpr,
            ?moodle_url $baseurl = null): string {
        global $OUTPUT, $USER;

        $context = new stdClass();

        if ($mode == 'gradeitem' || $mode == 'setup') {
            $editable = true;

            if ($element['type'] == 'grade') {
                $context->datatype = 'grade';

                $item = $element['object']->grade_item;
                if ($item->is_course_item() || $item->is_category_item()) {
                    $editable = (bool)get_config('moodle', 'grade_overridecat');;
                }

                if (!empty($USER->editing)) {
                    if ($editable) {
                        $context->editurl = $this->get_edit_link($element, $gpr);
                    }
                    $context->hideurl = $this->get_hiding_link($element, $gpr);
                    $context->lockurl = $this->get_locking_link($element, $gpr);
                }

                $context->gradeanalysisurl = $this->get_grade_analysis_link($element['object']);
            } else if (($element['type'] == 'item') || ($element['type'] == 'categoryitem') ||
                    ($element['type'] == 'courseitem') || ($element['type'] == 'userfield')) {

                $context->datatype = 'item';

                if ($element['type'] == 'item') {
                    if ($mode == 'setup') {
                        $context->deleteurl = $this->get_delete_link($element, $gpr);
                        $context->duplicateurl = $this->get_duplicate_link($element, $gpr);
                    } else {
                        $context =
                            grade_report::get_additional_context($this->context, $this->courseid,
                                $element, $gpr, $mode, $context, true);
                        $context->advancedgradingurl = $this->get_advanced_grading_link($element, $gpr);
                    }
                    $context->divider1 = true;
                }

                if (($element['type'] == 'item') ||
                    (($element['type'] == 'userfield') && ($element['name'] !== 'fullname'))) {
                    $context->divider2 = true;
                }

                if (!empty($USER->editing) || $mode == 'setup') {
                    if (($element['type'] == 'userfield') && ($element['name'] !== 'fullname')) {
                        $context->divider2 = true;
                    } else if (($mode !== 'setup') && ($element['type'] !== 'userfield')) {
                        $context->divider1 = true;
                        $context->divider2 = true;
                    }

                    if ($element['type'] == 'item') {
                        $context->editurl = $this->get_edit_link($element, $gpr);
                    }

                    $context->editcalculationurl =
                        $this->get_edit_calculation_link($element, $gpr);

                    if (isset($element['object'])) {
                        $object = $element['object'];
                        if ($object->itemmodule !== 'quiz') {
                            $context->hideurl = $this->get_hiding_link($element, $gpr);
                        }
                    }
                    $context->lockurl = $this->get_locking_link($element, $gpr);
                }

                // Sorting item.
                if ($baseurl) {
                    $sortlink = clone($baseurl);
                    if (isset($element['object']->id)) {
                        $sortlink->param('sortitemid', $element['object']->id);
                    } else if ($element['type'] == 'userfield') {
                        $context->datatype = $element['name'];
                        $sortlink->param('sortitemid', $element['name']);
                    }

                    if (($element['type'] == 'userfield') && ($element['name'] == 'fullname')) {
                        $sortlink->param('sortitemid', 'firstname');
                        $context->ascendingfirstnameurl = $this->get_sorting_link($sortlink, $gpr);
                        $context->descendingfirstnameurl = $this->get_sorting_link($sortlink, $gpr, 'desc');

                        $sortlink->param('sortitemid', 'lastname');
                        $context->ascendinglastnameurl = $this->get_sorting_link($sortlink, $gpr);
                        $context->descendinglastnameurl = $this->get_sorting_link($sortlink, $gpr, 'desc');
                    } else {
                        $context->ascendingurl = $this->get_sorting_link($sortlink, $gpr);
                        $context->descendingurl = $this->get_sorting_link($sortlink, $gpr, 'desc');
                    }
                }
                if ($mode !== 'setup') {
                    $context = grade_report::get_additional_context($this->context, $this->courseid,
                        $element, $gpr, $mode, $context);
                }
            } else if ($element['type'] == 'category') {
                $context->datatype = 'category';
                if ($mode !== 'setup') {
                    $mode = 'category';
                    $context = grade_report::get_additional_context($this->context, $this->courseid,
                        $element, $gpr, $mode, $context);
                } else {
                    $context->deleteurl = $this->get_delete_link($element, $gpr);
                    $context->resetweightsurl = $this->get_reset_weights_link($element, $gpr);
                }

                if (!empty($USER->editing) || $mode == 'setup') {
                    if ($mode !== 'setup') {
                        $context->divider1 = true;
                    }
                    $context->editurl = $this->get_edit_link($element, $gpr);
                    $context->hideurl = $this->get_hiding_link($element, $gpr);
                    $context->lockurl = $this->get_locking_link($element, $gpr);
                }
            }

            if (isset($element['object'])) {
                $context->dataid = $element['object']->id;
            } else if ($element['type'] == 'userfield') {
                $context->dataid = $element['name'];
            }

            if ($element['type'] != 'text' && !empty($element['object']->feedback)) {
                $viewfeedbackstring = get_string('viewfeedback', 'grades');
                $context->viewfeedbackurl = html_writer::link('#', $viewfeedbackstring, ['class' => 'dropdown-item',
                    'aria-label' => $viewfeedbackstring, 'role' => 'menuitem', 'data-action' => 'feedback',
                    'data-courseid' => $this->courseid]);
            }
        } else if ($mode == 'user') {
            $context->datatype = 'user';
            $context = grade_report::get_additional_context($this->context, $this->courseid, $element, $gpr, $mode, $context, true);
            $context->dataid = $element['userid'];
        }

        // Omit the second divider if there is nothing between it and the first divider.
        if (!isset($context->ascendingfirstnameurl) && !isset($context->ascendingurl)) {
            $context->divider2 = false;
        }

        if ($mode == 'setup') {
            $context->databoundary = 'window';
        }

        if (!empty($USER->editing) || isset($context->gradeanalysisurl) || isset($context->gradesonlyurl)
                || isset($context->aggregatesonlyurl) || isset($context->fullmodeurl) || isset($context->reporturl0)
                || isset($context->ascendingfirstnameurl) || isset($context->ascendingurl)
                || isset($context->viewfeedbackurl) || ($mode == 'setup')) {
            return $OUTPUT->render_from_template('core_grades/cellmenu', $context);
        }
        return '';
    }

    /**
     * Returns link to sort grade item column
     *
     * @param moodle_url $sortlink A base link for sorting
     * @param object $gpr A grade_plugin_return object
     * @param string $direction Direction od sorting
     * @return string
     */
    public function get_sorting_link(moodle_url $sortlink, object $gpr, string $direction = 'asc'): string {

        if ($direction == 'asc') {
            $title = get_string('asc');
        } else {
            $title = get_string('desc');
        }

        $sortlink->param('sort', $direction);
        $gpr->add_url_params($sortlink);
        return html_writer::link($sortlink, $title,
            ['class' => 'dropdown-item', 'aria-label' => $title, 'role' => 'menuitem']);
    }

}

/**
 * Flat structure similar to grade tree.
 *
 * @uses grade_structure
 * @package core_grades
 * @copyright 2009 Nicolas Connault
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_seq extends grade_structure {

    /**
     * 1D array of elements
     */
    public $elements;

    /**
     * Constructor, retrieves and stores array of all grade_category and grade_item
     * objects for the given courseid. Full objects are instantiated. Ordering sequence is fixed if needed.
     *
     * @param int  $courseid The course id
     * @param bool $category_grade_last category grade item is the last child
     * @param bool $nooutcomes Whether or not outcomes should be included
     */
    public function __construct($courseid, $category_grade_last=false, $nooutcomes=false) {
        global $USER, $CFG;

        $this->courseid   = $courseid;
        $this->context    = context_course::instance($courseid);

        // get course grade tree
        $top_element = grade_category::fetch_course_tree($courseid, true);

        $this->elements = grade_seq::flatten($top_element, $category_grade_last, $nooutcomes);

        foreach ($this->elements as $key=>$unused) {
            $this->items[$this->elements[$key]['object']->id] =& $this->elements[$key]['object'];
        }
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function grade_seq($courseid, $category_grade_last=false, $nooutcomes=false) {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct($courseid, $category_grade_last, $nooutcomes);
    }

    /**
     * Static recursive helper - makes the grade_item for category the last children
     *
     * @param array &$element The seed of the recursion
     * @param bool $category_grade_last category grade item is the last child
     * @param bool $nooutcomes Whether or not outcomes should be included
     *
     * @return array
     */
    public function flatten(&$element, $category_grade_last, $nooutcomes) {
        if (empty($element['children'])) {
            return array();
        }
        $children = array();

        foreach ($element['children'] as $sortorder=>$unused) {
            if ($nooutcomes and $element['type'] != 'category' and
                $element['children'][$sortorder]['object']->is_outcome_item()) {
                continue;
            }
            $children[] = $element['children'][$sortorder];
        }
        unset($element['children']);

        if ($category_grade_last and count($children) > 1 and
            (
                $children[0]['type'] === 'courseitem' or
                $children[0]['type'] === 'categoryitem'
            )
        ) {
            $cat_item = array_shift($children);
            array_push($children, $cat_item);
        }

        $result = array();
        foreach ($children as $child) {
            if ($child['type'] == 'category') {
                $result = $result + grade_seq::flatten($child, $category_grade_last, $nooutcomes);
            } else {
                $child['eid'] = 'i'.$child['object']->id;
                $result[$child['object']->id] = $child;
            }
        }

        return $result;
    }

    /**
     * Parses the array in search of a given eid and returns a element object with
     * information about the element it has found.
     *
     * @param int $eid Gradetree Element ID
     *
     * @return object element
     */
    public function locate_element($eid) {
        // it is a grade - construct a new object
        if (strpos($eid, 'n') === 0) {
            if (!preg_match('/n(\d+)u(\d+)/', $eid, $matches)) {
                return null;
            }

            $itemid = $matches[1];
            $userid = $matches[2];

            //extra security check - the grade item must be in this tree
            if (!$item_el = $this->locate_element('ig'.$itemid)) {
                return null;
            }

            // $gradea->id may be null - means does not exist yet
            $grade = new grade_grade(array('itemid'=>$itemid, 'userid'=>$userid));

            $grade->grade_item =& $item_el['object']; // this may speedup grade_grade methods!
            return array('eid'=>'n'.$itemid.'u'.$userid,'object'=>$grade, 'type'=>'grade');

        } else if (strpos($eid, 'g') === 0) {
            $id = (int) substr($eid, 1);
            if (!$grade = grade_grade::fetch(array('id'=>$id))) {
                return null;
            }
            //extra security check - the grade item must be in this tree
            if (!$item_el = $this->locate_element('ig'.$grade->itemid)) {
                return null;
            }
            $grade->grade_item =& $item_el['object']; // this may speedup grade_grade methods!
            return array('eid'=>'g'.$id,'object'=>$grade, 'type'=>'grade');
        }

        // it is a category or item
        foreach ($this->elements as $element) {
            if ($element['eid'] == $eid) {
                return $element;
            }
        }

        return null;
    }
}

/**
 * This class represents a complete tree of categories, grade_items and final grades,
 * organises as an array primarily, but which can also be converted to other formats.
 * It has simple method calls with complex implementations, allowing for easy insertion,
 * deletion and moving of items and categories within the tree.
 *
 * @uses grade_structure
 * @package core_grades
 * @copyright 2009 Nicolas Connault
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_tree extends grade_structure {

    /**
     * The basic representation of the tree as a hierarchical, 3-tiered array.
     * @var object $top_element
     */
    public $top_element;

    /**
     * 2D array of grade items and categories
     * @var array $levels
     */
    public $levels;

    /**
     * Grade items
     * @var array $items
     */
    public $items;

    /**
     * Constructor, retrieves and stores a hierarchical array of all grade_category and grade_item
     * objects for the given courseid. Full objects are instantiated. Ordering sequence is fixed if needed.
     *
     * @param int   $courseid The Course ID
     * @param bool  $fillers include fillers and colspans, make the levels var "rectangular"
     * @param bool  $category_grade_last category grade item is the last child
     * @param array $collapsed array of collapsed categories
     * @param bool  $nooutcomes Whether or not outcomes should be included
     */
    public function __construct($courseid, $fillers=true, $category_grade_last=false,
                               $collapsed=null, $nooutcomes=false) {
        global $USER, $CFG, $COURSE, $DB;

        $this->courseid   = $courseid;
        $this->levels     = array();
        $this->context    = context_course::instance($courseid);

        if (!empty($COURSE->id) && $COURSE->id == $this->courseid) {
            $course = $COURSE;
        } else {
            $course = $DB->get_record('course', array('id' => $this->courseid));
        }
        $this->modinfo = get_fast_modinfo($course);

        // get course grade tree
        $this->top_element = grade_category::fetch_course_tree($courseid, true);

        // collapse the categories if requested
        if (!empty($collapsed)) {
            grade_tree::category_collapse($this->top_element, $collapsed);
        }

        // no otucomes if requested
        if (!empty($nooutcomes)) {
            grade_tree::no_outcomes($this->top_element);
        }

        // move category item to last position in category
        if ($category_grade_last) {
            grade_tree::category_grade_last($this->top_element);
        }

        if ($fillers) {
            // inject fake categories == fillers
            grade_tree::inject_fillers($this->top_element, 0);
            // add colspans to categories and fillers
            grade_tree::inject_colspans($this->top_element);
        }

        grade_tree::fill_levels($this->levels, $this->top_element, 0);

    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function grade_tree($courseid, $fillers=true, $category_grade_last=false,
                               $collapsed=null, $nooutcomes=false) {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct($courseid, $fillers, $category_grade_last, $collapsed, $nooutcomes);
    }

    /**
     * Static recursive helper - removes items from collapsed categories
     *
     * @param array &$element The seed of the recursion
     * @param array $collapsed array of collapsed categories
     *
     * @return void
     */
    public function category_collapse(&$element, $collapsed) {
        if ($element['type'] != 'category') {
            return;
        }
        if (empty($element['children']) or count($element['children']) < 2) {
            return;
        }

        if (in_array($element['object']->id, $collapsed['aggregatesonly'])) {
            $category_item = reset($element['children']); //keep only category item
            $element['children'] = array(key($element['children'])=>$category_item);

        } else {
            if (in_array($element['object']->id, $collapsed['gradesonly'])) { // Remove category item
                reset($element['children']);
                $first_key = key($element['children']);
                unset($element['children'][$first_key]);
            }
            foreach ($element['children'] as $sortorder=>$child) { // Recurse through the element's children
                grade_tree::category_collapse($element['children'][$sortorder], $collapsed);
            }
        }
    }

    /**
     * Static recursive helper - removes all outcomes
     *
     * @param array &$element The seed of the recursion
     *
     * @return void
     */
    public function no_outcomes(&$element) {
        if ($element['type'] != 'category') {
            return;
        }
        foreach ($element['children'] as $sortorder=>$child) {
            if ($element['children'][$sortorder]['type'] == 'item'
              and $element['children'][$sortorder]['object']->is_outcome_item()) {
                unset($element['children'][$sortorder]);

            } else if ($element['children'][$sortorder]['type'] == 'category') {
                grade_tree::no_outcomes($element['children'][$sortorder]);
            }
        }
    }

    /**
     * Static recursive helper - makes the grade_item for category the last children
     *
     * @param array &$element The seed of the recursion
     *
     * @return void
     */
    public function category_grade_last(&$element) {
        if (empty($element['children'])) {
            return;
        }
        if (count($element['children']) < 2) {
            return;
        }
        $first_item = reset($element['children']);
        if ($first_item['type'] == 'categoryitem' or $first_item['type'] == 'courseitem') {
            // the category item might have been already removed
            $order = key($element['children']);
            unset($element['children'][$order]);
            $element['children'][$order] =& $first_item;
        }
        foreach ($element['children'] as $sortorder => $child) {
            grade_tree::category_grade_last($element['children'][$sortorder]);
        }
    }

    /**
     * Static recursive helper - fills the levels array, useful when accessing tree elements of one level
     *
     * @param array &$levels The levels of the grade tree through which to recurse
     * @param array &$element The seed of the recursion
     * @param int   $depth How deep are we?
     * @return void
     */
    public function fill_levels(&$levels, &$element, $depth) {
        if (!array_key_exists($depth, $levels)) {
            $levels[$depth] = array();
        }

        // prepare unique identifier
        if ($element['type'] == 'category') {
            $element['eid'] = 'cg'.$element['object']->id;
        } else if (in_array($element['type'], array('item', 'courseitem', 'categoryitem'))) {
            $element['eid'] = 'ig'.$element['object']->id;
            $this->items[$element['object']->id] =& $element['object'];
        }

        $levels[$depth][] =& $element;
        $depth++;
        if (empty($element['children'])) {
            return;
        }
        $prev = 0;
        foreach ($element['children'] as $sortorder=>$child) {
            grade_tree::fill_levels($levels, $element['children'][$sortorder], $depth);
            $element['children'][$sortorder]['prev'] = $prev;
            $element['children'][$sortorder]['next'] = 0;
            if ($prev) {
                $element['children'][$prev]['next'] = $sortorder;
            }
            $prev = $sortorder;
        }
    }

    /**
     * Determines whether the grade tree item can be displayed.
     * This is particularly targeted for grade categories that have no total (None) when rendering the grade tree.
     * It checks if the grade tree item is of type 'category', and makes sure that the category, or at least one of children,
     * can be output.
     *
     * @param array $element The grade category element.
     * @return bool True if the grade tree item can be displayed. False, otherwise.
     */
    public static function can_output_item($element) {
        $canoutput = true;

        if ($element['type'] === 'category') {
            $object = $element['object'];
            $category = grade_category::fetch(array('id' => $object->id));
            // Category has total, we can output this.
            if ($category->get_grade_item()->gradetype != GRADE_TYPE_NONE) {
                return true;
            }

            // Category has no total and has no children, no need to output this.
            if (empty($element['children'])) {
                return false;
            }

            $canoutput = false;
            // Loop over children and make sure at least one child can be output.
            foreach ($element['children'] as $child) {
                $canoutput = self::can_output_item($child);
                if ($canoutput) {
                    break;
                }
            }
        }

        return $canoutput;
    }

    /**
     * Static recursive helper - makes full tree (all leafes are at the same level)
     *
     * @param array &$element The seed of the recursion
     * @param int   $depth How deep are we?
     *
     * @return int
     */
    public function inject_fillers(&$element, $depth) {
        $depth++;

        if (empty($element['children'])) {
            return $depth;
        }
        $chdepths = array();
        $chids = array_keys($element['children']);
        $last_child  = end($chids);
        $first_child = reset($chids);

        foreach ($chids as $chid) {
            $chdepths[$chid] = grade_tree::inject_fillers($element['children'][$chid], $depth);
        }
        arsort($chdepths);

        $maxdepth = reset($chdepths);
        foreach ($chdepths as $chid=>$chd) {
            if ($chd == $maxdepth) {
                continue;
            }
            if (!self::can_output_item($element['children'][$chid])) {
                continue;
            }
            for ($i=0; $i < $maxdepth-$chd; $i++) {
                if ($chid == $first_child) {
                    $type = 'fillerfirst';
                } else if ($chid == $last_child) {
                    $type = 'fillerlast';
                } else {
                    $type = 'filler';
                }
                $oldchild =& $element['children'][$chid];
                $element['children'][$chid] = array('object'=>'filler', 'type'=>$type,
                                                    'eid'=>'', 'depth'=>$element['object']->depth,
                                                    'children'=>array($oldchild));
            }
        }

        return $maxdepth;
    }

    /**
     * Static recursive helper - add colspan information into categories
     *
     * @param array &$element The seed of the recursion
     *
     * @return int
     */
    public function inject_colspans(&$element) {
        if (empty($element['children'])) {
            return 1;
        }
        $count = 0;
        foreach ($element['children'] as $key=>$child) {
            if (!self::can_output_item($child)) {
                continue;
            }
            $count += grade_tree::inject_colspans($element['children'][$key]);
        }
        $element['colspan'] = $count;
        return $count;
    }

    /**
     * Parses the array in search of a given eid and returns a element object with
     * information about the element it has found.
     * @param int $eid Gradetree Element ID
     * @return object element
     */
    public function locate_element($eid) {
        // it is a grade - construct a new object
        if (strpos($eid, 'n') === 0) {
            if (!preg_match('/n(\d+)u(\d+)/', $eid, $matches)) {
                return null;
            }

            $itemid = $matches[1];
            $userid = $matches[2];

            //extra security check - the grade item must be in this tree
            if (!$item_el = $this->locate_element('ig'.$itemid)) {
                return null;
            }

            // $gradea->id may be null - means does not exist yet
            $grade = new grade_grade(array('itemid'=>$itemid, 'userid'=>$userid));

            $grade->grade_item =& $item_el['object']; // this may speedup grade_grade methods!
            return array('eid'=>'n'.$itemid.'u'.$userid,'object'=>$grade, 'type'=>'grade');

        } else if (strpos($eid, 'g') === 0) {
            $id = (int) substr($eid, 1);
            if (!$grade = grade_grade::fetch(array('id'=>$id))) {
                return null;
            }
            //extra security check - the grade item must be in this tree
            if (!$item_el = $this->locate_element('ig'.$grade->itemid)) {
                return null;
            }
            $grade->grade_item =& $item_el['object']; // this may speedup grade_grade methods!
            return array('eid'=>'g'.$id,'object'=>$grade, 'type'=>'grade');
        }

        // it is a category or item
        foreach ($this->levels as $row) {
            foreach ($row as $element) {
                if ($element['type'] == 'filler') {
                    continue;
                }
                if ($element['eid'] == $eid) {
                    return $element;
                }
            }
        }

        return null;
    }

    /**
     * Returns a well-formed XML representation of the grade-tree using recursion.
     *
     * @param array  $root The current element in the recursion. If null, starts at the top of the tree.
     * @param string $tabs The control character to use for tabs
     *
     * @return string $xml
     */
    public function exporttoxml($root=null, $tabs="\t") {
        $xml = null;
        $first = false;
        if (is_null($root)) {
            $root = $this->top_element;
            $xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
            $xml .= "<gradetree>\n";
            $first = true;
        }

        $type = 'undefined';
        if (strpos($root['object']->table, 'grade_categories') !== false) {
            $type = 'category';
        } else if (strpos($root['object']->table, 'grade_items') !== false) {
            $type = 'item';
        } else if (strpos($root['object']->table, 'grade_outcomes') !== false) {
            $type = 'outcome';
        }

        $xml .= "$tabs<element type=\"$type\">\n";
        foreach ($root['object'] as $var => $value) {
            if (!is_object($value) && !is_array($value) && !empty($value)) {
                $xml .= "$tabs\t<$var>$value</$var>\n";
            }
        }

        if (!empty($root['children'])) {
            $xml .= "$tabs\t<children>\n";
            foreach ($root['children'] as $sortorder => $child) {
                $xml .= $this->exportToXML($child, $tabs."\t\t");
            }
            $xml .= "$tabs\t</children>\n";
        }

        $xml .= "$tabs</element>\n";

        if ($first) {
            $xml .= "</gradetree>";
        }

        return $xml;
    }

    /**
     * Returns a JSON representation of the grade-tree using recursion.
     *
     * @param array $root The current element in the recursion. If null, starts at the top of the tree.
     * @param string $tabs Tab characters used to indent the string nicely for humans to enjoy
     *
     * @return string
     */
    public function exporttojson($root=null, $tabs="\t") {
        $json = null;
        $first = false;
        if (is_null($root)) {
            $root = $this->top_element;
            $first = true;
        }

        $name = '';


        if (strpos($root['object']->table, 'grade_categories') !== false) {
            $name = $root['object']->fullname;
            if ($name == '?') {
                $name = $root['object']->get_name();
            }
        } else if (strpos($root['object']->table, 'grade_items') !== false) {
            $name = $root['object']->itemname;
        } else if (strpos($root['object']->table, 'grade_outcomes') !== false) {
            $name = $root['object']->itemname;
        }

        $json .= "$tabs {\n";
        $json .= "$tabs\t \"type\": \"{$root['type']}\",\n";
        $json .= "$tabs\t \"name\": \"$name\",\n";

        foreach ($root['object'] as $var => $value) {
            if (!is_object($value) && !is_array($value) && !empty($value)) {
                $json .= "$tabs\t \"$var\": \"$value\",\n";
            }
        }

        $json = substr($json, 0, strrpos($json, ','));

        if (!empty($root['children'])) {
            $json .= ",\n$tabs\t\"children\": [\n";
            foreach ($root['children'] as $sortorder => $child) {
                $json .= $this->exportToJSON($child, $tabs."\t\t");
            }
            $json = substr($json, 0, strrpos($json, ','));
            $json .= "\n$tabs\t]\n";
        }

        if ($first) {
            $json .= "\n}";
        } else {
            $json .= "\n$tabs},\n";
        }

        return $json;
    }

    /**
     * Returns the array of levels
     *
     * @return array
     */
    public function get_levels() {
        return $this->levels;
    }

    /**
     * Returns the array of grade items
     *
     * @return array
     */
    public function get_items() {
        return $this->items;
    }

    /**
     * Returns a specific Grade Item
     *
     * @param int $itemid The ID of the grade_item object
     *
     * @return grade_item
     */
    public function get_item($itemid) {
        if (array_key_exists($itemid, $this->items)) {
            return $this->items[$itemid];
        } else {
            return false;
        }
    }
}

/**
 * Local shortcut function for creating an edit/delete button for a grade_* object.
 * @param string $type 'edit' or 'delete'
 * @param int $courseid The Course ID
 * @param grade_* $object The grade_* object
 * @return string html
 */
function grade_button($type, $courseid, $object) {
    global $CFG, $OUTPUT;
    if (preg_match('/grade_(.*)/', get_class($object), $matches)) {
        $objectidstring = $matches[1] . 'id';
    } else {
        throw new coding_exception('grade_button() only accepts grade_* objects as third parameter!');
    }

    $strdelete = get_string('delete');
    $stredit   = get_string('edit');

    if ($type == 'delete') {
        $url = new moodle_url('index.php', array('id' => $courseid, $objectidstring => $object->id, 'action' => 'delete', 'sesskey' => sesskey()));
    } else if ($type == 'edit') {
        $url = new moodle_url('edit.php', array('courseid' => $courseid, 'id' => $object->id));
    }

    return $OUTPUT->action_icon($url, new pix_icon('t/'.$type, ${'str'.$type}, '', array('class' => 'iconsmall')));

}

/**
 * This method adds settings to the settings block for the grade system and its
 * plugins
 *
 * @global moodle_page $PAGE
 */
function grade_extend_settings($plugininfo, $courseid) {
    global $PAGE;

    $gradenode = $PAGE->settingsnav->prepend(get_string('gradeadministration', 'grades'), null, navigation_node::TYPE_CONTAINER,
        null, 'gradeadmin');

    $strings = array_shift($plugininfo);

    if ($reports = grade_helper::get_plugins_reports($courseid)) {
        foreach ($reports as $report) {
            $gradenode->add($report->string, $report->link, navigation_node::TYPE_SETTING, null, $report->id, new pix_icon('i/report', ''));
        }
    }

    if ($settings = grade_helper::get_info_manage_settings($courseid)) {
        $settingsnode = $gradenode->add($strings['settings'], null, navigation_node::TYPE_CONTAINER);
        foreach ($settings as $setting) {
            $settingsnode->add($setting->string, $setting->link, navigation_node::TYPE_SETTING, null, $setting->id, new pix_icon('i/settings', ''));
        }
    }

    if ($imports = grade_helper::get_plugins_import($courseid)) {
        $importnode = $gradenode->add($strings['import'], null, navigation_node::TYPE_CONTAINER);
        foreach ($imports as $import) {
            $importnode->add($import->string, $import->link, navigation_node::TYPE_SETTING, null, $import->id, new pix_icon('i/import', ''));
        }
    }

    if ($exports = grade_helper::get_plugins_export($courseid)) {
        $exportnode = $gradenode->add($strings['export'], null, navigation_node::TYPE_CONTAINER);
        foreach ($exports as $export) {
            $exportnode->add($export->string, $export->link, navigation_node::TYPE_SETTING, null, $export->id, new pix_icon('i/export', ''));
        }
    }

    if ($letters = grade_helper::get_info_letters($courseid)) {
        $letters = array_shift($letters);
        $gradenode->add($strings['letter'], $letters->link, navigation_node::TYPE_SETTING, null, $letters->id, new pix_icon('i/settings', ''));
    }

    if ($outcomes = grade_helper::get_info_outcomes($courseid)) {
        $outcomes = array_shift($outcomes);
        $gradenode->add($strings['outcome'], $outcomes->link, navigation_node::TYPE_SETTING, null, $outcomes->id, new pix_icon('i/outcomes', ''));
    }

    if ($scales = grade_helper::get_info_scales($courseid)) {
        $gradenode->add($strings['scale'], $scales->link, navigation_node::TYPE_SETTING, null, $scales->id, new pix_icon('i/scales', ''));
    }

    if ($gradenode->contains_active_node()) {
        // If the gradenode is active include the settings base node (gradeadministration) in
        // the navbar, typcially this is ignored.
        $PAGE->navbar->includesettingsbase = true;

        // If we can get the course admin node make sure it is closed by default
        // as in this case the gradenode will be opened
        if ($coursenode = $PAGE->settingsnav->get('courseadmin', navigation_node::TYPE_COURSE)){
            $coursenode->make_inactive();
            $coursenode->forceopen = false;
        }
    }
}

/**
 * Grade helper class
 *
 * This class provides several helpful functions that work irrespective of any
 * current state.
 *
 * @copyright 2010 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class grade_helper {
    /**
     * Cached manage settings info {@see get_info_settings}
     * @var grade_plugin_info|false
     */
    protected static $managesetting = null;
    /**
     * Cached grade report plugins {@see get_plugins_reports}
     * @var array|false
     */
    protected static $gradereports = null;
    /**
     * Cached grade report plugins preferences {@see get_info_scales}
     * @var array|false
     */
    protected static $gradereportpreferences = null;
    /**
     * Cached scale info {@see get_info_scales}
     * @var grade_plugin_info|false
     */
    protected static $scaleinfo = null;
    /**
     * Cached outcome info {@see get_info_outcomes}
     * @var grade_plugin_info|false
     */
    protected static $outcomeinfo = null;
    /**
     * Cached leftter info {@see get_info_letters}
     * @var grade_plugin_info|false
     */
    protected static $letterinfo = null;
    /**
     * Cached grade import plugins {@see get_plugins_import}
     * @var array|false
     */
    protected static $importplugins = null;
    /**
     * Cached grade export plugins {@see get_plugins_export}
     * @var array|false
     */
    protected static $exportplugins = null;
    /**
     * Cached grade plugin strings
     * @var array
     */
    protected static $pluginstrings = null;
    /**
     * Cached grade aggregation strings
     * @var array
     */
    protected static $aggregationstrings = null;

    /**
     * @deprecated since 4.3
     */
    #[\core\attribute\deprecated('get_string', since: '4.3', mdl: 'MDL-78561', final: true)]
    public static function get_lang_string(): void {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
    }

    /**
     * Gets strings commonly used by the describe plugins
     *
     * report => get_string('view'),
     * scale => get_string('scales'),
     * outcome => get_string('outcomes', 'grades'),
     * letter => get_string('letters', 'grades'),
     * export => get_string('export', 'grades'),
     * import => get_string('import'),
     * settings => get_string('settings')
     *
     * @return array
     */
    public static function get_plugin_strings() {
        if (self::$pluginstrings === null) {
            self::$pluginstrings = array(
                'report' => get_string('view'),
                'scale' => get_string('scales'),
                'outcome' => get_string('outcomes', 'grades'),
                'letter' => get_string('letters', 'grades'),
                'export' => get_string('export', 'grades'),
                'import' => get_string('import'),
                'settings' => get_string('edittree', 'grades')
            );
        }
        return self::$pluginstrings;
    }

    /**
     * Gets strings describing the available aggregation methods.
     *
     * @return array
     */
    public static function get_aggregation_strings() {
        if (self::$aggregationstrings === null) {
            self::$aggregationstrings = array(
                GRADE_AGGREGATE_MEAN             => get_string('aggregatemean', 'grades'),
                GRADE_AGGREGATE_WEIGHTED_MEAN    => get_string('aggregateweightedmean', 'grades'),
                GRADE_AGGREGATE_WEIGHTED_MEAN2   => get_string('aggregateweightedmean2', 'grades'),
                GRADE_AGGREGATE_EXTRACREDIT_MEAN => get_string('aggregateextracreditmean', 'grades'),
                GRADE_AGGREGATE_MEDIAN           => get_string('aggregatemedian', 'grades'),
                GRADE_AGGREGATE_MIN              => get_string('aggregatemin', 'grades'),
                GRADE_AGGREGATE_MAX              => get_string('aggregatemax', 'grades'),
                GRADE_AGGREGATE_MODE             => get_string('aggregatemode', 'grades'),
                GRADE_AGGREGATE_SUM              => get_string('aggregatesum', 'grades')
            );
        }
        return self::$aggregationstrings;
    }

    /**
     * Get grade_plugin_info object for managing settings if the user can
     *
     * @param int $courseid
     * @return grade_plugin_info[]
     */
    public static function get_info_manage_settings($courseid) {
        if (self::$managesetting !== null) {
            return self::$managesetting;
        }
        $context = context_course::instance($courseid);
        self::$managesetting = array();
        if ($courseid != SITEID && has_capability('moodle/grade:manage', $context)) {
            self::$managesetting['gradebooksetup'] = new grade_plugin_info('setup',
                new moodle_url('/grade/edit/tree/index.php', array('id' => $courseid)),
                get_string('gradebooksetup', 'grades'));
            self::$managesetting['coursesettings'] = new grade_plugin_info('coursesettings',
                new moodle_url('/grade/edit/settings/index.php', array('id'=>$courseid)),
                get_string('coursegradesettings', 'grades'));
        }
        if (self::$gradereportpreferences === null) {
            self::get_plugins_reports($courseid);
        }
        if (self::$gradereportpreferences) {
            self::$managesetting = array_merge(self::$managesetting, self::$gradereportpreferences);
        }
        return self::$managesetting;
    }
    /**
     * Returns an array of plugin reports as grade_plugin_info objects
     *
     * @param int $courseid
     * @return array
     */
    public static function get_plugins_reports($courseid) {
        global $SITE, $CFG;

        if (self::$gradereports !== null) {
            return self::$gradereports;
        }
        $context = context_course::instance($courseid);
        $gradereports = array();
        $gradepreferences = array();
        foreach (core_component::get_plugin_list('gradereport') as $plugin => $plugindir) {
            //some reports make no sense if we're not within a course
            if ($courseid==$SITE->id && ($plugin=='grader' || $plugin=='user')) {
                continue;
            }

            // Remove outcomes report if outcomes not enabled.
            if ($plugin === 'outcomes' && empty($CFG->enableoutcomes)) {
                continue;
            }

            // Remove ones we can't see
            if (!has_capability('gradereport/'.$plugin.':view', $context)) {
                continue;
            }

            // Singleview doesn't doesn't accomodate for all cap combos yet, so this is hardcoded..
            if ($plugin === 'singleview' && !has_all_capabilities(array('moodle/grade:viewall',
                    'moodle/grade:edit'), $context)) {
                continue;
            }

            $pluginstr = get_string('pluginname', 'gradereport_'.$plugin);
            $url = new moodle_url('/grade/report/'.$plugin.'/index.php', array('id'=>$courseid));
            $gradereports[$plugin] = new grade_plugin_info($plugin, $url, $pluginstr);

            // Add link to preferences tab if such a page exists
            if (file_exists($plugindir.'/preferences.php')) {
                $url = new moodle_url('/grade/report/'.$plugin.'/preferences.php', array('id' => $courseid));
                $gradepreferences[$plugin] = new grade_plugin_info($plugin, $url,
                    get_string('preferences', 'grades') . ': ' . $pluginstr);
            }
        }
        if (count($gradereports) == 0) {
            $gradereports = false;
            $gradepreferences = false;
        } else if (count($gradepreferences) == 0) {
            $gradepreferences = false;
            asort($gradereports);
        } else {
            asort($gradereports);
            asort($gradepreferences);
        }
        self::$gradereports = $gradereports;
        self::$gradereportpreferences = $gradepreferences;
        return self::$gradereports;
    }

    /**
     * Get information on scales
     * @param int $courseid
     * @return grade_plugin_info
     */
    public static function get_info_scales($courseid) {
        if (self::$scaleinfo !== null) {
            return self::$scaleinfo;
        }
        if (has_capability('moodle/course:managescales', context_course::instance($courseid))) {
            $url = new moodle_url('/grade/edit/scale/index.php', array('id'=>$courseid));
            self::$scaleinfo = new grade_plugin_info('scale', $url, get_string('view'));
        } else {
            self::$scaleinfo = false;
        }
        return self::$scaleinfo;
    }
    /**
     * Get information on outcomes
     * @param int $courseid
     * @return grade_plugin_info[]|false
     */
    public static function get_info_outcomes($courseid) {
        global $CFG, $SITE;

        if (self::$outcomeinfo !== null) {
            return self::$outcomeinfo;
        }
        $context = context_course::instance($courseid);
        $canmanage = has_capability('moodle/grade:manage', $context);
        $canupdate = has_capability('moodle/course:update', $context);
        if (!empty($CFG->enableoutcomes) && ($canmanage || $canupdate)) {
            $outcomes = array();
            if ($canupdate) {
                if ($courseid!=$SITE->id) {
                    $url = new moodle_url('/grade/edit/outcome/course.php', array('id'=>$courseid));
                    $outcomes['course'] = new grade_plugin_info('course', $url, get_string('outcomescourse', 'grades'));
                }
                $url = new moodle_url('/grade/edit/outcome/index.php', array('id'=>$courseid));
                $outcomes['edit'] = new grade_plugin_info('edit', $url, get_string('editoutcomes', 'grades'));
                $url = new moodle_url('/grade/edit/outcome/import.php', array('courseid'=>$courseid));
                $outcomes['import'] = new grade_plugin_info('import', $url, get_string('importoutcomes', 'grades'));
            } else {
                if ($courseid!=$SITE->id) {
                    $url = new moodle_url('/grade/edit/outcome/course.php', array('id'=>$courseid));
                    $outcomes['edit'] = new grade_plugin_info('edit', $url, get_string('outcomescourse', 'grades'));
                }
            }
            self::$outcomeinfo = $outcomes;
        } else {
            self::$outcomeinfo = false;
        }
        return self::$outcomeinfo;
    }
    /**
     * Get information on letters
     * @param int $courseid
     * @return array
     */
    public static function get_info_letters($courseid) {
        global $SITE;
        if (self::$letterinfo !== null) {
            return self::$letterinfo;
        }
        $context = context_course::instance($courseid);
        $canmanage = has_capability('moodle/grade:manage', $context);
        $canmanageletters = has_capability('moodle/grade:manageletters', $context);
        if ($canmanage || $canmanageletters) {
            // Redirect to system context when report is accessed from admin settings MDL-31633
            if ($context->instanceid == $SITE->id) {
                $param = array('edit' => 1);
            } else {
                $param = array('edit' => 1,'id' => $context->id);
            }
            self::$letterinfo = array(
                'view' => new grade_plugin_info('view', new moodle_url('/grade/edit/letter/index.php', array('id'=>$context->id)), get_string('view')),
                'edit' => new grade_plugin_info('edit', new moodle_url('/grade/edit/letter/index.php', $param), get_string('edit'))
            );
        } else {
            self::$letterinfo = false;
        }
        return self::$letterinfo;
    }
    /**
     * Get information import plugins
     * @param int $courseid
     * @return array
     */
    public static function get_plugins_import($courseid) {
        global $CFG;

        if (self::$importplugins !== null) {
            return self::$importplugins;
        }
        $importplugins = array();
        $context = context_course::instance($courseid);

        if (has_capability('moodle/grade:import', $context)) {
            foreach (core_component::get_plugin_list('gradeimport') as $plugin => $plugindir) {
                if (!has_capability('gradeimport/'.$plugin.':view', $context)) {
                    continue;
                }
                $pluginstr = get_string('pluginname', 'gradeimport_'.$plugin);
                $url = new moodle_url('/grade/import/'.$plugin.'/index.php', array('id'=>$courseid));
                $importplugins[$plugin] = new grade_plugin_info($plugin, $url, $pluginstr);
            }

            // Show key manager if grade publishing is enabled and the user has xml publishing capability.
            // XML is the only grade import plugin that has publishing feature.
            if ($CFG->gradepublishing && has_capability('gradeimport/xml:publish', $context)) {
                $url = new moodle_url('/grade/import/keymanager.php', array('id'=>$courseid));
                $importplugins['keymanager'] = new grade_plugin_info('keymanager', $url, get_string('keymanager', 'grades'));
            }
        }

        if (count($importplugins) > 0) {
            asort($importplugins);
            self::$importplugins = $importplugins;
        } else {
            self::$importplugins = false;
        }
        return self::$importplugins;
    }
    /**
     * Get information export plugins
     * @param int $courseid
     * @return array
     */
    public static function get_plugins_export($courseid) {
        global $CFG;

        if (self::$exportplugins !== null) {
            return self::$exportplugins;
        }
        $context = context_course::instance($courseid);
        $exportplugins = array();
        $canpublishgrades = 0;
        if (has_capability('moodle/grade:export', $context)) {
            foreach (core_component::get_plugin_list('gradeexport') as $plugin => $plugindir) {
                if (!has_capability('gradeexport/'.$plugin.':view', $context)) {
                    continue;
                }
                // All the grade export plugins has grade publishing capabilities.
                if (has_capability('gradeexport/'.$plugin.':publish', $context)) {
                    $canpublishgrades++;
                }

                $pluginstr = get_string('pluginname', 'gradeexport_'.$plugin);
                $url = new moodle_url('/grade/export/'.$plugin.'/index.php', array('id'=>$courseid));
                $exportplugins[$plugin] = new grade_plugin_info($plugin, $url, $pluginstr);
            }

            // Show key manager if grade publishing is enabled and the user has at least one grade publishing capability.
            if ($CFG->gradepublishing && $canpublishgrades != 0) {
                $url = new moodle_url('/grade/export/keymanager.php', array('id'=>$courseid));
                $exportplugins['keymanager'] = new grade_plugin_info('keymanager', $url, get_string('keymanager', 'grades'));
            }
        }
        if (count($exportplugins) > 0) {
            asort($exportplugins);
            self::$exportplugins = $exportplugins;
        } else {
            self::$exportplugins = false;
        }
        return self::$exportplugins;
    }

    /**
     * Returns the value of a field from a user record
     *
     * @param stdClass $user object
     * @param stdClass $field object
     * @return string value of the field
     */
    public static function get_user_field_value($user, $field) {
        if (!empty($field->customid)) {
            $fieldname = 'customfield_' . $field->customid;
            if (!empty($user->{$fieldname}) || is_numeric($user->{$fieldname})) {
                $fieldvalue = $user->{$fieldname};
            } else {
                $fieldvalue = $field->default;
            }
        } else {
            $fieldvalue = $user->{$field->shortname};
        }
        return $fieldvalue;
    }

    /**
     * Returns an array of user profile fields to be included in export
     *
     * @param int $courseid
     * @param bool $includecustomfields
     * @return array An array of stdClass instances with customid, shortname, datatype, default and fullname fields
     */
    public static function get_user_profile_fields($courseid, $includecustomfields = false) {
        global $CFG, $DB;

        // Gets the fields that have to be hidden
        $hiddenfields = array_map('trim', explode(',', $CFG->hiddenuserfields));
        $context = context_course::instance($courseid);
        $canseehiddenfields = has_capability('moodle/course:viewhiddenuserfields', $context);
        if ($canseehiddenfields) {
            $hiddenfields = array();
        }

        $fields = array();
        require_once($CFG->dirroot.'/user/lib.php');                // Loads user_get_default_fields()
        require_once($CFG->dirroot.'/user/profile/lib.php');        // Loads constants, such as PROFILE_VISIBLE_ALL
        $userdefaultfields = user_get_default_fields();

        // Sets the list of profile fields
        $userprofilefields = array_map('trim', explode(',', $CFG->grade_export_userprofilefields));
        if (!empty($userprofilefields)) {
            foreach ($userprofilefields as $field) {
                $field = trim($field);
                if (in_array($field, $hiddenfields) || !in_array($field, $userdefaultfields)) {
                    continue;
                }
                $obj = new stdClass();
                $obj->customid  = 0;
                $obj->shortname = $field;
                $obj->fullname  = get_string($field);
                $fields[] = $obj;
            }
        }

        // Sets the list of custom profile fields
        $customprofilefields = array_map('trim', explode(',', $CFG->grade_export_customprofilefields));
        if ($includecustomfields && !empty($customprofilefields)) {
            $customfields = profile_get_user_fields_with_data(0);

            foreach ($customfields as $fieldobj) {
                $field = (object)$fieldobj->get_field_config_for_external();
                // Make sure we can display this custom field
                if (!in_array($field->shortname, $customprofilefields)) {
                    continue;
                } else if (in_array($field->shortname, $hiddenfields)) {
                    continue;
                } else if ($field->visible != PROFILE_VISIBLE_ALL && !$canseehiddenfields) {
                    continue;
                }

                $obj = new stdClass();
                $obj->customid  = $field->id;
                $obj->shortname = $field->shortname;
                $obj->fullname  = format_string($field->name);
                $obj->datatype  = $field->datatype;
                $obj->default   = $field->defaultdata;
                $fields[] = $obj;
            }
        }

        return $fields;
    }

    /**
     * This helper method gets a snapshot of all the weights for a course.
     * It is used as a quick method to see if any wieghts have been automatically adjusted.
     * @param int $courseid
     * @return array of itemid -> aggregationcoef2
     */
    public static function fetch_all_natural_weights_for_course($courseid) {
        global $DB;
        $result = array();

        $records = $DB->get_records('grade_items', array('courseid'=>$courseid), 'id', 'id, aggregationcoef2');
        foreach ($records as $record) {
            $result[$record->id] = $record->aggregationcoef2;
        }
        return $result;
    }

    /**
     * Resets all static caches.
     *
     * @return void
     */
    public static function reset_caches() {
        self::$managesetting = null;
        self::$gradereports = null;
        self::$gradereportpreferences = null;
        self::$scaleinfo = null;
        self::$outcomeinfo = null;
        self::$letterinfo = null;
        self::$importplugins = null;
        self::$exportplugins = null;
        self::$pluginstrings = null;
        self::$aggregationstrings = null;
    }

    /**
     * Returns icon of element
     *
     * @param array $element An array representing an element in the grade_tree
     * @param bool  $spacerifnone return spacer if no icon found
     *
     * @return string icon or spacer
     */
    public static function get_element_icon(array $element, bool $spacerifnone = false): string {
        global $CFG, $OUTPUT;
        require_once($CFG->libdir . '/filelib.php');

        $outputstr = '';

        // Object holding pix_icon information before instantiation.
        $icon = new stdClass();
        $icon->attributes = ['class' => 'icon itemicon'];
        $icon->component = 'moodle';

        $none = true;
        switch ($element['type']) {
            case 'item':
            case 'courseitem':
            case 'categoryitem':
                $none = false;

                $iscourse   = $element['object']->is_course_item();
                $iscategory = $element['object']->is_category_item();
                $isscale    = $element['object']->gradetype == GRADE_TYPE_SCALE;
                $isvalue    = $element['object']->gradetype == GRADE_TYPE_VALUE;
                $isoutcome  = !empty($element['object']->outcomeid);

                if ($element['object']->is_calculated()) {
                    $icon->pix = 'i/calc';
                    $icon->title = s(get_string('calculatedgrade', 'grades'));

                } else if (($iscourse || $iscategory) && ($isscale || $isvalue)) {
                    if ($category = $element['object']->get_item_category()) {
                        $aggrstrings = self::get_aggregation_strings();
                        $stragg = $aggrstrings[$category->aggregation];

                        $icon->pix = 'i/calc';
                        $icon->title = s($stragg);

                        switch ($category->aggregation) {
                            case GRADE_AGGREGATE_MEAN:
                            case GRADE_AGGREGATE_MEDIAN:
                            case GRADE_AGGREGATE_WEIGHTED_MEAN:
                            case GRADE_AGGREGATE_WEIGHTED_MEAN2:
                            case GRADE_AGGREGATE_EXTRACREDIT_MEAN:
                                $icon->pix = 'i/agg_mean';
                                break;
                            case GRADE_AGGREGATE_SUM:
                                $icon->pix = 'i/agg_sum';
                                break;
                        }
                    }

                } else if ($element['object']->itemtype == 'mod') {
                    // Prevent outcomes displaying the same icon as the activity they are attached to.
                    if ($isoutcome) {
                        $icon->pix = 'i/outcomes';
                        $icon->title = s(get_string('outcome', 'grades'));
                    } else {
                        $modinfo = get_fast_modinfo($element['object']->courseid);
                        $module = $element['object']->itemmodule;
                        $instanceid = $element['object']->iteminstance;
                        if (isset($modinfo->instances[$module][$instanceid])) {
                            $icon->url = $modinfo->instances[$module][$instanceid]->get_icon_url();
                        } else {
                            $icon->pix = 'monologo';
                            $icon->component = $element['object']->itemmodule;
                        }
                        $icon->title = s(get_string('modulename', $element['object']->itemmodule));
                    }
                } else if ($element['object']->itemtype == 'manual') {
                    if ($element['object']->is_outcome_item()) {
                        $icon->pix = 'i/outcomes';
                        $icon->title = s(get_string('outcome', 'grades'));
                    } else {
                        $icon->pix = 'i/manual_item';
                        $icon->title = s(get_string('manualitem', 'grades'));
                    }
                }
                break;

            case 'category':
                $none = false;
                $icon->pix = 'i/folder';
                $icon->title = s(get_string('category', 'grades'));
                break;
        }

        if ($none) {
            if ($spacerifnone) {
                $outputstr = $OUTPUT->spacer() . ' ';
            }
        } else if (isset($icon->url)) {
            $outputstr = html_writer::img($icon->url, $icon->title, $icon->attributes);
        } else {
            $outputstr = $OUTPUT->pix_icon($icon->pix, $icon->title, $icon->component, $icon->attributes);
        }

        return $outputstr;
    }

    /**
     * Returns the string that describes the type of the element.
     *
     * @param array $element An array representing an element in the grade_tree
     * @return string The string that describes the type of the grade element
     */
    public static function get_element_type_string(array $element): string {
        // If the element is a grade category.
        if ($element['type'] == 'category') {
            return get_string('category', 'grades');
        }
        // If the element is a grade item.
        if (in_array($element['type'], ['item', 'courseitem', 'categoryitem'])) {
            // If calculated grade item.
            if ($element['object']->is_calculated()) {
                return get_string('calculatedgrade', 'grades');
            }
            // If aggregated type grade item.
            if ($element['object']->is_aggregate_item()) {
                return get_string('aggregation', 'core_grades');
            }
            // If external grade item (module, plugin, etc.).
            if ($element['object']->is_external_item()) {
                // If outcome grade item.
                if ($element['object']->is_outcome_item()) {
                    return get_string('outcome', 'grades');
                }
                return get_string('modulename', $element['object']->itemmodule);
            }
            // If manual grade item.
            if ($element['object']->itemtype == 'manual') {
                // If outcome grade item.
                if ($element['object']->is_outcome_item()) {
                    return get_string('outcome', 'grades');
                }
                return get_string('manualitem', 'grades');
            }
        }

        return '';
    }

    /**
     * Returns name of element optionally with icon and link
     *
     * @param array $element An array representing an element in the grade_tree
     * @param bool  $withlink Whether or not this header has a link
     * @param bool  $icon Whether or not to display an icon with this header
     * @param bool  $spacerifnone return spacer if no icon found
     * @param bool  $withdescription Show description if defined by this item.
     * @param bool  $fulltotal If the item is a category total, returns $categoryname."total"
     *                         instead of "Category total" or "Course total"
     * @param moodle_url|null $sortlink Link to sort column.
     *
     * @return string header
     */
    public static function get_element_header(array $element, bool $withlink = false, bool $icon = true,
            bool $spacerifnone = false, bool $withdescription = false, bool $fulltotal = false,
            ?moodle_url $sortlink = null): string {
        $header = '';

        if ($icon) {
            $header .= self::get_element_icon($element, $spacerifnone);
        }

        $title = $element['object']->get_name($fulltotal);
        $titleunescaped = $element['object']->get_name($fulltotal, false);
        $header .= $title;

        if ($element['type'] != 'item' && $element['type'] != 'categoryitem' && $element['type'] != 'courseitem') {
            return $header;
        }

        if ($sortlink) {
            $url = $sortlink;
            $header = html_writer::link($url, $header, [
                'title' => $titleunescaped,
                'class' => 'gradeitemheader ',
            ]);
        } else {
            if ($withlink && $url = self::get_activity_link($element)) {
                $a = new stdClass();
                $a->name = get_string('modulename', $element['object']->itemmodule);
                $a->title = $titleunescaped;
                $title = get_string('linktoactivity', 'grades', $a);
                $header = html_writer::link($url, $header, [
                    'title' => $title,
                    'class' => 'gradeitemheader ',
                ]);
            } else {
                $header = html_writer::span($header, 'gradeitemheader ', [
                    'title' => $titleunescaped,
                    'tabindex' => '0',
                ]);
            }
        }

        if ($withdescription) {
            $desc = $element['object']->get_description();
            if (!empty($desc)) {
                $header .= '<div class="gradeitemdescription">' . s($desc) . '</div><div class="gradeitemdescriptionfiller"></div>';
            }
        }

        return $header;
    }

    /**
     * Returns a link to activity
     *
     * @param array $element An array representing an element in the grade_tree
     * @return moodle_url|null link to activity or null if not found
     */
    public static function get_activity_link(array $element): ?moodle_url {
        $itemtype = $element['object']->itemtype;
        $itemmodule = $element['object']->itemmodule;
        $iteminstance = $element['object']->iteminstance;

        // Links only for module items that have valid instance, module and are
        // called from grade_tree with valid modinfo.
        $modinfo = get_fast_modinfo($element['object']->courseid);
        if ($itemtype != 'mod' || !$iteminstance || !$itemmodule || !$modinfo) {
            return null;
        }

        // Get $cm efficiently and with visibility information using modinfo.
        $instances = $modinfo->get_instances();
        if (empty($instances[$itemmodule][$iteminstance])) {
            return null;
        }
        $cm = $instances[$itemmodule][$iteminstance];

        // Do not add link if activity is not visible to the current user.
        if (!$cm->uservisible) {
            return null;
        }

        return new moodle_url('/mod/' . $itemmodule . '/view.php', ['id' => $cm->id]);
    }
}
