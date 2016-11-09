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
 * @package   gradeexport_externalsystem
 * @copyright 2015 Antonio Carlos Mariani <antonio.c.mariani@ufsc.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include('../../../config.php');
require($CFG->libdir .'/gradelib.php');
require($CFG->dirroot.'/grade/lib.php');
require($CFG->dirroot.'/grade/export/externalsystem/locallib.php');

$courseid = required_param('id', PARAM_INT);
$affiliatedcourseid = optional_param('affiliatedcourseid', 0, PARAM_INT);
if ($affiliatedcourseid == $courseid) {
    $affiliatedcourseid = 0;
}
$groupid = optional_param('groupid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
$baseurl = new moodle_url('/grade/export/externalsystem/index.php', array('id' => $courseid));

$orderby = optional_param('orderby', '', PARAM_ALPHA);
if (!empty($orderby)) {
    $baseurl->param('orderby', $orderby);
}

$affiliatedcourses = gradeexport_externalsystem_get_affiliated_courses($course);

if (empty($affiliatedcourseid)) {
    $drv = gradeexport_externalsystem_base::get_instance($course, $USER, $groupid, $orderby);
    $selectedcourse = $course;
} else {
    if (isset($affiliatedcourses[$affiliatedcourseid])) {
        $affiliatedcourse = $affiliatedcourses[$affiliatedcourseid];
        $drv = gradeexport_externalsystem_base::get_instance($affiliatedcourse, $USER, $groupid, $orderby, $course);
        $selectedcourse = $affiliatedcourse;
    } else {
        print_error('invalidcourseid');
    }
}

// Execute the driver action if selected
$action = optional_param('action', false, PARAM_ACTION);
if ($action && $drv && $drv->can_view_grades()) {
    $actions = $drv->actions();
    if (isset($actions['actions'][$action]) && method_exists($drv, $action)) {
        $drv->$action();
    } else {
        print_error('unknowndriveraction', 'gradeexport_externalsystem', $baseurl, $action);
    }
}

$PAGE->set_url($baseurl);
$PAGE->set_context($context);

if ($drv) {
    $heading = $drv->title_header();
} else {
    $heading = get_string('title_header', 'gradeexport_externalsystem');
}
print_grade_page_head($course->id, 'export', 'externalsystem', $heading);

if (!empty($affiliatedcourses)) {
    gradeexport_externalsystem_notification::show_message('visit_grouped_courses');

    $options = array();
    foreach ($affiliatedcourses AS $c) {
        $options[$c->id] = $c->fullname;
    }

    $select = new single_select($baseurl, 'affiliatedcourseid', $options, $affiliatedcourseid);
    $select->label = get_string('course') . ': ';
    echo '<div class="groupselector">'.$OUTPUT->render($select).'</div>';

    if (empty($affiliatedcourseid)) {
        echo $OUTPUT->footer();
        exit;
    }
}

// Course is not a parent course or no affiliated course was selected
if (!$drv && (empty($affiliatedcourses) || $selectedcourse->id == $affiliatedcourseid)) {
    gradeexport_externalsystem_notification::show_problem('cannot_export');
    echo $OUTPUT->footer();
    exit;
}

if (!$drv->can_view_grades()) {
    echo $OUTPUT->notification(get_string('cannot_view_grades', 'gradeexport_externalsystem'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

$parents = gradeexport_externalsystem_get_parent_courses($course);
if (!empty($parents)) {
    gradeexport_externalsystem_notification::show_message('grouped_course', 'gradeexport_externalsystem');
}

if (!empty($affiliatedcourseid)) {
    $baseurl->param('affiliatedcourseid', $affiliatedcourseid);
}

$group_selectbox = gradeexport_externalsystem_group_selectbox($baseurl, $selectedcourse, $USER->id, $groupid);
if ($group_selectbox === false) {
    gradeexport_externalsystem_notification::show_problem('cannot_access_groups');
    echo $OUTPUT->footer();
    exit;
} else if (!empty($group_selectbox)) {
    echo $group_selectbox;
}

$canedit = true;
foreach ($drv->can_send_grades() AS $notification) {
    echo $notification->render();
    $canedit = $canedit && !$notification->is_problem();
}

$messages = array();
if (optional_param('sendgrades', false, PARAM_TEXT) && confirm_sesskey()) {
    if (!$canedit) {
        print_error('nopermission');
    }
    $students = optional_param_array('send', array(), $drv->fieldmapping['userident']['type']);
    if (empty($students)) {
        gradeexport_externalsystem_notification::show_problem('no_selected_students');
    } else {
        $messages = $drv->send_data($students);
        $has_error = false;
        foreach ($messages AS $userid => $msgs) {
            foreach ($msgs AS $m) {
                $has_error = $has_error || $m->is_error();
            }
        }
        if ($has_error) {
            gradeexport_externalsystem_notification::show_problem('sentgrades_error');
        } else {
            gradeexport_externalsystem_notification::show_success('sentgrades_success');
        }
    }
}

$baseurl->param('groupid', $groupid);

$renderer = $PAGE->get_renderer('gradeexport_externalsystem');
$renderer->show($baseurl, $drv, $canedit, $messages);

echo $OUTPUT->footer();
