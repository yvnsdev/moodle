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
 * Library methods for the mod_choice activity.
 *
 * @package   mod_choice
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CHOICE_PUBLISH_ANONYMOUS', '0');
define('CHOICE_PUBLISH_NAMES', '1');

define('CHOICE_SHOWRESULTS_NOT', '0');
define('CHOICE_SHOWRESULTS_AFTER_ANSWER', '1');
define('CHOICE_SHOWRESULTS_AFTER_CLOSE', '2');
define('CHOICE_SHOWRESULTS_ALWAYS', '3');

define('CHOICE_DISPLAY_HORIZONTAL', '0');
define('CHOICE_DISPLAY_VERTICAL', '1');

define('CHOICE_EVENT_TYPE_OPEN', 'open');
define('CHOICE_EVENT_TYPE_CLOSE', 'close');

// Standard functions.

/**
 * Get the user summary information for the outline report.
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $choice
 * @return object|null
 */
function choice_user_outline($course, $user, $mod, $choice) {
    global $DB;

    if ($answer = $DB->get_record('choice_answers', ['choiceid' => $choice->id, 'userid' => $user->id])) {
        $result = (object) [
            'info' => "'" . format_string(choice_get_option_text($choice, $answer->optionid)) . "'",
            'time' => $answer->timemodified,
        ];
        return $result;
    }
    return null;
}

/**
 * Callback for the "Complete" report - prints the activity summary for the given user
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $choice
 */
function choice_user_complete($course, $user, $mod, $choice) {
    global $DB;
    if ($answers = $DB->get_records('choice_answers', ["choiceid" => $choice->id, "userid" => $user->id])) {
        $info = [];
        foreach ($answers as $answer) {
            $info[] = "'" . format_string(choice_get_option_text($choice, $answer->optionid)) . "'";
        }
        core_collator::asort($info);
        echo get_string("answered", "choice") . ": " . join(', ', $info) . ". " .
                get_string("updated", '', userdate($answer->timemodified));
    } else {
        print_string("notanswered", "choice");
    }
}

/**
 * Create an instance of the choice activity using the given data.
 *
 * @param object $choice
 * @return int
 */
function choice_add_instance($choice) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/choice/locallib.php');

    $choice->timemodified = time();

    // Insert answers.
    $choice->id = $DB->insert_record("choice", $choice);
    foreach ($choice->option as $key => $value) {
        $value = trim($value);
        if (isset($value) && $value <> '') {
            $option = new stdClass();
            $option->text = $value;
            $option->choiceid = $choice->id;
            if (isset($choice->limit[$key])) {
                $option->maxanswers = $choice->limit[$key];
            }
            $option->timemodified = time();
            $DB->insert_record("choice_options", $option);
        }
    }

    // Add calendar events if necessary.
    choice_set_events($choice);
    if (!empty($choice->completionexpected)) {
        \core_completion\api::update_completion_date_event(
            $choice->coursemodule,
            'choice',
            $choice->id,
            $choice->completionexpected
        );
    }

    return $choice->id;
}

/**
 * Update an instance of the choice activity using the given data.
 *
 * @param object $choice
 * @return bool
 */
function choice_update_instance($choice) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/choice/locallib.php');

    $choice->id = $choice->instance;
    $choice->timemodified = time();

    // Update, delete or insert answers.
    foreach ($choice->option as $key => $value) {
        $value = trim($value);
        $option = new stdClass();
        $option->text = $value;
        $option->choiceid = $choice->id;
        if (isset($choice->limit[$key])) {
            $option->maxanswers = $choice->limit[$key];
        }
        $option->timemodified = time();
        if (isset($choice->optionid[$key]) && !empty($choice->optionid[$key])) {
            // Existing choice record.
            $option->id = $choice->optionid[$key];
            if (isset($value) && $value <> '') {
                $DB->update_record("choice_options", $option);
            } else {
                // Remove the empty (unused) option.
                $DB->delete_records("choice_options", ["id" => $option->id]);
                // Delete any answers associated with this option.
                $DB->delete_records("choice_answers", ["choiceid" => $choice->id, "optionid" => $option->id]);
            }
        } else {
            if (isset($value) && $value <> '') {
                $DB->insert_record("choice_options", $option);
            }
        }
    }

    // Add calendar events if necessary.
    choice_set_events($choice);
    $completionexpected = (!empty($choice->completionexpected)) ? $choice->completionexpected : null;
    \core_completion\api::update_completion_date_event($choice->coursemodule, 'choice', $choice->id, $completionexpected);

    return $DB->update_record('choice', $choice);
}

/**
 * Prepare the options for display in the choice activity.
 *
 * @param object $choice
 * @param object $user
 * @param object $coursemodule
 * @param array $allresponses
 * @return array
 */
function choice_prepare_options($choice, $user, $coursemodule, $allresponses) {
    global $DB;

    $cdisplay = ['options' => []];

    $cdisplay['limitanswers'] = $choice->limitanswers;
    $cdisplay['showavailable'] = $choice->showavailable;

    $context = context_module::instance($coursemodule->id);

    foreach ($choice->option as $optionid => $text) {
        if (isset($text)) {
            // Make sure there are no dud entries in the db with blank text values.
            $option = new stdClass();
            $option->attributes = new stdClass();
            $option->attributes->value = $optionid;
            $option->text = format_string($text);
            $option->maxanswers = $choice->maxanswers[$optionid];
            $option->displaylayout = $choice->display;

            if (isset($allresponses[$optionid])) {
                $option->countanswers = count($allresponses[$optionid]);
            } else {
                $option->countanswers = 0;
            }
            if ($DB->record_exists('choice_answers', ['choiceid' => $choice->id, 'userid' => $user->id, 'optionid' => $optionid])) {
                $option->attributes->checked = true;
            }
            if ($choice->limitanswers && ($option->countanswers >= $option->maxanswers) && empty($option->attributes->checked)) {
                $option->attributes->disabled = true;
            }
            $cdisplay['options'][] = $option;
        }
    }

    // Only enrolled users are allowed to make a choice.
    $cdisplay['hascapability'] = is_enrolled($context, null, 'mod/choice:choose');

    if ($choice->allowupdate && $DB->record_exists('choice_answers', ['choiceid' => $choice->id, 'userid' => $user->id])) {
        $cdisplay['allowupdate'] = true;
    }

    if ($choice->showpreview && $choice->timeopen > time()) {
        $cdisplay['previewonly'] = true;
    }

    return $cdisplay;
}

/**
 * Modifies responses of other users adding the option $newoptionid to them.
 *
 * @param array $userids list of users to add option to (must be users without any answers yet)
 * @param array $answerids list of existing attempt ids of users (will be either appended or
 *      substituted with the newoptionid, depending on $choice->allowmultiple)
 * @param int $newoptionid
 * @param stdClass $choice choice object, result of {@link choice_get_choice()}
 * @param stdClass $cm
 * @param stdClass $course
 */
function choice_modify_responses($userids, $answerids, $newoptionid, $choice, $cm, $course) {
    // Get all existing responses and the list of non-respondents.
    $groupmode = groups_get_activity_groupmode($cm);
    $onlyactive = $choice->includeinactive ? false : true;
    $allresponses = choice_get_response_data($choice, $cm, $groupmode, $onlyactive);

    // Check that the option value is valid.
    if (!$newoptionid || !isset($choice->option[$newoptionid])) {
        return;
    }

    // First add responses for users who did not make any choice yet.
    foreach ($userids as $userid) {
        if (isset($allresponses[0][$userid])) {
            choice_user_submit_response($newoptionid, $choice, $userid, $course, $cm);
        }
    }

    // Create the list of all options already selected by each user.
    $optionsbyuser = []; // Mapping userid=>array of chosen choice options.
    $usersbyanswer = []; // Mapping answerid=>userid (which answer belongs to each user).
    foreach ($allresponses as $optionid => $responses) {
        if ($optionid > 0) {
            foreach ($responses as $userid => $userresponse) {
                $optionsbyuser += [$userid => []];
                $optionsbyuser[$userid][] = $optionid;
                $usersbyanswer[$userresponse->answerid] = $userid;
            }
        }
    }

    // Go through the list of submitted attemptids and find which users answers need to be updated.
    foreach ($answerids as $answerid) {
        if (isset($usersbyanswer[$answerid])) {
            $userid = $usersbyanswer[$answerid];
            if (!in_array($newoptionid, $optionsbyuser[$userid])) {
                $options = $choice->allowmultiple ?
                        array_merge($optionsbyuser[$userid], [$newoptionid]) : $newoptionid;
                choice_user_submit_response($options, $choice, $userid, $course, $cm);
            }
        }
    }
}

/**
 * Process user submitted answers for a choice,
 * and either updating them or saving new answers.
 *
 * @param int|array $formanswer the id(s) of the user submitted choice options.
 * @param object $choice the selected choice.
 * @param int $userid user identifier.
 * @param object $course current course.
 * @param object $cm course context.
 */
function choice_user_submit_response($formanswer, $choice, $userid, $course, $cm) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir . '/completionlib.php');

    $continueurl = new moodle_url('/mod/choice/view.php', ['id' => $cm->id]);

    if (empty($formanswer)) {
        throw new \moodle_exception('atleastoneoption', 'choice', $continueurl);
    }

    if (is_array($formanswer)) {
        if (!$choice->allowmultiple) {
            throw new \moodle_exception('multiplenotallowederror', 'choice', $continueurl);
        }
        $formanswers = $formanswer;
    } else {
        $formanswers = [$formanswer];
    }

    $options = $DB->get_records('choice_options', ['choiceid' => $choice->id], '', 'id');
    foreach ($formanswers as $key => $val) {
        if (!isset($options[$val])) {
            throw new \moodle_exception('cannotsubmit', 'choice', $continueurl);
        }
    }
    // Start lock to prevent synchronous access to the same data
    // before it's updated, if using limits.
    if ($choice->limitanswers) {
        $timeout = 10;
        $locktype = 'mod_choice_choice_user_submit_response';
        // Limiting access to this choice.
        $resouce = 'choiceid:' . $choice->id;
        $lockfactory = \core\lock\lock_config::get_lock_factory($locktype);

        // Opening the lock.
        $choicelock = $lockfactory->get_lock($resouce, $timeout, MINSECS);
        if (!$choicelock) {
            throw new \moodle_exception('cannotsubmit', 'choice', $continueurl);
        }
    }

    $current = $DB->get_records('choice_answers', ['choiceid' => $choice->id, 'userid' => $userid]);

    // Array containing [answerid => optionid] mapping.
    $existinganswers = array_map(function ($answer) {
        return $answer->optionid;
    }, $current);

    $context = context_module::instance($cm->id);

    $choicesexceeded = false;
    $countanswers = [];
    foreach ($formanswers as $val) {
        $countanswers[$val] = 0;
    }
    if ($choice->limitanswers) {
        // Find out whether groups are being used and enabled.
        if (groups_get_activity_groupmode($cm) > 0) {
            $currentgroup = groups_get_activity_group($cm);
        } else {
            $currentgroup = 0;
        }

         [$insql, $params] = $DB->get_in_or_equal($formanswers, SQL_PARAMS_NAMED);

        if ($currentgroup) {
            // If groups are being used, retrieve responses only for users in current group.
            $params['groupid'] = $currentgroup;
            $sql = "SELECT ca.*
                      FROM {choice_answers} ca
                INNER JOIN {groups_members} gm ON ca.userid=gm.userid
                     WHERE optionid $insql
                       AND gm.groupid= :groupid";
        } else {
            // Groups are not used, retrieve all answers for this option ID.
            $sql = "SELECT ca.*
                      FROM {choice_answers} ca
                     WHERE optionid $insql";
        }

        $answers = $DB->get_records_sql($sql, $params);
        if ($answers) {
            foreach ($answers as $a) {
                // Only return enrolled users.
                if (is_enrolled($context, $a->userid, 'mod/choice:choose')) {
                    $countanswers[$a->optionid]++;
                }
            }
        }

        foreach ($countanswers as $opt => $count) {
            // Ignore the user's existing answers when checking whether an answer count has been exceeded.
            // A user may wish to update their response with an additional choice option and shouldn't be competing with themself!
            if (in_array($opt, $existinganswers)) {
                continue;
            }
            if ($count >= $choice->maxanswers[$opt]) {
                $choicesexceeded = true;
                break;
            }
        }
    }

    // Check the user hasn't exceeded the maximum selections for the choice(s) they have selected.
    $answersnapshots = [];
    $deletedanswersnapshots = [];
    if (!($choice->limitanswers && $choicesexceeded)) {
        if ($current) {
            // Update an existing answer.
            foreach ($current as $c) {
                if (in_array($c->optionid, $formanswers)) {
                    $DB->set_field('choice_answers', 'timemodified', time(), ['id' => $c->id]);
                } else {
                    $deletedanswersnapshots[] = $c;
                    $DB->delete_records('choice_answers', ['id' => $c->id]);
                }
            }

            // Add new ones.
            foreach ($formanswers as $f) {
                if (!in_array($f, $existinganswers)) {
                    $newanswer = new stdClass();
                    $newanswer->optionid = $f;
                    $newanswer->choiceid = $choice->id;
                    $newanswer->userid = $userid;
                    $newanswer->timemodified = time();
                    $newanswer->id = $DB->insert_record("choice_answers", $newanswer);
                    $answersnapshots[] = $newanswer;
                }
            }
        } else {
            // Add new answer.
            foreach ($formanswers as $answer) {
                $newanswer = new stdClass();
                $newanswer->choiceid = $choice->id;
                $newanswer->userid = $userid;
                $newanswer->optionid = $answer;
                $newanswer->timemodified = time();
                $newanswer->id = $DB->insert_record("choice_answers", $newanswer);
                $answersnapshots[] = $newanswer;
            }

            // Update completion state.
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) && $choice->completionsubmit) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
        }
    } else {
        // This is a choice with limited options, and one of the options selected has just run over its limit.
        $choicelock->release();
        throw new \moodle_exception('choicefull', 'choice', $continueurl);
    }

    // Release lock.
    if (isset($choicelock)) {
        $choicelock->release();
    }

    // Trigger events.
    foreach ($deletedanswersnapshots as $answer) {
        \mod_choice\event\answer_deleted::create_from_object($answer, $choice, $cm, $course)->trigger();
    }
    foreach ($answersnapshots as $answer) {
        \mod_choice\event\answer_created::create_from_object($answer, $choice, $cm, $course)->trigger();
    }
}

/**
 * Print the report link for a choice activity.
 *
 * @param array $user
 * @param object $cm
 * @return void Output is echo'd
 */
function choice_show_reportlink($user, $cm) {
    $userschosen = [];
    foreach ($user as $optionid => $userlist) {
        if ($optionid) {
            $userschosen = array_merge($userschosen, array_keys($userlist));
        }
    }
    $responsecount = count(array_unique($userschosen));

    echo '<div class="reportlink">';
    echo "<a href=\"report.php?id=$cm->id\">" . get_string("viewallresponses", "choice", $responsecount) . "</a>";
    echo '</div>';
}

/**
 * Prepares the data for showing the results of a choice activity.
 *
 * @param object $choice
 * @param object $course
 * @param object $coursemodule
 * @param array $allresponses
 * @param bool $allresponses
 * @return object|bool
 */
function prepare_choice_show_results($choice, $course, $cm, $allresponses) {
    global $OUTPUT;

    $display = clone($choice);
    $display->coursemoduleid = $cm->id;
    $display->courseid = $course->id;

    if (!empty($choice->showunanswered)) {
        $choice->option[0] = get_string('notanswered', 'choice');
        $choice->maxanswers[0] = 0;
    }

    // Remove from the list of non-respondents the users who do not have access to this activity.
    if (!empty($display->showunanswered) && $allresponses[0]) {
        $info = new \core_availability\info_module(cm_info::create($cm));
        $allresponses[0] = $info->filter_user_list($allresponses[0]);
    }

    // Overwrite options value.
    $display->options = [];
    $allusers = [];
    foreach ($choice->option as $optionid => $optiontext) {
        $display->options[$optionid] = new stdClass();
        $display->options[$optionid]->text = format_string(
            $optiontext,
            true,
            ['context' => context_module::instance($cm->id)]
        );
        $display->options[$optionid]->maxanswer = $choice->maxanswers[$optionid];

        if (array_key_exists($optionid, $allresponses)) {
            $display->options[$optionid]->user = $allresponses[$optionid];
            $allusers = array_merge($allusers, array_keys($allresponses[$optionid]));
        }
    }
    unset($display->option);
    unset($display->maxanswers);

    $display->numberofuser = count(array_unique($allusers));
    $context = context_module::instance($cm->id);
    $display->viewresponsecapability = has_capability('mod/choice:readresponses', $context);
    $display->deleterepsonsecapability = has_capability('mod/choice:deleteresponses', $context);
    $display->fullnamecapability = has_capability('moodle/site:viewfullnames', $context);

    if (empty($allresponses)) {
        echo $OUTPUT->heading(get_string("nousersyet"), 3, null);
        return false;
    }

    return $display;
}

/**
 * Deletes responses for a choice activity.
 *
 * @param array $attemptids
 * @param object $choice Choice main table row
 * @param object $cm Course-module object
 * @param object $course Course object
 * @return bool
 */
function choice_delete_responses($attemptids, $choice, $cm, $course) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir . '/completionlib.php');

    if (!is_array($attemptids) || empty($attemptids)) {
        return false;
    }

    foreach ($attemptids as $num => $attemptid) {
        if (empty($attemptid)) {
            unset($attemptids[$num]);
        }
    }

    $completion = new completion_info($course);
    foreach ($attemptids as $attemptid) {
        if ($todelete = $DB->get_record('choice_answers', ['choiceid' => $choice->id, 'id' => $attemptid])) {
            // Trigger the event answer deleted.
            \mod_choice\event\answer_deleted::create_from_object($todelete, $choice, $cm, $course)->trigger();
            $DB->delete_records('choice_answers', ['choiceid' => $choice->id, 'id' => $attemptid]);
        }
    }

    // Update completion state.
    if ($completion->is_enabled($cm) && $choice->completionsubmit) {
        $completion->update_state($cm, COMPLETION_INCOMPLETE);
    }

    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id
 * @return bool
 */
function choice_delete_instance($id) {
    global $DB;

    if (! $choice = $DB->get_record("choice", ["id" => "$id"])) {
        return false;
    }

    $result = true;

    if (! $DB->delete_records("choice_answers", ["choiceid" => "$choice->id"])) {
        $result = false;
    }

    if (! $DB->delete_records("choice_options", ["choiceid" => "$choice->id"])) {
        $result = false;
    }

    if (! $DB->delete_records("choice", ["id" => "$choice->id"])) {
        $result = false;
    }
    // Remove old calendar events.
    if (! $DB->delete_records('event', ['modulename' => 'choice', 'instance' => $choice->id])) {
        $result = false;
    }

    return $result;
}

/**
 * Returns text string which is the answer that matches the id.
 *
 * @param object $choice
 * @param int $id
 * @return string
 */
function choice_get_option_text($choice, $id) {
    global $DB;

    if ($result = $DB->get_record("choice_options", ["id" => $id])) {
        return $result->text;
    } else {
        return get_string("notanswered", "choice");
    }
}

/**
 * Gets a full choice record.
 *
 * @param int $choiceid
 * @return object|bool The choice or false
 */
function choice_get_choice($choiceid) {
    global $DB;

    if ($choice = $DB->get_record("choice", ["id" => $choiceid])) {
        if ($options = $DB->get_records("choice_options", ["choiceid" => $choiceid], "id")) {
            foreach ($options as $option) {
                $choice->option[$option->id] = $option->text;
                $choice->maxanswers[$option->id] = $option->maxanswers;
            }
            return $choice;
        }
    }
    return false;
}

/**
 * List the actions that correspond to a view of this module.
 *
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function choice_get_view_actions() {
    return ['view', 'view all', 'report'];
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function choice_get_post_actions() {
    return ['choose', 'choose again'];
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the choice.
 *
 * @param MoodleQuickForm $mform form passed by reference
 */
function choice_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'choiceheader', get_string('modulenameplural', 'choice'));
    $mform->addElement('static', 'choicedelete', get_string('delete'));
    $mform->addElement('advcheckbox', 'reset_choice', get_string('removeresponses', 'choice'));
}

/**
 * Course reset form defaults.
 *
 * @return array
 */
function choice_reset_course_form_defaults($course) {
    return ['reset_choice' => 1];
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * choice responses for course $data->courseid.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function choice_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'choice');
    $status = [];

    if (!empty($data->reset_choice)) {
        $choicessql = "SELECT ch.id
                       FROM {choice} ch
                       WHERE ch.course=?";

        $DB->delete_records_select('choice_answers', "choiceid IN ($choicessql)", [$data->courseid]);
        $status[] = [
            'component' => $componentstr,
            'item' => get_string('removeresponses', 'choice'),
            'error' => false,
        ];
    }

    // Updating dates - shift may be negative too.
    if ($data->timeshift) {
        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        shift_course_mod_dates('choice', ['timeopen', 'timeclose'], $data->timeshift, $data->courseid);
        $status[] = [
            'component' => $componentstr,
            'item' => get_string('date'),
            'error' => false,
        ];
    }

    return $status;
}

/**
 * Get response data for a choice and group.
 *
 * @uses CONTEXT_MODULE
 * @param object $choice
 * @param object $cm
 * @param int $groupmode
 * @param bool $onlyactive Whether to get response data for active users only.
 * @param int $groupid Group id, null for current group if choice has groups.
 * @return array
 */
function choice_get_response_data($choice, $cm, $groupmode, $onlyactive, ?int $groupid = null) {
    global $CFG, $USER, $DB;

    $context = context_module::instance($cm->id);

    if ($groupmode > 0) {
        if (is_null($groupid)) {
            // Get the current group.
            $groupid = groups_get_activity_group($cm);
        }
    } else {
        $groupid = 0;
    }

    // Initialise the returned array, which is a matrix:  $allresponses[responseid][userid] = responseobject.
    $allresponses = [];

    // First get all the users who have access here.
    // To start with we assume they are all "unanswered" then move them later.
    // TODO Does not support custom user profile fields (MDL-70456).
    $userfieldsapi = \core_user\fields::for_identity($context, false)->with_userpic();
    $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;
    $allresponses[0] = get_enrolled_users(
        $context,
        'mod/choice:choose',
        $groupid,
        $userfields,
        null,
        0,
        0,
        $onlyactive
    );

    // Get all the recorded responses for this choice.
    $rawresponses = $DB->get_records('choice_answers', ['choiceid' => $choice->id]);

    // Use the responses to move users into the correct column.
    if ($rawresponses) {
        $answeredusers = [];
        foreach ($rawresponses as $response) {
            if (isset($allresponses[0][$response->userid])) {
                // This person is enrolled and in correct group.
                $allresponses[0][$response->userid]->timemodified = $response->timemodified;
                $allresponses[$response->optionid][$response->userid] = clone($allresponses[0][$response->userid]);
                $allresponses[$response->optionid][$response->userid]->answerid = $response->id;
                $answeredusers[] = $response->userid;
            }
        }
        foreach ($answeredusers as $answereduser) {
            unset($allresponses[0][$answereduser]);
        }
    }
    return $allresponses;
}

/**
 * Returns whether the module supports a particular feature.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return null|true|string True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function choice_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COMMUNICATION;

        default:
            return null;
    }
}

/**
 * Adds module specific settings to the settings block.
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $choicenode The node to add module settings to
 */
function choice_extend_settings_navigation(settings_navigation $settings, navigation_node $choicenode) {
    if (has_capability('mod/choice:readresponses', $settings->get_page()->cm->context)) {
        $choicenode->add(
            get_string('responses', 'choice'),
            new moodle_url('/mod/choice/report.php', ['id' => $settings->get_page()->cm->id])
        );
    }
}

/**
 * Return a list of page types.
 *
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function choice_page_type_list($pagetype, $parentcontext, $currentcontext) {
    return ['mod-choice-*' => get_string('page-mod-choice-x', 'choice')];
}

/**
 * Get responses of a given user on a given choice.
 *
 * @param stdClass $choice Choice record
 * @param int $userid User id
 * @return array of choice answers records
 * @since  Moodle 3.6
 */
function choice_get_user_response($choice, $userid) {
    global $DB;
    return $DB->get_records('choice_answers', ['choiceid' => $choice->id, 'userid' => $userid], 'optionid');
}

/**
 * Get my responses on a given choice.
 *
 * @param stdClass $choice Choice record
 * @return array of choice answers records
 * @since  Moodle 3.0
 */
function choice_get_my_response($choice) {
    global $USER;
    return choice_get_user_response($choice, $USER->id);
}


/**
 * Get all the responses on a given choice.
 *
 * @param stdClass $choice Choice record
 * @return array of choice answers records
 * @since  Moodle 3.0
 */
function choice_get_all_responses($choice) {
    global $DB;
    return $DB->get_records('choice_answers', ['choiceid' => $choice->id]);
}


/**
 * Return true if we are allowd to view the choice results.
 *
 * @param stdClass $choice Choice record
 * @param rows|null $current my choice responses
 * @param bool|null $choiceopen if the choice is open
 * @return bool true if we can view the results, false otherwise.
 * @since  Moodle 3.0
 */
function choice_can_view_results($choice, $current = null, $choiceopen = null) {

    if (is_null($choiceopen)) {
        $timenow = time();

        if ($choice->timeopen != 0 && $timenow < $choice->timeopen) {
            // If the choice is not available, we can't see the results.
            return false;
        }

        if ($choice->timeclose != 0 && $timenow > $choice->timeclose) {
            $choiceopen = false;
        } else {
            $choiceopen = true;
        }
    }
    if (empty($current)) {
        $current = choice_get_my_response($choice);
    }

    if (
        $choice->showresults == CHOICE_SHOWRESULTS_ALWAYS ||
        ($choice->showresults == CHOICE_SHOWRESULTS_AFTER_ANSWER && !empty($current)) ||
        ($choice->showresults == CHOICE_SHOWRESULTS_AFTER_CLOSE && !$choiceopen)
    ) {
        return true;
    }
    return false;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $choice     choice object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function choice_view($choice, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = [
        'context' => $context,
        'objectid' => $choice->id,
    ];

    $event = \mod_choice\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('choice', $choice);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if a choice is available for the current user.
 *
 * @param  stdClass  $choice            choice record
 * @return array                       status (available or not and possible warnings)
 */
function choice_get_availability_status($choice) {
    $available = true;
    $warnings = [];

    $timenow = time();

    if (!empty($choice->timeopen) && ($choice->timeopen > $timenow)) {
        $available = false;
        $warnings['notopenyet'] = userdate($choice->timeopen);
    } else if (!empty($choice->timeclose) && ($timenow > $choice->timeclose)) {
        $available = false;
        $warnings['expired'] = userdate($choice->timeclose);
    }
    if (!$choice->allowupdate && choice_get_my_response($choice)) {
        $available = false;
        $warnings['choicesaved'] = '';
    }

    // Choice is available.
    return [$available, $warnings];
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every choice event in the site is checked, else
 * only choice events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @param int|stdClass $instance Choice module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function choice_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/choice/locallib.php');

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('choice', ['id' => $instance], '*', MUST_EXIST);
        }
        choice_set_events($instance);
        return true;
    }

    if ($courseid) {
        if (! $choices = $DB->get_records("choice", ["course" => $courseid])) {
            return true;
        }
    } else {
        if (! $choices = $DB->get_records("choice")) {
            return true;
        }
    }

    foreach ($choices as $choice) {
        choice_set_events($choice);
    }
    return true;
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function choice_check_updates_since(cm_info $cm, $from, $filter = []) {
    global $DB;

    $updates = new stdClass();
    $choice = $DB->get_record($cm->modname, ['id' => $cm->instance], '*', MUST_EXIST);
    [$available, $warnings] = choice_get_availability_status($choice);
    if (!$available) {
        return $updates;
    }

    $updates = course_check_module_updates_since($cm, $from, [], $filter);

    if (!choice_can_view_results($choice)) {
        return $updates;
    }
    // Check if there are new responses in the choice.
    $updates->answers = (object) ['updated' => false];
    $select = 'choiceid = :id AND timemodified > :since';
    $params = ['id' => $choice->id, 'since' => $from];
    $answers = $DB->get_records_select('choice_answers', $select, $params, '', 'id');
    if (!empty($answers)) {
        $updates->answers->updated = true;
        $updates->answers->itemids = array_keys($answers);
    }

    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_choice_core_calendar_provide_event_action(
    calendar_event $event,
    \core_calendar\action_factory $factory,
    int $userid = 0
) {
    global $USER;

    if (!$userid) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['choice'][$event->instance];

    if (!$cm->uservisible) {
        // The module is not visible to the user for any reason.
        return null;
    }

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    $now = time();

    if (!empty($cm->customdata['timeclose']) && $cm->customdata['timeclose'] < $now) {
        // The choice has closed so the user can no longer submit anything.
        return null;
    }

    // The choice is actionable if we don't have a start time or the start time is
    // in the past.
    $actionable = (empty($cm->customdata['timeopen']) || $cm->customdata['timeopen'] <= $now);

    if ($actionable && choice_get_user_response((object)['id' => $event->instance], $userid)) {
        // There is no action if the user has already submitted their choice.
        return null;
    }

    return $factory->create_instance(
        get_string('viewchoices', 'choice'),
        new \moodle_url('/mod/choice/view.php', ['id' => $cm->id]),
        1,
        $actionable
    );
}

/**
 * This function calculates the minimum and maximum cutoff values for the timestart of
 * the given event.
 *
 * It will return an array with two values, the first being the minimum cutoff value and
 * the second being the maximum cutoff value. Either or both values can be null, which
 * indicates there is no minimum or maximum, respectively.
 *
 * If a cutoff is required then the function must return an array containing the cutoff
 * timestamp and error string to display to the user if the cutoff value is violated.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The date must be after this date'],
 *     [1506741172, 'The date must be before this date']
 * ]
 *
 * @param calendar_event $event The calendar event to get the time range for
 * @param stdClass $choice The module instance to get the range from
 */
function mod_choice_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $choice) {
    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == CHOICE_EVENT_TYPE_OPEN) {
        if (!empty($choice->timeclose)) {
            $maxdate = [
                $choice->timeclose,
                get_string('openafterclose', 'choice'),
            ];
        }
    } else if ($event->eventtype == CHOICE_EVENT_TYPE_CLOSE) {
        if (!empty($choice->timeopen)) {
            $mindate = [
                $choice->timeopen,
                get_string('closebeforeopen', 'choice'),
            ];
        }
    }

    return [$mindate, $maxdate];
}

/**
 * This function will update the choice module according to the
 * event that has been modified.
 *
 * It will set the timeopen or timeclose value of the choice instance
 * according to the type of event provided.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event
 * @param stdClass $choice The module instance to get the range from
 */
function mod_choice_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $choice) {
    global $DB;

    if (!in_array($event->eventtype, [CHOICE_EVENT_TYPE_OPEN, CHOICE_EVENT_TYPE_CLOSE])) {
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;

    // Something weird going on. The event is for a different module so
    // we should ignore it.
    if ($modulename != 'choice') {
        return;
    }

    if ($choice->id != $instanceid) {
        return;
    }

    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($event->eventtype == CHOICE_EVENT_TYPE_OPEN) {
        // If the event is for the choice activity opening then we should
        // set the start time of the choice activity to be the new start
        // time of the event.
        if ($choice->timeopen != $event->timestart) {
            $choice->timeopen = $event->timestart;
            $modified = true;
        }
    } else if ($event->eventtype == CHOICE_EVENT_TYPE_CLOSE) {
        // If the event is for the choice activity closing then we should
        // set the end time of the choice activity to be the new start
        // time of the event.
        if ($choice->timeclose != $event->timestart) {
            $choice->timeclose = $event->timestart;
            $modified = true;
        }
    }

    if ($modified) {
        $choice->timemodified = time();
        // Persist the instance changes.
        $DB->update_record('choice', $choice);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}

/**
 * Add a get_coursemodule_info function in case any choice type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return false|cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function choice_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionsubmit, timeopen, timeclose';
    if (!$choice = $DB->get_record('choice', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $choice->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('choice', $choice, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionsubmit'] = $choice->completionsubmit;
    }
    // Populate some other values that can be used in calendar or on dashboard.
    if ($choice->timeopen) {
        $result->customdata['timeopen'] = $choice->timeopen;
    }
    if ($choice->timeclose) {
        $result->customdata['timeclose'] = $choice->timeclose;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_choice_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (
        empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC
    ) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionsubmit':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionsubmit', 'choice');
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * Callback to fetch the activity event type lang string.
 *
 * @param string $eventtype The event type.
 * @return lang_string The event type lang string.
 */
function mod_choice_core_calendar_get_event_action_string(string $eventtype): string {
    $modulename = get_string('modulename', 'choice');

    switch ($eventtype) {
        case CHOICE_EVENT_TYPE_OPEN:
            $identifier = 'calendarstart';
            break;
        case CHOICE_EVENT_TYPE_CLOSE:
            $identifier = 'calendarend';
            break;
        default:
            return get_string('requiresaction', 'calendar', $modulename);
    }

    return get_string($identifier, 'choice', $modulename);
}
