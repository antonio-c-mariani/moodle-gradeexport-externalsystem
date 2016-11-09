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
 * Message class
 *
 * @package   gradeexport_externalsystem
 * @copyright 2015 Antonio Carlos Mariani <antonio.c.mariani@ufsc.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class gradeexport_externalsystem_message {

    /** @var mixed string|array text of the message or messages */
    private $message;

    /** @var string message type: info, error or success */
    private $type;

    /** @var boolean false means the related grade can be sent to external system despite of the message.
      *              true means there is an error that prevents sending related grade
      *      the default value is false
    */
    public $prevents_sending_grade;

    /** @var boolean true means the checkbox for sending the related grade must be checked.
      *              false means is not necessary to send related grade so the checkbox is unchecked.
    */
    public $checked_to_send_grade;

    /** @var array allowed message types */
    private static $allowedtypes = array('info', 'error', 'success');

    public function __construct($message, $type='info', $checked_to_send_grade=false, $prevents_sending_grade=false) {
        $this->message = $message;
        if (!in_array($type, self::$allowedtypes)) {
            throw new Exception('Invalid type for message: ' . $type);
        }
        $this->type = $type;
        $this->checked_to_send_grade = $checked_to_send_grade;
        $this->prevents_sending_grade = $prevents_sending_grade;
    }

    public function is_error() {
        return $this->type == 'error';
    }

    /**
     * Show (return) the messages
     */
    public function show() {
        $text = '';
        if (is_string($this->message)) {
            $text .= html_writer::tag('div', $this->message, array('class' => 'alert-' . $this->type));
        } else {
            foreach ($this->message AS $m) {
                $text .= html_writer::tag('div', $m, array('class' => 'alert-' . $this->type));
            }
        }

        return $text;
    }

    public static function info($code, $module='', $a=null, $checked_to_send_grade=false, $prevents_sending_grade=false) {
        if (empty($module)) {
            $module = 'gradeexport_externalsystem';
        }
        return new self(get_string($code, $module, $a), 'info', $checked_to_send_grade, $prevents_sending_grade);
    }

    public static function error($code, $module='', $a=null, $checked_to_send_grade=false, $prevents_sending_grade=true) {
        if (empty($module)) {
            $module = 'gradeexport_externalsystem';
        }
        return new self(get_string($code, $module, $a), 'error', $checked_to_send_grade, $prevents_sending_grade);
    }

    public static function success($code, $module='', $a=null, $checked_to_send_grade=false, $prevents_sending_grade=false) {
        if (empty($module)) {
            $module = 'gradeexport_externalsystem';
        }
        return new self(get_string($code, $module, $a), 'success', $checked_to_send_grade, $prevents_sending_grade);
    }
}
