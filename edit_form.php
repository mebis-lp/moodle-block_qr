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
 * Form for qr.
 *
 * @package    block_qr
 * @copyright  2023 ISB Bayern
 * @author     Florian Dagner <florian.dagner@outlook.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_qr_edit_form extends block_edit_form {
    /**
     * Loads the modules of the corresponding course (if there is one).
     *
     * @return void
     */
    public function generate_course_module_list(): void {
        if (isset($this->courselist)) {
            return;
        }

        if (!is_null($this->page->course)) {
            $this->iscourse = true;
            $courseid = $this->page->course->id;
        }

        $cm = get_fast_modinfo($courseid);

        $courselist = [];
        foreach ($cm->sections as $sectionnum => $section) {
            $sectioninfo = $cm->get_section_info($sectionnum);
            $cmid = 'section=' . $sectionnum;
            $name = $sectioninfo->name;
            if (empty($name)) {
                if ($sectionnum == 0) {
                    $name = get_string('general');
                } else {
                    $name = get_string('section') . ' ' . $sectionnum;
                }
            }

            $courselist[$cmid] = '--- ' . $name . ' ---';
            foreach ($section as $cmid) {
                $module = $cm->get_cm($cmid);
                // Get only course modules which are not deleted.
                if ($module->deletioninprogress == 0) {
                    $courselist['cmid=' . $cmid] = $module->name;
                }
            }
        }
        $this->courselist = $courselist;
    }

    /**
     * specific_definition
     *
     * @param mixed $mform
     */
    protected function specific_definition($mform) {
        $this->generate_course_module_list();
        // Section header title.
        $mform->addElement('header', 'widthheader', get_string('codecontent', 'block_qr'));
        // Add options for the creation of the qr code.
        $options = [
            'currenturl' => get_string('currenturl', 'block_qr'),
            'courseurl' => get_string('courseurl', 'block_qr'),
            'internalcontent' => get_string('internalcontent', 'block_qr'),
            'owncontent' => get_string('owncontent', 'block_qr'),
            'event' => get_string('event', 'block_qr'),
            'geolocation' => get_string('geolocation', 'block_qr')
        ];
        $courseid = $this->page->course->id;
        if ($courseid == SITEID) {
            foreach ($options as $key => $value) {
                if ($key == "courseurl" || $key == "internalcontent") {
                    unset($options[$key]);
                }
            }
        }

        $selectoptions = $mform->addElement(
            'select',
            'config_options',
            '',
            $options,
        );
        $selectoptions->setSelected(0);
        $mform->setType('config_options', PARAM_TEXT);
        // Course link.
        $mform->addElement('text', 'config_courseurl', get_string('courseurl_label', 'block_qr'), 'size="40"');
        $mform->hideIf('config_courseurl', 'config_options', 'neq', 'courseurl');
        $mform->setType('config_courseurl', PARAM_TEXT);
         // Selection for internal links.
        $mform->addElement(
            'select',
            'config_internal',
            get_string('internalcontent_label', 'block_qr'),
            $this->courselist,
        );
        $mform->hideIf('config_internal', 'config_options', 'neq', 'internalcontent');
        $mform->setType('config_internal', PARAM_TEXT);
        // Text field.
        $mform->addElement('text', 'config_owncontent', get_string('owncontent_label', 'block_qr'), 'size="40"');
        $mform->hideIf('config_owncontent', 'config_options', 'neq', 'owncontent');
        $mform->setType('config_owncontent', PARAM_NOTAGS);
        // Calendar fields.
        $mform->addElement('text', 'config_event_summary', get_string('event_summary', 'block_qr'));
        $mform->setType('config_event_summary', PARAM_TEXT);
        $mform->hideIf('config_event_summary', 'config_options', 'neq', 'event');
        $mform->addElement('text', 'config_event_location', get_string('event_location', 'block_qr'));
        $mform->hideIf('config_event_location', 'config_options', 'neq', 'event');
        $mform->setType('config_event_location', PARAM_TEXT);
        $mform->addElement('advcheckbox', 'config_allday', get_string('allday', 'block_qr'));
        $mform->hideIf('config_allday', 'config_options', 'neq', 'event');
        $mform->addElement('date_time_selector', 'config_event_start', get_string('event_start', 'block_qr'));
        $mform->hideIf('config_event_start[hour]', 'config_allday', 'neq', '0');
        $mform->hideIf('config_event_start[minute]', 'config_allday', 'neq', '0');
        $mform->hideIf('config_event_start', 'config_options', 'neq', 'event');
        $mform->setType('config_event_start', PARAM_RAW);
        $mform->addElement('date_time_selector', 'config_event_end', get_string('event_end', 'block_qr'));
        $mform->hideIf('config_event_end', 'config_options', 'neq', 'event');
        $mform->hideIf('config_event_end[hour]', 'config_allday', 'neq', '0');
        $mform->hideIf('config_event_end[minute]', 'config_allday', 'neq', '0');
        $mform->setType('config_event_end', PARAM_RAW);
        // Geolocations fields.
        $mform->addElement('text', 'config_geolocation_br', get_string('latitude', 'block_qr'));
        $mform->addRule(
            'config_geolocation_br',
            get_string('latitude_error', 'block_qr'),
            'regex',
            '/^-?([0-8]?[0-9]|90)(\.[0-9]{1,20})?$/',
            'client'
        );
        $mform->addHelpButton('config_geolocation_br', 'config_geolocation_br', 'block_qr');
        $mform->setType('config_geolocation_br', PARAM_TEXT);
        $mform->addElement('text', 'config_geolocation_lng', get_string('longitude', 'block_qr'));
        $mform->hideIf('config_geolocation_br', 'config_options', 'neq', 'geolocation');
        $mform->addRule(
            'config_geolocation_lng',
            get_string('longitude_error', 'block_qr'),
            'regex',
            '/^-?([0-9]{1,2}|1[0-7][0-9]|180)(\.[0-9]{1,20})?$/',
            'client'
        );
        $mform->addHelpButton('config_geolocation_lng', 'config_geolocation_lng', 'block_qr');
        $mform->hideIf('config_geolocation_lng', 'config_options', 'neq', 'geolocation');
        $mform->setType('config_geolocation_lng', PARAM_TEXT);
        $linkoptions = [
            'nolink' => get_string('nolink', 'block_qr'),
            'osm' => get_string('osm', 'block_qr'),
        ];
        $mform->addElement(
            'select',
            'config_link',
            get_string('config_link_label', 'block_qr'),
            $linkoptions,
        );
        $mform->hideIf('config_link', 'config_options', 'neq', 'geolocation');
        $mform->setType('config_link', PARAM_TEXT);

        // Section header title.
        $mform->addElement('header', 'widthheader', get_string('settings', 'block_qr'));
        // Settings.
        define('QR_SIZE_SMALL', '150px');
        define('QR_SIZE_MEDIUM', '200px');
        define('QR_SIZE_LARGE', '275px');
        $sizeoptions = [
            QR_SIZE_SMALL => get_string('small', 'block_qr'),
            QR_SIZE_MEDIUM => get_string('medium', 'block_qr'),
            QR_SIZE_LARGE => get_string('large', 'block_qr')
        ];
        $selectsize = $mform->addElement(
            'select',
            'config_size',
            get_string('config_size_label', 'block_qr'),
            $sizeoptions,
        );
        $selectsize->setSelected('275px');
        $mform->setType('config_size', PARAM_TEXT);
    }
    // Validation of start date and end date in calendar fields.
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['config_event_start'] > $data['config_event_end']) {
            $errors['config_event_end'] = get_string('date_compare', 'block_qr');
        }
        return $errors;
    }

}
