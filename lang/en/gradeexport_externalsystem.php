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
 * Strings for component 'gradeexport_externalsystem', language 'en'
 *
 * @package   gradeexport_externalsystem
 * @copyright 2015 Antonio Carlos Mariani <antonio.c.mariani@ufsc.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'External system';
$string['externalsystem:publish'] = 'Export grades to an external system';
$string['externalsystem:view'] = 'View grades to be exported to an external system';

$string['drivers'] = 'Active drivers';
$string['drivers_help'] = 'Select de drivers that can be used to export grades to an external system.';

$string['unknowndriveraction'] = 'The driver action \'{$a}\' is unknown.';
$string['sentgrades_success'] = 'The grades were sent to the external system.';
$string['sentgrades_error'] = 'There were problems when sending grades to the external system. See occurrences in the remarks column.';
$string['notexternal'] = 'Not subscribed in the external system';
$string['notlocal'] = 'Not enrolled in the Moodle course';

$string['title_header'] = 'Export grades to an external system';
$string['remarks'] = 'Remarks';
$string['external'] = 'External';
$string['local'] = 'Moodle';
$string['send'] = 'Send';
$string['send_grades'] = 'Send selected grades';
$string['grade_sent'] = 'Grade was sent';
$string['invalid_type_value'] = 'The course grades configuration is incorrect: the final grade type must be a numeric value.<br>Please edit settings of the \'{$a}\' grade category and change the \'Grade type\' to \'Value\'.';
$string['invalid_grade_range'] = 'The course grades configuration is incorrect: the grades must be in the range [{$a->minimum}..{$a->maximum}].<br>Please edit settings of the \'{$a->link}\' grade category and change the \'Minimum grade\' to \'{$a->minimum}\' and \'Maximum grade\' to \'{$a->maximum}\'.';
$string['grades_differ'] = 'Local grade is not equal to external';
$string['send_zero'] = 'Will be sent grade 0 (zero)';

$string['not_implemented'] = 'Method \'{$a->method}\' is not implement in class \'{$a->class}\'';
$string['cannot_send_grades'] = 'You don\'t have permission to send grades from this course to an external system';
$string['mandatory_field'] = '\'{$a}\' is a required field and it\'s not defined at the driver';
$string['cannot_view_grades'] = 'You don\'t have permission to view the grades from this course to be sent to an external system';
$string['cannot_access_groups'] = 'You don\'t have permission to access the course groups.';
$string['cannot_export'] = 'The grades of this course are not exportable to an external system.';

$string['visit_grouped_courses'] = 'As it is a grouping of courses, sending grades to an external system should be made for each affiliated course by selecting it below. Visit the affiliated course if the grades have been assigned there.';
$string['grouped_course'] = 'This course is part of a grouping. Visit the grouping if the grades have been assigned there.';
$string['no_selected_students'] = 'No students were select to send grades';
