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
 * Library of interface functions and constants.
 *
 * @package     mod_teacherinfo
 * @copyright   2019, Creatic SAS <soporte@creatic.co>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */

function teacherinfo_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        case FEATURE_NO_VIEW_LINK:            return true;

        default: return null;
    }
}

/**
 * Saves a new instance of the mod_teacherinfo into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_teacherinfo_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function teacherinfo_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->name = '...';
    $moduleinstance->customfields = json_encode($moduleinstance->customfields);
    $moduleinstance->timecreated = time();

    $id = $DB->insert_record('teacherinfo', $moduleinstance);

    return $id;
}

/**
 * Updates an instance of the mod_teacherinfo in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param mod_teacherinfo_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function teacherinfo_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->name = '...';
    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;
    $moduleinstance->customfields = json_encode($moduleinstance->customfields);
    return $DB->update_record('teacherinfo', $moduleinstance);
}

/**
 * Removes an instance of the mod_teacherinfo from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function teacherinfo_delete_instance($id) {
    global $DB;

    $exists = $DB->get_record('teacherinfo', array('id' => $id));
    if (!$exists) {
        return false;
    }

    $DB->delete_records('teacherinfo', array('id' => $id));

    return true;
}

/**
 * Shows teacher information on course view.
 *
 * @param object $coursemodule Course module information.
 * @return object $info. Returns a course module cached info object.
 */
function teacherinfo_get_coursemodule_info($coursemodule) {
    global $DB, $PAGE;

    /* Loads CSS */
    $PAGE->requires->css(new moodle_url('/mod/teacherinfo/styles.css'));

    /* Loads Teacher Information module data */

    $teacherinfo = $DB->get_record('teacherinfo', ['id' => $coursemodule->instance]);

    /* Variable $html will show renderized information. */
    $html = '';

    /* If course information is enabled, shows course information data */
    if($teacherinfo->courseinfoenabled) {
        /* If custom title is set, it shows custom title. Otherwise, it loads default title from language strings. */
        $infotitle = !empty($teacherinfo->infotitle)
                ? $teacherinfo->infotitle : get_string('infotitledef', 'mod_teacherinfo');

        $html .= html_writer::tag('h3', $infotitle, ['class' => 'header title']);
        if($teacherinfo->customcourseinfo) {
            $courseinfo = format_module_intro('teacherinfo', $teacherinfo, $coursemodule->id, false);
            $html .= html_writer::div($courseinfo, 'description');
        } else {
            $currentcourseinfo = $DB->get_record('course', ['id' => $coursemodule->course], 'id,summary');
            $courseinfo = $currentcourseinfo->summary;
            $html .= html_writer::div($courseinfo, 'description');
        }
    }
    /* If custom title is set, it shows custom title. Otherwise, it loads default title from language strings. */
    $teachertitle = !empty($teacherinfo->teachertitle)
            ? $teacherinfo->teachertitle : get_string('teachertitledef', 'mod_teacherinfo');

    $html .= html_writer::tag('h3', $teachertitle, ['class' => 'header title']);

    /* Gets information about specified user to render and show. */
    if($userinfo = $DB->get_record('user', ['id' => $teacherinfo->userid], 'id,firstname,lastname,picture,description')) {
        /* Shows avatar if enabled */
        if($teacherinfo->avatarenabled && $avatarinfo = $DB->get_record('files', ['id' => $userinfo->picture])) {
            $avatarurl = moodle_url::make_pluginfile_url($avatarinfo->contextid, $avatarinfo->component,
                    $avatarinfo->filearea, null, $avatarinfo->filepath, 'adaptable/f3', false);

            $avatarimg = html_writer::img($avatarurl, get_string('useravatar', 'mod_teacherinfo'));
            $avatarimg .= html_writer::tag('p', get_string('authorinfo', 'mod_teacherinfo'), ['class' => 'author-info']);
            $html .= html_writer::div($avatarimg, 'image');
        }

        /* If avatar is enabled, in PC screen it distributes space between avatar and information */
        $teacherinfoclass = $teacherinfo->avatarenabled ? 'teacher-information with-avatar' : 'teacher-information';
        $html .= html_writer::start_div($teacherinfoclass);

        /* Shows full name if enabled */
        if($teacherinfo->fullnameenabled) {
            $html .= html_writer::tag('h4', $userinfo->firstname . ' ' . $userinfo->lastname, ['class' => 'title']);
        }

        /* Shows description if enabled */
        if($teacherinfo->descriptionenabled && !empty($userinfo->description)) {
            $html .= html_writer::tag('p', $userinfo->description);
        }

        /* Determines whether custom fields are enabled for show */
        $customfields = json_decode($teacherinfo->customfields);
        $enabledcustomfields = [];

        foreach($customfields as $name => $value) {
            if($value) {
                $enabledcustomfields[] = $name;
            }
        }

        /* If there's any enabled custom fields, it gets data and shows on the view course page. */
        if(!empty($enabledcustomfields)) {
            $customdatasql = 'AND (';
            $customdatasql .= 'ud.fieldid = ' . implode($enabledcustomfields, ' OR ud.fieldid = ') . ')';

            /* A query for getting specified user's data */
            $sqlquery = 'SELECT ud.fieldid, uf.name, uf.datatype, ud.data, uf.param3
                           FROM {user_info_data} ud
                     INNER JOIN {user_info_field} uf
                             ON uf.id = ud.fieldid
                          WHERE ud.userid = :userid'
                              . $customdatasql;

            $customfieldsdata = $DB->get_records_sql($sqlquery, ['userid' => $teacherinfo->userid]);
            $html .= html_writer::start_tag('ul', ['class' => 'custom-fields']);

            foreach($customfieldsdata as $customfield) {
                switch($customfield->datatype) {
                    case 'datetime':

                        if($customfield->param3) {
                            $fielddata = userdate($customfield->data, get_string('strftimedaydatetime', 'core_langconfig'));
                        } else {
                            $fielddata = userdate($customfield->data, get_string('strftimedate', 'core_langconfig'));
                        }

                        break;
                    default:
                        $fielddata = $customfield->data;
                        break;
                }

                $fieldname = html_writer::tag('strong', $customfield->name . ': ');
                $html .= html_writer::tag('li', $fieldname . $fielddata);
            }
            $html .= html_writer::end_tag('ul');
            $html .= !$teacherinfo->avatarenabled ? html_writer::tag('p',
                    get_string('authorinfo', 'mod_teacherinfo'), ['class' => 'author-info']) : '';
        }
    }

    $info = new cached_cm_info();
    $info->content = $html;

    return $info;
}
