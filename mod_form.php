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
 * The main mod_teacherinfo configuration form.
 *
 * @package     mod_teacherinfo
 * @copyright   2019, Creatic SAS <soporte@creatic.co>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package    mod_teacherinfo
 * @copyright  2019, Creatic SAS <soporte@creatic.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_teacherinfo_mod_form extends moodleform_mod {

    /**
     * Form definition.
     *
     * @throws HTML_QuickForm_Error
     * @throws coding_exception
     */
    public function definition() {
        global $CFG, $DB, $COURSE;

        $customfields = $DB->get_records('user_info_field', null, 'id,name,shortname,description');
        $mform = $this->_form;

        $context = $this->get_context();

        $mform->addElement('header', 'headercourseinfo', get_string('courseinfoheader', 'mod_teacherinfo'));
        $mform->addElement('static', '', '', get_string('courseinfohelp', 'mod_teacherinfo'));

        // Checkbox that enables course information show.
        $mform->addElement('advcheckbox', 'courseinfoenabled', get_string('courseinfoenabled', 'mod_teacherinfo'));
        $mform->addElement('text', 'infotitle', get_string('infotitle', 'mod_teacherinfo'),
                ['style' => 'margin-bottom: -20px;']);
        $mform->setType('infotitle', PARAM_TEXT);
        $mform->addElement('static', '', '', get_string('titlehelp', 'mod_teacherinfo'));

        // Enable custom course information.
        $mform->addElement('advcheckbox', 'customcourseinfo', get_string('customcourseinfo', 'mod_teacherinfo'));
        $mform->hideif('customcourseinfo', 'courseinfoenabled', 'notchecked');
        $this->standard_intro_elements();
        $mform->hideif('introeditor', 'customcourseinfo', 'notchecked');

        // Teacher information settings.
        $mform->addElement('header', 'headerteacherinfo', get_string('teacherinfoheader', 'mod_teacherinfo'));
        $mform->addElement('static', '', '', get_string('teacherinfohelp', 'mod_teacherinfo'));

        // SQL for getting course participants
        $sqlquery = 'SELECT ra.userid, u.firstname, u.lastname
                       FROM {role_assignments} ra
                 INNER JOIN {context} c
                         ON ra.contextid = c.id
                 INNER JOIN {user} u
                         ON ra.userid = u.id
                      WHERE c.instanceid = :courseid
                        AND c.contextlevel = 50
                   GROUP BY ra.userid';

        $teachers = $DB->get_records_sql($sqlquery, ['courseid' => $COURSE->id]);

        // Filters teacher from participants
        if(!empty($teachers)) {
            $teacherlist = [];
            foreach($teachers as $teacher) {
                if(!has_capability('moodle/grade:edit', $context, $teacher->userid)) {
                    unset($teachers[$teacher->userid]);
                } else {
                    $teacherlist[$teacher->userid] = $teacher->firstname . ' ' . $teacher->lastname;
                }
            }
            $mform->addElement('select', 'userid', get_string('selectteacher', 'mod_teacherinfo'), $teacherlist);
        } else {

        }

        $mform->addElement('text', 'teachertitle', get_string('teachertitle', 'mod_teacherinfo'),
                ['style' => 'margin-bottom: -20px;']);
        $mform->setType('teachertitle', PARAM_TEXT);
        $mform->addElement('static', '', '', get_string('titlehelp', 'mod_teacherinfo'));

        // Enable teacher avatar.
        $mform->addElement('advcheckbox', 'avatarenabled', get_string('avatarenabled', 'mod_teacherinfo'));

        // Enable teacher fullname.
        $mform->addElement('advcheckbox', 'fullnameenabled', get_string('fullnameenabled', 'mod_teacherinfo'));

        // Enable teacher description.
        $mform->addElement('advcheckbox', 'descriptionenabled', get_string('descriptionenabled', 'mod_teacherinfo'));

        // Custom fields.
        $mform->addElement('header', 'headercustomfields', get_string('customfields', 'mod_teacherinfo'));

        // Checkbox allows to select what custom fields to show.
        foreach($customfields as $customfield) {
            $mform->addElement('advcheckbox', 'customfields[' . $customfield->id . ']', $customfield->name);
        }

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Process data before displaying it on the setting form.
     *
     * @param array $default_values Teacher's information settings.
     */
    public function data_preprocessing(&$default_values) {

        // Converts selected custom fields JSON to variables to load into settings form.
        if (isset($default_values['customfields'])) {
            $customfields = json_decode($default_values['customfields']);
            $default_values['customfields'] = array();
            foreach($customfields as $name => $value) {
                $default_values['customfields'][$name] = $value;
            }
        }
    }
}
