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
 * Renderer for the gradebook export externalsystem
 *
 * @package   gradeexport_externalsystem
 * @copyright 2015 Antonio Carlos Mariani <antonio.c.mariani@ufsc.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class gradeexport_externalsystem_renderer extends plugin_renderer_base {

    private $baseurl;
    private $drv;
    private $canedit;
    private $columns_count;

    /**
     * Render report
     *
     * @param string $baseurl form url
     * @param object $drv instance of gradeexport/externalsystem driver
     * @param boolean $canedit user can edit or just view
     * @param array $extra_messages messages to be shown
     * @return void
     */
    public function show($baseurl, $drv, $canedit, $extra_messages) {
        global $OUTPUT, $PAGE;

        $this->baseurl = $baseurl;
        $this->drv = $drv;
        $this->canedit = $canedit;
        $this->drv->populate();

        $PAGE->requires->js_init_call('M.gradeexport_externalsystem.init', array());

        // Populate table rows
        $rows = $this->render_header();
        $count = $this->render_enrolled_students($rows, $extra_messages);

        if ($this->drv->groupid <= 0) {
            $count = $this->render_not_enrolled_external_students($rows, $count);
        }

        if ($this->canedit) {
            $rows[] = $this->render_footer();
        }

        // Print driver actions
        echo $this->render_driver_actions();

        // Print form
        echo $OUTPUT->box_start('generalbox boxaligncenter');
        if ($this->canedit) {
            echo html_writer::start_tag('form', array('method'=>'POST', 'action'=>$this->baseurl->out_omit_querystring()));
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey()));
            if (empty($this->drv->parent)) {
                echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=> $this->drv->course->id));
            } else {
                echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=> $this->drv->parent->id));
                echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'affiliatedcourseid', 'value'=> $this->drv->course->id));
            }
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'groupid', 'value'=> $this->drv->groupid));
        }

        // Print students table
        $table = new html_table();
        $table->align = array();
        $table->attributes = array('class' => 'generaltable path-grade-report-grader');
        $table->data = $rows;
        echo html_writer::table($table);

        // End form
        if ($this->canedit) {
            echo html_writer::end_tag('form');
        }

        echo $OUTPUT->box_end();
    }

    /**
     * Render students table header
     *
     * @return array Lines of the students table header
     */
    private function render_header() {
        $row1 = array();
        $row2 = array();
        $this->columns_count = 1;

        $cell1 = new html_table_cell();
        $cell1->text =  '';
        $cell1->rowspan = 2;
        $row1[] = $cell1;

        foreach ($this->drv->fieldmapping AS $field => $fielddesc) {
            $this->columns_count++;
            $cell1 = new html_table_cell();
            $cell1->header = true;

            if ($field == 'fullname' || $field == 'userident') {
                $url = clone($this->baseurl);
                $url->param('orderby', $field);
                $cell1->text = html_writer::link($url, $fielddesc['name']);
            } else {
                $cell1->text = $fielddesc['name'];
            }

            $cell1->style = 'vertical-align:middle;';

            $table->align[] = $fielddesc['align'];
            if ($field == 'grade' || ($this->canedit && $fielddesc['editable'])) {
                $cell1->colspan = 2;
                $cell1->style .= 'text-align:center;';

                $cell2 = new html_table_cell();
                $cell2->header = true;
                $cell2->text = $this->drv->external_column_name();
                $cell2->style = 'text-align:' . $fielddesc['align'];
                $row2[] = $cell2;

                $cell2 = new html_table_cell();
                $cell2->header = true;
                $cell2->text = get_string('local', 'gradeexport_externalsystem');
                $cell2->style = 'text-align:' . $fielddesc['align'];
                $row2[] = $cell2;
                $this->columns_count++;
            } else if ($fielddesc['editable']) {
                $cell1->style .= 'text-align:' . $fielddesc['align'];

                $cell2 = new html_table_cell();
                $cell2->header = true;
                $cell2->text = $this->drv->external_column_name();
                $cell2->style = 'text-align:' . $fielddesc['align'];
                $row2[] = $cell2;
                $this->columns_count++;
            } else {
                $cell1->rowspan = 2;
                $cell1->style .= 'text-align:' . $fielddesc['align'];
            }
            $row1[] = $cell1;
        }

        $cell1 = new html_table_cell();
        $cell1->header = true;
        $cell1->text = get_string('remarks', 'gradeexport_externalsystem');
        $cell1->rowspan = 2;
        $cell1->style = 'vertical-align:middle; text-align:left;';
        $row1[] = $cell1;

        if ($this->canedit) {
            $cell1 = new html_table_cell();
            $cell1->header = true;
            $cell1->text = get_string('send', 'gradeexport_externalsystem');
            $cell1->rowspan = 2;
            $cell1->style = 'vertical-align:middle; text-align:right;';
            $row1[] = $cell1;
        }
        return array($row1, $row2);
    }

    /**
     * Render students table with students that are enrolled in the course
     *
     * @param array $rows array with previous lines where the students will be renderer
     * @return int The number of lines included in $rows
     */
    private function render_enrolled_students(&$rows, $extra_messages) {
        $userfield = $this->drv->userfield;

        $count = 0;
        foreach ($this->drv->enrolledstudents AS $userid => $st) {
            $row = array();

            $count++;
            $row[] = $count . '.';

            $messages = array();
            if (isset($extra_messages[$userid])) {
                if (is_array($extra_messages[$userid])) {
                    $messages = $extra_messages[$userid];
                } else {
                    $messages[] = $extra_messages[$userid];
                }
            }
            if (isset($st->message)) {
                $messages[] = $st->message;
            }

            $userident = $st->$userfield;

            if (isset($this->drv->externaldata[$userident])) {
                $extdata = $this->drv->externaldata[$userident];
                $this->drv->externaldata[$userident]->registered = true;
            } else {
                $extdata = false;
                $messages = array(gradeexport_externalsystem_message::error('notexternal'));
            }

            foreach ($this->drv->fieldmapping AS $field => $fielddesc) {
                switch ($field) {
                    case 'userident':
                        $cell = new html_table_cell();
                        $cell->style = 'text-align:'.$fielddesc['align'];
                        $cell->text = $userident;
                        $row[] = $cell;
                        break;
                    case 'fullname':
                        $cell = new html_table_cell();
                        $cell->style = 'text-align:'.$fielddesc['align'];
                        $cell->text = $this->drv->fullname($st);
                        $row[] = $cell;
                        break;
                    case 'grade';
                        $cell = new html_table_cell();
                        $cell->text = $extdata ? $this->drv->format_external_grade($extdata->grade) : '';
                        $cell->style = 'text-align:'.$fielddesc['align'];
                        $row[] = $cell;

                        $cell = new html_table_cell();
                        $cell->style = 'text-align:'.$fielddesc['align'];
                        $grade = $this->drv->grade($userid);
                        $formatted = $this->drv->format_local_grade($grade);
                        $cell->text = html_writer::tag('span', $formatted, array('class' => $this->drv->class_for_gradepass($grade)));
                        $row[] = $cell;
                        break;
                    default:
                        $cell = new html_table_cell();
                        $cell->style = 'text-align:'.$fielddesc['align'];

                        if ($fielddesc['source'] == 'driver') {
                            $cell->text = $this->drv->get_driverdata($field, $userid, true);
                            $row[] = $cell;
                        } else if ($fielddesc['source'] == 'external') {
                            if ($fielddesc['type'] == PARAM_BOOL) {
                                $checked = false;
                                if ($extdata && $extdata->$field) {
                                    $checked = true;
                                    $cell->text = html_writer::checkbox("{$field}_external[$userid]]", true, $checked, '', array('disabled' => 'disabled'));
                                } else {
                                    $cell->text = '';
                                }
                                $row[] = $cell;

                                if ($this->canedit && $fielddesc['editable']) {
                                    if ($extdata) {
                                        $cell = new html_table_cell();
                                        $cell->style = 'text-align:'.$fielddesc['align'];
                                        $cell->text = html_writer::checkbox("{$field}[{$userid}]", true, $checked, '', array('id' => "{$field}_{$userid}", 'class' => 'gradeexport_externalsystem_editable'));
                                        $row[] = $cell;
                                    } else {
                                        $row[] = '';
                                    }
                                }
                            } else {
                                $cell->text = $extdata ? $extdata->$field : '';
                                $row[] = $cell;
                            }
                        } else {
                            $cell->text = '?';
                            $row[] = $cell;
                        }
                }
            }

            if ($extdata) {
                $grade = $this->drv->grade($userid);
                $result = $this->drv->check_grade($grade, $extdata);
                if (is_object($result)) {
                    $messages[] = $result;
                } else if (is_array($result)) {
                    $messages = array_merge($messages, $result);
                }
            }

            $row[] = $this->render_messages($messages);

            if ($this->canedit) {
                $show_send_checkbox = true;
                $checked = false;
                foreach($messages AS $m) {
                    $show_send_checkbox = $show_send_checkbox && !$m->prevents_sending_grade;
                    $checked = $checked || $m->checked_to_send_grade;
                }

                if ($show_send_checkbox) {
                    $cell = new html_table_cell();
                    $cell->style = 'text-align:right';
                    $cell->text = html_writer::checkbox("send[{$userid}]", $userident, $checked, '', array('id' => "send_{$userid}"));
                    $row[] = $cell;
                } else {
                    $row[] = '';
                }
            }

            $rows[] = $row;
        }

        return $count;
    }

    /**
     * Render students table with students that are subscribed in the external system but not enrolled in the course
     *
     * @param array $rows array with previous lines where the students will be renderer
     * @return int The initial value of $count plus the number of lines included in $rows
     */
    private function render_not_enrolled_external_students(&$rows, $count) {
        foreach ($this->drv->externaldata AS $userident => $extdata) {
            if (isset($extdata->registered)) {
                continue;
            }

            $row = array();

            $count++;
            $row[] = $count . '.';

            foreach ($this->drv->fieldmapping AS $field => $fielddesc) {
                switch ($field) {
                    case 'userident';
                    $cell = new html_table_cell();
                    $cell->text = $extdata->userident;
                    $cell->style = 'text-align:'.$fielddesc['align'];
                    $row[] = $cell;
                    break;
                    case 'fullname';
                    $cell = new html_table_cell();
                    $cell->text = $extdata->fullname;
                    $cell->style = 'text-align:'.$fielddesc['align'];
                    $row[] = $cell;
                    break;
                    case 'grade';
                    $cell = new html_table_cell();
                    $cell->text = $this->drv->format_external_grade($extdata->grade);
                    $cell->style = 'text-align:'.$fielddesc['align'];
                    $row[] = $cell;

                    $row[] = '';
                    break;
                    default:
                    if ($fielddesc['source'] == 'driver') {
                        $row[] = '';
                    } else if ($fielddesc['source'] == 'external') {
                        $cell = new html_table_cell();
                        $cell->style = 'text-align:'.$fielddesc['align'];
                        if ($fielddesc['type'] == PARAM_BOOL) {
                            if ($extdata->$field) {
                                $cell->text = html_writer::checkbox("{$field}_external[$userid]]", true, $extdata->$field, '', array('disabled' => 'disabled'));
                            } else {
                                $cell->text = '';
                            }
                            $row[] = $cell;

                            if ($this->canedit && $fielddesc['editable']) {
                                $row[] = '';
                            }
                        } else {
                            $cell->text = $extdata ? $extdata->$field : '';
                            $row[] = $cell;
                        }
                    } else {
                        $row[] = '?';
                    }
                }
            }
            $messages = array(gradeexport_externalsystem_message::error('notlocal'));
            $row[] = $this->render_messages($messages);

            if ($this->canedit) {
                $row[] = '';
            }

            $rows[] = $row;
        }

        return $count;
    }

    /**
     * Render students table footer
     *
     * @return array The last line of the students table
     */
    private function render_footer() {
        $row = array();

        for ($i = 1; $i <= $this->columns_count; $i++) {
            $row[] = '';
        }
        $cell = new html_table_cell();
        $cell->text = html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('send_grades', 'gradeexport_externalsystem'),
                        'name' => 'sendgrades', 'class' => 'form-submit'));
        $cell->style = 'text-align:right';
        $row[] = $cell;

        $cell = new html_table_cell();
        $cell->text = html_writer::empty_tag('img', array('src' => 'images/arrow_rtl.png'));
        $cell->style = 'text-align:right';
        $row[] = $cell;

        return $row;
    }

    /**
     * Render messages
     *
     * @param array $messages messages to be render
     * @return string
     */
    private function render_messages($messages) {
        $text = '';
        foreach($messages AS $m) {
            $text .= $m->show();
        }
        return $text;
    }

    /**
     * Render driver actions
     *
     * @return string
     */
    private function render_driver_actions() {
        global $OUTPUT;

        $drv_actions = $this->drv->actions();
        if (empty($drv_actions) || empty($drv_actions['actions'])) {
            return '';
        }

        $align = isset($drv_actions['align']) ? $drv_actions['align'] : 'right';
        $format = isset($drv_actions['format']) ? $drv_actions['format'] : 'button';
        $actions = $drv_actions['actions'];

        $elements = array();
        foreach ($actions AS $action => $label) {
            $url = clone $this->baseurl;
            $url->param('action', $action);
            if ($format == 'button') {
                $elements[] = $OUTPUT->single_button($url, $label, 'post', array('class' => "gradeexport_externalsystem_align_{$align}"));
            } else {
                $elements[] = html_writer::link($url, $label);
            }
        }

        if ($format == 'button') {
            return $OUTPUT->container(implode('', $elements));
        } else {
            return html_writer::tag('div', implode(' | ', $elements), array('align' => $align));
        }
    }
}
