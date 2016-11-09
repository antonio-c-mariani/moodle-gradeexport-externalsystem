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

defined('MOODLE_INTERNAL') || die;

/**
 * Return an menu array of driver class names
 *
 * @return array of driver classes names
 */
function gradeexport_externalsystem_driver_classes() {
    // Get plugins that require 'gradeexport_externalsystem' plugin
    $plugin_manager = core_plugin_manager::instance();
    $dependents = $plugin_manager->other_plugins_that_require('gradeexport_externalsystem');

    // Add this plugin
    $dependents[] = 'gradeexport_externalsystem';

    // Look for classes that have 'gradeexport_externalsystem_base' class as a parent
    foreach ($dependents AS $dep) {
        $pluginfo = $plugin_manager->get_plugin_info($dep);
        $classes_dir = $pluginfo->rootdir . '/classes';
        foreach (get_directory_list($classes_dir, '', false) AS $file) {
            $class = $pluginfo->type . '_' . $pluginfo->name . '_' . basename($file, '.php');
            if (class_exists($class) && is_subclass_of($class, 'gradeexport_externalsystem_base')) {
                $reflection = new ReflectionClass($class);
                if (!$reflection->isAbstract()) {
                    $drivers[$class] = $class;
                }
            }
        }
    }

    return $drivers;
}

/**
 * Get courses that are pointed by a meta link enrolment in $course
 *
 * @param stdclass $course Moodle course
 * @return array
 */
function gradeexport_externalsystem_get_affiliated_courses($course) {
    global $DB, $CFG;

    $courses = array();

    if (!in_array('meta', explode(',', $CFG->enrol_plugins_enabled))) {
        return $courses;
    }

    $enrols = $DB->get_records('enrol', array('enrol' => 'meta', 'status'=>ENROL_INSTANCE_ENABLED,
                                              'courseid' => $course->id), 'sortorder,id');
    foreach($enrols AS $enrol) {
        if ($c = get_course($enrol->customint1)) {
            $courses[$c->id] = $c;
        }
    }

    return $courses;
}

/**
 * Get courses that have a meta link enrolment pointing to the $course
 *
 * @param stdclass $course Moodle course
 * @return array
 */
function gradeexport_externalsystem_get_parent_courses($course) {
    global $DB, $CFG;

    $courses = array();

    if (!in_array('meta', explode(',', $CFG->enrol_plugins_enabled))) {
        return $courses;
    }

    $enrols = $DB->get_records('enrol', array('enrol' => 'meta', 'status' => ENROL_INSTANCE_ENABLED,
                                              'customint1' => $course->id), '', 'id, courseid');
    foreach($enrols AS $enrol) {
        if ($c = get_course($enrol->courseid)) {
            $courses[$c->id] = $c;
        }
    }

    return $courses;
}

/**
 * Return html text for a groups select box
 *
 * @param moodle_url $url
 * @param stClass $course
 * @param int $userid
 * @param int $groupid
 * @return string
 */
function gradeexport_externalsystem_group_selectbox($url, $course, $userid, $groupid=0) {
    global $OUTPUT;

    $groupsmenu = array();

    $context = context_course::instance($course->id);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);

    if ($accessallgroups) {
        $allowedgroups = groups_get_all_groups($course->id);
        if (empty($allowedgroups)) {
            return '';
        }
        $uid = 0;
        $groupsmenu[0] = get_string('allparticipants');
    } else {
        $allowedgroups = groups_get_all_groups($course->id, $userid);
        if (empty($allowedgroups)) {
            return $this->course->groupmode ? false : '';
        } else if ($groupid == 0) {
            $g = reset($allowedgroups);
            $groupid = $g->id;
        }
        $uid = $userid;
    }


    $groupingsmenu = array();
    foreach (groups_get_all_groupings($course->id) AS $grouping) {
        $menu = array();
        foreach (groups_get_all_groups($course->id, $uid, $grouping->id) AS $g) {
            $menu[$g->id] = $g->name;
            if (isset($allowedgroups[$g->id])) {
                unset($allowedgroups[$g->id]);
            }
        }
        if (!empty($menu)) {
            $groupingsmenu[$grouping->name] = array($grouping->name => $menu);
        }
    }

    foreach ($allowedgroups AS $g) {
        $groupsmenu[$g->id] = $g->name;
    }

    $groupsmenu = $groupsmenu + $groupingsmenu;

    $select = new single_select($url, 'groupid', $groupsmenu, $groupid, null, 'selectgroup');
    $select->label = get_string('groups') . ': ';
    $output = $OUTPUT->render($select);
    return html_writer::tag('div', $output, array('class' => 'groupselector'));
}
