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

class gradeexport_externalsystem_sample extends gradeexport_externalsystem_base {

    public function __construct($course, $user, $groupid=0, $orderby='', $parent=false, $onlyactive=false) {
        parent::__construct($course, $user, $groupid, $orderby, $parent, $onlyactive);

        $this->userfield  = 'username';

        $this->fieldmapping = array('userident' => array('name' => 'Registration',
                                                         'type' => PARAM_ALPHANUM,
                                                         ),
                                    'fullname'  => array( 'name' => 'Name',
                                                          'type' => PARAM_TEXT,
                                                         ),
                                    'attendance'  => array('name'   => 'Attendance',
                                                          'type'    => PARAM_FLOAT,
                                                          'source'  => 'driver',
                                                          'align'   => 'right',
                                                          'default' => 0,
                                                         ),
                                    'grade'     => array('name'  => 'Current grade',
                                                         'type'  => PARAM_FLOAT,
                                                         'align' => 'right',
                                                         ),
                                   );

    }

    public function data_for_field_attendance() {
        $courseid = $this->parent ? $this->parent->id : $this->course->id;
        $modules = get_coursemodules_in_course('attendance', $courseid);
        if (empty($modules)) {
            return array();
        }
        $mod = reset($modules);

        $percentages = array();
        $summary = new mod_attendance_summary($mod->instance);
        foreach ($summary->get_user_taken_sessions_percentages() as $userid => $percentage) {
            $data = new stdClass();
            $data->value = $percentage * 100;
            $data->display_value = format_float($data->value) . '%';
            $percentages[$userid] = $data;
        }

        return $percentages;
    }

    public function knows_how_to_send_grades() {
        return true;
    }

    public function can_send_grades() {
        $notifications = parent::can_send_grades();

        if ($this->coursegradeitem->gradetype == GRADE_TYPE_VALUE) {
            if ($this->coursegradeitem->grademin != 0 || $this->coursegradeitem->grademax != 100) {
                $a = new stdClass();
                $a->minimum = 0;
                $a->maximum = 100;
                $a->link = $this->get_main_grade_category_link();
                $notifications[] = gradeexport_externalsystem_notification::problem('invalid_grade_range', 'gradeexport_externalsystem', $a);
            }
        } else {
            $notifications[] = gradeexport_externalsystem_notification::problem('invalid_type_value', 'gradeexport_externalsystem',
                                        $this->get_main_grade_category_link());
        }

        return $notifications;
    }

    public function format_external_grade($grade) {
        if (is_numeric($grade)) {
            return format_float($grade, 2, true);
        } else {
            return $grade;
        }
    }

    public function load_external_data() {
        global $DB, $CFG;

        $allnames = implode(',', get_all_user_name_fields());
        list($gradebookrolessql, $params) = $DB->get_in_or_equal(explode(',', $CFG->gradebookroles), SQL_PARAMS_NAMED, 'role');

        $context = context_course::instance($this->course->id);
        $params['contextid'] = $context->id;

        $userfield = $this->userfield;
        $sql = "SELECT DISTINCT u.{$userfield}, {$allnames}
                  FROM {role_assignments} ra
                  JOIN {user} u ON (u.id = ra.userid)
                 WHERE ra.contextid = :contextid
                   AND ra.roleid {$gradebookrolessql}
              ORDER BY {$this->orderby}, u.id";

        $this->externaldata = array();
        $skip = true;
        foreach ($DB->get_records_sql($sql, $params) AS $st) {
            if ($skip) {    // skip the first one for tests
                $skip = false;
            } else {
                $extstudent = new stdClass();
                $extstudent->userident = $st->$userfield;
                $extstudent->fullname = fullname($st);
                $extstudent->grade = substr(base_convert(md5($extstudent->fullname), 16, 10), 10, 4) / 100.0 % 50 * 1.9;
                $this->externaldata[$extstudent->userident] = $extstudent;
            }
        }
        $extstudent = new stdClass();
        $extstudent->userident = 'abc123';
        $extstudent->fullname = 'William Forrester';
        $extstudent->grade = 80;
        $this->externaldata[$extstudent->userident] = $extstudent;
    }

    public function check_grade(grade_grade $grade, stdclass $user_external_data) {
        $messages = array();

        if (isset($grade->finalgrade)) {
            $finalgrade = $grade->finalgrade;
        } else {
            $finalgrade = 0;
            $messages[] = gradeexport_externalsystem_message::info('send_zero');
        }

        if (grade_floats_different($finalgrade, $user_external_data->grade)) {
            $messages[] = gradeexport_externalsystem_message::info('grades_differ', '', null, true);
        }

        return $messages;
    }

    public function send_user_data($userid, $userident, grade_grade $grade, $extradata) {
        $messages = array();

        $formatted = $this->format_local_grade($grade, false);
        // send data
        $messages[] = gradeexport_externalsystem_message::success('grade_sent');

        return $messages;
    }

    public function actions() {
        return array('format'   => 'button',   // format of the actions (button or link)
                     'align'    => 'right',    // actions align (left or right)
                     'actions'  => array(
                                        'download_pdf' => 'Download PDF',
                                   ),
                    );
    }

    public function download_pdf() {
        global $CFG;

        if (defined('BEHAT_SITE_RUNNING')) {
            // For text based formats - we cannot test the output with behat if we force a file download.
            return;
        }

        include_once($CFG->libdir . '/pdflib.php');

        $doc = new pdf;
        $doc->setPrintHeader(false);
        $doc->setPrintFooter(false);
        $doc->AddPage();
        $doc->Write(5, 'Hello World!');
        $doc->Output('some_data.pdf', 'D');
        exit;
    }
}
