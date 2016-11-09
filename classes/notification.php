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
 * Notification
 *
 * @package   gradeexport_externalsystem
 * @copyright 2015 Antonio Carlos Mariani <antonio.c.mariani@ufsc.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class gradeexport_externalsystem_notification {

    private $message;
    private $type;

    private static $allowedtypes = array('message', 'problem', 'success');

    public function __construct($message, $type='message') {
        self::check_type($type);
        $this->message = $message;
        $this->type = $type;
    }

    public function is_problem() {
        return $this->type == 'problem';
    }

    public function get_message() {
        return $this->message;
    }

    public function get_type() {
        return $this->type;
    }

    /**
     * Render the notification message
     */
    public function render() {
        global $OUTPUT;

        return $OUTPUT->notification($this->message, 'notify'.$this->type);
    }

    public static function show($message, $type='success') {
        global $OUTPUT;

        self::check_type($type);
        echo $OUTPUT->notification($message, 'notify'.$type);
    }

    public static function success($code, $module='gradeexport_externalsystem', $a=null) {
        return new self(get_string($code, $module, $a), 'success');
    }

    public static function message($code, $module='gradeexport_externalsystem', $a=null) {
        return new self(get_string($code, $module, $a), 'message');
    }

    public static function problem($code, $module='gradeexport_externalsystem', $a=null) {
        return new self(get_string($code, $module, $a), 'problem');
    }

    public static function show_success($code, $module='gradeexport_externalsystem', $a=null) {
        self::show(get_string($code, $module, $a), 'success');
    }

    public static function show_message($code, $module='gradeexport_externalsystem', $a=null) {
        self::show(get_string($code, $module, $a), 'message');
    }

    public static function show_problem($code, $module='gradeexport_externalsystem', $a=null) {
        self::show(get_string($code, $module, $a), 'problem');
    }

    public static function check_type($type) {
        if (!in_array($type, self::$allowedtypes)) {
            throw new Exception("Invalid notification type: '{$type}'");
        }
    }


}
