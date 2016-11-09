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

abstract class gradeexport_externalsystem_base {

    // User field name the identifies the user ('username' or 'idnumber')
    public $userfield;

    // Current Moodle course
    public $course;

    // The Moode course (or false) that has a enrol meta pointing to $course
    public $parent;

    // The Moodle user that is sending grades to the external system
    public $user;

    // Moodle user group id to send grades (zero means all users)
    public $groupid;

    // Only active users as selected
    public $onlyactive;

    // Field mapping that defines columns to be show at the report and data to be sent to external system
    // 'userident', 'fullname' and 'grade' are mandatory
    public $fieldmapping;

    // Course grade_item instance (course final grade)
    public $coursegradeitem;

    // Students enrolled in the course/group
    public $enrolledstudents;

    // Student grades
    private $grades;

    // The external system data to be shown at the report
    public $externaldata;

    // Specific driver data to be shown at the report
    public $driverdata;

    // default values for field mapping
    protected static $fieldmapping_default = array(
                        'editable' => false,
                        'align'    => 'left',
                        'default'  => '',
                        'source'   => 'system',
                        );

    /**
     * Constructor for the gradeexport_externalsystem_base class.
     *
     * @param stdclass $course Moodle course
     * @param stdclass $user Moodle user
     * @param int $groupid Moodle group id
     * @param string $orderby order that students must be listed
     * @param mixed stdclass|false $parent Moodle course that has a meta enrol pointing to $course
     * @param boolean $onlyactive Only active enrolled users are shown
     */
    public function __construct($course, $user, $groupid=0, $orderby='', $parent=false, $onlyactive=false) {
        $this->course = $course;
        $this->user = $user;
        $this->groupid = $groupid;
        $this->onlyactive = $onlyactive;
        $this->parent = $parent;
        $this->coursegradeitem = grade_item::fetch_course_item($this->parent ? $this->parent->id : $this->course->id);

        if ($orderby == 'fullname') {
            $this->orderby = 'firstname, lastname, username';
        } else if ($orderby == 'userident') {
            $this->orderby = 'username';
        } else {
            $this->orderby = $this->default_student_order_by();
        }
    }

    /**
     * Return a driver that knows how to send grades to external system
     *
     * @param stdclass $course Moodle course
     * @param stdclass $user Moodle user
     * @param int $groupid Moodle group id
     * @param mixed stdclass|false $parent Moodle course that has a meta enrol pointing to $course
     * @param string $orderby order that students must be listed
     * @return mixed object driver or false
     */
    public static function get_instance($course, $user, $groupid=0, $orderby='', $parent=false) {
        global $CFG;

        $drivers = explode(',', get_config('gradeexport_externalsystem', 'drivers'));
        if (empty($drivers)) {
            return false;
        }

        foreach ($drivers AS $driver) {
            if (class_exists($driver)) {
                $drv = new $driver($course, $user, $groupid, $orderby, $parent);
                if ($drv->knows_how_to_send_grades()) {
                    return $drv;
                }
            }
        }

        return false;
    }

    /**
     * Return true if the current user can view the report.
     * The default behavior is just check the 'gradeexport/externalsystem:view' capability
     *
     * @return boolean
     */
    public function can_view_grades() {
        $context = context_course::instance($this->course->id);
        return has_capability('gradeexport/externalsystem:view', $context, $this->user);
    }

    /**
     * Return true if the current user can send grades to the external system or a message/type
     *   where type = 'notifyproblem' | 'notifymessage' | 'notifysuccess'
     * The default behavior is just check the 'gradeexport/externalsystem:publish' capability.
     *
     * @return array of gradeexport_externalsystem_notification
     */
    public function can_send_grades() {
        $context = context_course::instance($this->course->id);
        if (has_capability('moodle/grade:export', $context, $this->user) &&
                    has_capability('gradeexport/externalsystem:publish', $context, $this->user)) {
            return array();
        } else {
            return array(gradeexport_externalsystem_notification::problem('cannot_send_grades'));
        }
    }

    /**
     * Return true if the driver "knows" the current Moodle course so it knows how to send grades to the external system
     *
     * @return boolean
     */
    public function knows_how_to_send_grades() {
        return false;
    }

    /**
     * Return the title header string
     *
     * @return string
     */
    public function title_header() {
        return get_string('title_header', 'gradeexport_externalsystem');
    }

    /**
     * Return the user fullname to be shown in the report
     *
     * @param stdclass $user Moodle user
     * @return string
     */
    public function fullname($user) {
        return fullname($user);
    }

    /**
     * Return the user grade_grade
     *
     * @param int $userid Moodle user id
     * @return grade_grade class
     */
    public function grade($userid) {
        if (isset($this->grade[$userid])) {
            $grade = $this->grade[$userid];
        } else {
            $grade = new grade_grade(array('itemid'=>$this->coursegradeitem->id, 'userid'=>$userid));
        }
        $grade->grade_item =& $this->coursegradeitem; // this may speedup grade_grade methods!
        return $grade;
    }

    /**
     * Return a string (user fields separated by comma) defining the student order by in the report
     *
     * @return string
     */

    public function default_student_order_by($usertablealias = 'u') {
        list($order, $params) = users_order_by_sql($usertablealias);
        return $order;
    }

    /**
     * Return the column name representing external data
     *
     * @return string
     */
    public function external_column_name() {
        return get_string('external', 'gradeexport_externalsystem');
    }

    /**
     * Return an array of actions ('driver_method' => 'action (button or link) title')
     *    to be render at the top right part of the report
     *
     * @return array
     */
    public function actions() {
        /*
        return array('format'   => 'button',   // format of the actions (button or link)
                     'align'    => 'right',    // actions align (left or right)
                     'actions'  => array(
                                        'driver_method' => 'action (button or link) title',
                                   ),
                    );
        */

        return array();
    }

    /**
     * Format local grade to be shown at the report or exported to external system
     *
     * @param grade_grade class $grade
     * @param boolean $localized
     * @return string
     */
    public function format_local_grade(grade_grade $grade, $localized=true) {
        return grade_format_gradevalue($grade->finalgrade, $this->coursegradeitem, $localized);
    }

    /**
     * Format external grade to be shown at the report
     *
     * @param mixed (int|float|string) $grade
     * @return string
     */
    abstract public function format_external_grade($grade);

    /**
     * Get external data for the current course and store is at $this->externaldata
     *
     * @return array
     */
    abstract protected function load_external_data();

    /**
     * Return an array of messages to be shown at the remark column of the report
     *
     * @param grade_grade $grade_grade
     * @param stdclass $user_external_data external data related to the $grade
     * @return array of gradeexport_externalsystem_message
     */
    abstract public function check_grade(grade_grade $grade, stdclass $user_external_data);

    /**
     * Send user specific data to the external system
     *
     * @param int $userid Student Moodle id
     * @param mixed $userident Student external identification
     * @param grade_grade $grade Student grade
     * @param array $extradata Student extra data
     * @return gradeexport_externalsystem_message Either a sucess or error message
     */
    abstract protected function send_user_data($userid, $userident, grade_grade $grade, $extradata);

    /**
     * Send the form data to the external system
     *
     * @param array $students students that grades must be sent
     * @return array of messages
     */
    public function send_data($students) {
        $this->merge_fieldmapping();
        $this->load_local_grades(array_keys($students));

        $data = array();
        foreach ($students AS $userid => $userident) {
            $data[$userid] = array();
        }

        foreach ($this->fieldmapping AS $field => $fielddesc) {
            $default = isset($fielddesc['default']) ? $fielddesc['default'] : null;
            if ($fielddesc['editable']) {
                $values = optional_param_array($field, array(), $fielddesc['type']);
                foreach ($students AS $userid => $st) {
                    $data[$userid][$field] = isset($values[$userid]) ? $values[$userid] : $default;
                }
            }
        }

        $messages = array();
        foreach ($students AS $userid => $userident) {
            $grade = $this->grade($userid);
            $messages[$userid] = $this->send_user_data($userid, $userident, $grade, $data[$userid]);
        }
        return $messages;
    }

    /**
     * Populate all local variables with local, driver an external data
     *
     * @return array of messages
     */
    public function populate() {
        $this->merge_fieldmapping();
        $this->load_enrolled_students();
        $this->load_local_grades();
        $this->load_external_data();

        $this->driverdata = array();
        foreach ($this->fieldmapping AS $field => $fielddesc) {
            if ($fielddesc['source'] == 'driver') {
                $method = 'data_for_field_' . $field;
                if (method_exists($this, $method)) {
                    $this->driverdata[$field] = $this->$method();
                } else {
                    $a = new stdClass();
                    $a->method = $method;
                    $a->class = get_class($this);
                    print_error('not_implemented', 'gradeexport_externalsystem', '', $a);
                }
            }
        }
    }

    /**
     * Return a css class (gradepass or gradefail) for the grade_item
     * @param grade_grade $grade
     * @return string
     */
    public function class_for_gradepass(grade_grade $grade) {
        $class = 'gradefail';
        $ispassed = $grade->is_passed();
        if ($ispassed) {
            $class = 'gradepass';
        } else if (is_null($ispassed)) {
            $class = '';
        }
        return $class;
    }

    /**
     * Get specific driver data to be shown or sent to external system
     * @param string $field Name or the driver data field
     * @param int $userid Student Moodle id
     * @param boolean $display_value true means the formatted display value
     * @return string
     */
    public function get_driverdata($field, $userid, $display_values=true) {
        if (isset($this->driverdata[$field][$userid])) {
            return $display_values ? $this->driverdata[$field][$userid]->display_value : $this->driverdata[$field][$userid]->value;
        } else {
            return $display_values ? '-' : $this->fieldmapping[$field]['default'];
        }
    }

    /**
     * Load the students grades (grade_grade instances)
     *
     * @return null
     * @param mixed array|false $userids Student Moodle id
     */
    protected function load_local_grades($userids=false) {
        if ($userids === false) {
            // Userids was not given
            if (empty($this->groupid)) {
                // No group were selected; fetch all user grades
                $this->grades = grade_grade::fetch_all(array('itemid' => $this->coursegradeitem->id));
                return;
            } else {
                // Take userids from $this->enrolledstudents
                $userids = array_keys($this->enrolledstudents);
            }
        }

        if (empty($userids)) {
            $this->grades = array();
        } else {
            $this->grades = grade_grade::fetch_users_grades($this->coursegradeitem, $userids, false);
        }
    }

    /**
     * Get enrolled students in the course/group and store them at $this->enrolledstudents
     *
     * @return null
     */
    protected function load_enrolled_students() {
        global $DB, $CFG;

        $context = context_course::instance($this->course->id);

        list($gradebookroles_sql, $params) = $DB->get_in_or_equal(explode(',', $CFG->gradebookroles), SQL_PARAMS_NAMED, 'grbr');
        list($enrolledsql, $enrolledparams) = get_enrolled_sql($context, '', $this->groupid, $this->onlyactive);
        $params = array_merge($params, $enrolledparams);

        $params['contextid'] = $context->id;

        if ($this->groupid) {
            $groupsql = "INNER JOIN {groups_members} gm ON (gm.userid = u.id)";
            $groupwheresql = "AND gm.groupid = :groupid";
            $params['groupid'] = $this->groupid;
        } else {
            $groupsql = "";
            $groupwheresql = "";
        }

        $allnames = implode(',', get_all_user_name_fields());
        $sql = "SELECT u.id, u.{$this->userfield}, $allnames
                  FROM {user} u
                  JOIN ($enrolledsql) je ON (je.id = u.id)
                  $groupsql
                  JOIN (    SELECT DISTINCT ra.userid
                              FROM {role_assignments} ra
                             WHERE ra.roleid $gradebookroles_sql
                               AND ra.contextid = :contextid
                       ) rainner ON (rainner.userid = u.id)
                 WHERE u.deleted = 0
                   $groupwheresql
              ORDER BY {$this->orderby}";
        $this->enrolledstudents = $DB->get_records_sql($sql, $params);
    }

    /**
     * Merge the fieldmapping with the fieldmapping_default
     *
     * @return null
     */
    protected function merge_fieldmapping() {
        $mandatory = array('userident', 'fullname', 'grade');
        foreach ($mandatory AS $field) {
            if (!isset($this->fieldmapping[$field])) {
                print_error('mandatory_field', 'gradeexport_externalsystem', $field);
            }
        }

        foreach ($this->fieldmapping AS $field => $fielddesc) {
            $this->fieldmapping[$field] = array_merge(self::$fieldmapping_default, $fielddesc);
        }
    }

    /**
     * Return a link to the main grade category of the current course
     *
     * @return String
     */
    protected function get_main_grade_category_link() {
        $category = grade_category::fetch(array('courseid' => $this->course->id, 'depth' => 1));
        $params = array('courseid'=>$this->course->id, 'id'=>$category->id, 'gpr_type'=>'edit', 'gpr_plugin'=>'tree', 'gpr_courseid'=>$this->course->id);
        $url = new moodle_url('/grade/edit/tree/category.php', $params);
        return html_writer::link($url, $this->course->fullname);
    }
}
