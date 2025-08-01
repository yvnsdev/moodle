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
 * Strings for component 'core_course', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   core_course
 * @copyright 2018 Adrian Greeve <adriangreeve.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activitychoosercategory'] = 'Activity chooser';
$string['activitychooserrecommendations'] = 'Recommended activities';
$string['activitychoosersettings'] = 'Activity chooser settings';
$string['activitychooseractivefooter'] = 'Activity chooser footer';
$string['activitychooseractivefooter_desc'] = 'The activity chooser can support plugins that add items to the footer.';
$string['activitychooserhidefooter'] = 'No footer';
$string['activitydate:closed'] = 'Closed:';
$string['activitydate:closes'] = 'Closes:';
$string['activitydate:opened'] = 'Opened:';
$string['activitydate:opens'] = 'Opens:';
$string['activitydetails'] = 'Activity details';
$string['addselectedactivity'] = 'Add selected activity';
$string['aria:coursecategory'] = 'Course category';
$string['aria:courseshortname'] = 'Course short name';
$string['aria:coursename'] = 'Course name';
$string['aria:defaulttab'] = 'Default activities';
$string['aria:favourite'] = 'Course is starred';
$string['aria:favouritestab'] = 'Starred activities';
$string['aria:modulefavourite'] = 'Add {$a} to starred';
$string['aria:moduleunfavourite'] = 'Remove {$a} from starred';
$string['aria:recommendedtab'] = 'Recommended activities';
$string['browsecourseadminindex'] = 'Browse the course administration with this index.';
$string['browsesettingindex'] = 'Browse settings with this index.';
$string['communicationroomlink'] = 'Chat to course participants';
$string['completion_automatic:done'] = 'Done:';
$string['completion_automatic:failed'] = 'Failed:';
$string['completion_automatic:todo'] = 'To do:';
$string['completion_manual:aria:done'] = '{$a} is marked as done. Press to undo.';
$string['completion_manual:aria:markdone'] = 'Mark {$a} as done';
$string['completion_manual:done'] = 'Done';
$string['completion_manual:markdone'] = 'Mark as done';
$string['completion_setby:auto:done'] = 'Done: {$a->condition} (set by {$a->setby})';
$string['completion_setby:auto:todo'] = 'To do: {$a->condition} (set by {$a->setby})';
$string['completion_setby:manual:done'] = '{$a->activityname} is marked by {$a->setby} as done. Press to undo.';
$string['completion_setby:manual:markdone'] = '{$a->activityname} is marked by {$a->setby} as not done. Press to mark as done.';
$string['completionrequirements'] = 'Completion requirements for {$a}';
$string['courseaccess'] = 'Course access';
$string['coursealreadyfinished'] = 'Course already finished';
$string['coursecommunication_desc'] = 'The default communication service for new courses. Existing courses will not have any provider selected by default.';
$string['coursecontentnotification'] = 'Send content change notification';
$string['coursecontentnotifnew'] = '{$a->coursename} new content';
$string['coursecontentnotifnewbody'] = '<p>{$a->moduletypename} <a href="{$a->link}">{$a->modulename}</a> is new in the course <a href="{$a->courselink}">{$a->coursename}</a>.</p><p><a href="{$a->notificationpreferenceslink}">Change your notification preferences</a></p>';
$string['coursecontentnotifupdate'] = '{$a->coursename} content change';
$string['coursecontentnotifupdatebody'] = '<p>{$a->moduletypename} <a href="{$a->link}">{$a->modulename}</a> has been changed in the course <a href="{$a->courselink}">{$a->coursename}</a>.</p><p><a href="{$a->notificationpreferenceslink}">Change your notification preferences</a></p>';
$string['coursecontentnotification_help'] = 'Tick the box to notify course participants about this new or changed activity or resource. Only users who can access the activity or resource will receive the notification.';
$string['coursecount'] = 'Course count';
$string['coursenotyetstarted'] = 'The course has not yet started';
$string['coursenotyetfinished'] = 'The course has not yet finished';
$string['courseparticipants'] = 'Course participants';
$string['coursetoolong'] = 'The course is too long';
$string['customfield_islocked'] = 'Locked';
$string['customfield_islocked_help'] = 'If the field is locked, only users with the capability to change locked custom fields (by default users with the default role of manager only) will be able to change it in the course settings.';
$string['customfield_notvisible'] = 'Nobody';
$string['customfield_visibility'] = 'Visible to';
$string['customfield_visibility_help'] = 'This setting determines who can view the custom field name and value in the list of courses or in the available custom field filter of the Dashboard.';
$string['customfield_visibletoall'] = 'Everyone';
$string['customfield_visibletoteachers'] = 'Teachers';
$string['customfieldsettings'] = 'Common course custom fields settings';
$string['defaultsettingscategory'] = 'Default settings';
$string['downloadcourseconfirmation'] = 'You are about to download a zip file of course content (excluding items which cannot be downloaded and any files larger than {$a}).';
$string['downloadcoursecontent'] = 'Download course content';
$string['downloadcoursecontent_help'] = 'This setting determines whether course content may be downloaded by users with the download course content capability (by default users with the role of student or teacher).';
$string['downloadcontent'] = 'Include in course content download';
$string['downloadcontent_help'] = 'Should this activity or resource be included in the zip file of course content available for download? File, Folder, Page and Label can be fully downloaded. For all other activities and resources, only the name and description are downloaded. This option requires download course content to be enabled in the course settings.

The setting has no effect on mobile app content download for offline usage.';
$string['enabledownloadcoursecontent'] = 'Enable download course content';
$string['errorendbeforestart'] = 'The end date ({$a}) is before the course start date.';
$string['favourite'] = 'Starred course';
$string['filterbothactive'] = 'First ({$a->first}) Last ({$a->last})';
$string['filterbyname'] = 'Filter by name';
$string['filterfirstactive'] = 'First ({$a->first})';
$string['filterlastactive'] = 'Last ({$a->last})';
$string['gradetopassnotset'] = 'This course does not have a grade to pass set. It may be set in the grade item of the course (Gradebook setup).';
$string['hideendedcoursestask'] = 'Hide courses on end date';
$string['informationformodule'] = 'Information about the {$a} activity';
$string['mod_purpose_administration'] = 'Administration';
$string['mod_purpose_administration_help'] = 'Tools to help the teacher in managing the course progress.';
$string['mod_purpose_assessment'] = 'Assessment';
$string['mod_purpose_assessment_help'] = 'Activities that allow evaluation and measurement of student understanding and performance, including individual and collaborative assessment, and peer assessment.';
$string['mod_purpose_collaboration'] = 'Collaboration';
$string['mod_purpose_collaboration_help'] = 'Tools for collaborative learning that encourages knowledge sharing, discussion, and teamwork among learners.';
$string['mod_purpose_communication'] = 'Communication';
$string['mod_purpose_communication_help'] = 'Activities that facilitate real-time communication, asynchronous interactions, and feedback collection.';
$string['mod_purpose_content'] = 'Resources';
$string['mod_purpose_content_help'] = 'Activities and tools to organise and display course materials like documents, web links, and multimedia.';
$string['mod_purpose_interactivecontent'] = 'Interactive content';
$string['mod_purpose_interactivecontent_help'] = 'Engaging interactive activities that encourage active learner participation.';
$string['mod_purposes'] = 'Categories';
$string['module'] = 'Activity';
$string['namewithlink'] = 'Category name with link';
$string['nocourseactivity'] = 'Not enough course activity between the start and the end of the course';
$string['nocourseendtime'] = 'The course does not have an end time';
$string['nocoursesections'] = 'No course sections';
$string['nocoursestudents'] = 'No students';
$string['noaccesssincestartinfomessage'] = 'Hi {$a->userfirstname},
<p>A number of students in {$a->coursename} have never accessed the course.</p>';
$string['norecentaccessesinfomessage'] = 'Hi {$a->userfirstname},
<p>A number of students in {$a->coursename} have not accessed the course recently.</p>';
$string['noteachinginfomessage'] = 'Hi {$a->userfirstname},
<p>Courses with start dates in the next week have been identified as having no teacher or student enrolments.</p>';
$string['overview_info'] = 'An overview of all activities in the course, with dates and other information.';
$string['overview_missing_notice'] = 'Go to the {$a}.';
$string['overview_missing_title'] = '{$a} information not available.';
$string['overview_modname'] = '{$a} overview';
$string['overview_nogroups_error'] = 'You are not a member of any group';
$string['overview_nogroups_title'] = 'Information not available because you are not a member of any group';
$string['overview_page_title'] = 'Course activities: {$a}';
$string['overview_table_caption'] = 'Table listing all {$a} activities';
$string['participants:perpage'] = 'Number of participants per page';
$string['participants:perpage_help'] = 'The number of users shown per page on the participants page in each course.';
$string['participantsnavigation'] = 'Participants tertiary navigation.';
$string['pdfexportfont'] = 'PDF font';
$string['pdfexportfont_help'] = 'The font to be used for generated PDF files, such as assignment submissions.';
$string['privacy:perpage'] = 'The number of courses to show per page.';
$string['privacy:completionpath'] = 'Course completion';
$string['privacy:favouritespath'] = 'Course starred information';
$string['privacy:metadata:activityfavouritessummary'] = 'The course system contains information about which items from the activity chooser have been starred by the user.';
$string['privacy:metadata:completionsummary'] = 'The course contains completion information about the user.';
$string['privacy:metadata:favouritessummary'] = 'The course contains information relating to the course being starred by the user.';
$string['recommend'] = 'Recommend';
$string['recommendcheckbox'] = 'Recommend activity: {$a}';
$string['recommended_help'] = 'Activities recommended by your organisation.';
$string['relativedatessubmissionduedateafter'] = '{$a->datediffstr} after course start';
$string['relativedatessubmissionduedatebefore'] = '{$a->datediffstr} before course start';
$string['searchactivitiesbyname'] = 'Search for activities by name';
$string['searchresults'] = 'Search results: {$a}';
$string['sectionlink'] = 'Permalink';
$string['showstartedcoursestask'] = 'Show courses on start date';
$string['submitsearch'] = 'Submit search';
$string['studentsatriskincourse'] = 'Students at risk in {$a} course';
$string['studentsatriskinfomessage'] = 'Hi {$a->userfirstname},
<p>Students in the {$a->coursename} course have been identified as being at risk.</p>';
$string['target:coursecompletion'] = 'Students at risk of not meeting the course completion conditions';
$string['target:coursecompletion_help'] = 'This target describes whether the student is considered at risk of not meeting the course completion conditions.';
$string['target:coursecompetencies'] = 'Students at risk of not achieving the competencies assigned to a course';
$string['target:coursecompetencies_help'] = 'This target describes whether a student is at risk of not achieving the competencies assigned to a course. This target considers that all competencies assigned to the course must be achieved by the end of the course.';
$string['target:coursedropout'] = 'Students at risk of dropping out';
$string['target:coursedropout_help'] = 'This target describes whether the student is considered at risk of dropping out.';
$string['target:coursegradetopass'] = 'Students at risk of not achieving the minimum grade to pass the course';
$string['target:coursegradetopass_help'] = 'This target describes whether the student is at risk of not achieving the minimum grade to pass the course.';
$string['target:noaccesssincecoursestart'] = 'Students who have not accessed the course yet';
$string['target:noaccesssincecoursestart_help'] = 'This target describes students who never accessed a course they are enrolled in.';
$string['target:noaccesssincecoursestartinfo'] = 'The following students are enrolled in a course which has started, but they have never accessed the course.';
$string['target:norecentaccesses'] = 'Students who have not accessed the course recently';
$string['target:norecentaccesses_help'] = 'This target identifies students who have not accessed a course they are enrolled in within the set analysis interval (by default the past month).';
$string['target:norecentaccessesinfo'] = 'The following students have not accessed a course they are enrolled in within the set analysis interval (by default the past month).';
$string['target:noteachingactivity'] = 'Courses at risk of not starting';
$string['target:noteachingactivity_help'] = 'This target describes whether courses due to start in the coming week will have teaching activity.';
$string['target:noteachingactivityinfo'] = 'The following courses due to start in the upcoming days are at risk of not starting because they don\'t have teachers or students enrolled.';
$string['targetlabelstudentcompletionno'] = 'Student who is likely to meet the course completion conditions';
$string['targetlabelstudentcompletionyes'] = 'Student at risk of not meeting the course completion conditions';
$string['targetlabelstudentcompetenciesno'] = 'Student who is likely to achieve the competencies assigned to a course';
$string['targetlabelstudentcompetenciesyes'] = 'Student at risk of not achieving the competencies assigned to a course';
$string['targetlabelstudentdropoutyes'] = 'Student at risk of dropping out';
$string['targetlabelstudentdropoutno'] = 'Not at risk';
$string['targetlabelstudentgradetopassno'] = 'Student who is likely to meet the minimum grade to pass the course.';
$string['targetlabelstudentgradetopassyes'] = 'Student at risk of not meeting the minimum grade to pass the course.';
$string['targetlabelteachingyes'] = 'Users with teaching capabilities who have access to the course';
$string['targetlabelteachingno'] = 'Courses at risk of not starting';
$string['totalactivities'] = 'Activities: {$a}';
$string['gotosection'] = 'Go to section {$a}';

// Deprecated since Moodle 4.5.
$string['daystakingcourse'] = 'Days taking course';
