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
 * Class block_qr
 *
 * @package    block_qr
 * @copyright  2023 ISB Bayern
 * @author     Florian Dagner <florian.dagner@outlook.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_qr extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_qr');
    }

    public function get_content() {
        global $OUTPUT, $CFG;
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($this->instance)) {
            return $this->content;
        }

        $this->content = new stdClass;
        $context = new stdClass;

        if ($this->page->course) {
            $context->courseid = $this->page->course->id;
            $modinfo = get_fast_modinfo($context->courseid);

            if ($this->page->cm) {
                $context->cmid = $this->page->cm->id;
                $context->sectionnum = $this->page->cm->sectionnum;
            } else {
                $context->cmid = null;
                $context->sectionnum = optional_param('section', 0, PARAM_INT);
            }

            if ($context->sectionnum > 0) {
                $context->prevsectionnum = $context->sectionnum - 1;
            }
            if ($context->sectionnum < count($modinfo->get_section_info_all()) - 1) {
                $context->nextsectionnum = $context->sectionnum + 1;
            }
        }

        $format = core_courseformat\base::instance($context->courseid);

        $configcontent = null;
        $qrcodecontent = null;
        $tooltip = null;

        switch ($this->config->options ?? 0) {
            case '0':
                $qrcodecontent = $this->page->url;
                $configcontent = get_string('currenturl', 'block_qr');
                $tooltip = $qrcodecontent;
                break;
            case '1':
                $qrcodecontent = (
                            new moodle_url(
                                '/course/view.php',
                                ['id' => $context->courseid]
                            )
                        )->out();
                $configcontent = $this->config->courseurl;
                $tooltip = $qrcodecontent;
                break;
            case '2':
                list($type, $id) = explode('=', $this->config->internal);
                switch ($type) {
                    case 'cmid':
                        $module = $modinfo->get_cm($id);
                        if (!is_null($module->get_url())) {
                            $configcontent = $module->name;
                            $qrcodecontent = $module->url;
                            $tooltip = $qrcodecontent;
                        } else {
                            $configcontent = $module->name;
                            $qrcodecontent = $format->get_view_url($module->sectionnum);
                            $anchor = 'module-' . $id;
                            $qrcodecontent->set_anchor($anchor);
                            $tooltip = $qrcodecontent;
                        }
                        break;
                    case 'section':
                        $section = null;
                        $sectioninfo = $modinfo->get_section_info($id);
                        if (!is_null($sectioninfo)) {
                            $configcontent = $sectioninfo->name;
                            if (empty($name)) {
                                if ($id == 0) {
                                    $configcontent = get_string('general');
                                } else {
                                    $configcontent = get_string('section') . ' ' . $id;
                                }
                            }
                            $qrcodecontent = $format->get_view_url($id);
                            $anchor = 'section-' . $id;
                            $section = $id;
                            $tooltip = $qrcodecontent;
                        }
                        break;
                }
                break;
            case '3':
                $qrcodecontent = $this->config->content;
                $configcontent = "";
                $tooltip = $qrcodecontent;
                break;

            case '4':
                $qrcodecontent = "BEGIN:VCALENDAR" . '\n';
                $qrcodecontent .= "VERSION:2.0" . '\n';
                $qrcodecontent .= "BEGIN:VEVENT" . '\n';
                $qrcodecontent .= "SUMMARY:" . $this->config->event_summary . '\n';
                $qrcodecontent .= "LOCATION:" . $this->config->event_location . '\n';
                switch ($this->config->allday) {
                    case '0':
                        $qrcodecontent .= "DTSTART:" . date('Ymd\THis', $this->config->event_start) . '\n';
                        $qrcodecontent .= "DTEND:" . date('Ymd\THis', $this->config->event_end) . '\n';
                        break;
                    case '1':
                        $qrcodecontent .= "DTSTART:" . date('Ymd', $this->config->event_start) . '\n';
                        $qrcodecontent .= "DTEND:" . date('Ymd', $this->config->event_end) . '\n';
                }
                $qrcodecontent .= "END:VEVENT" . '\n';
                $qrcodecontent .= "END:VCALENDAR" . '\n';
                $configcontent = $this->content->text .= get_string('event', 'block_qr');
                $tooltip = $this->config->event_summary . "<br>";
                $tooltip .= $this->config->event_location . "<br>";
                $tooltip .= date('d.m.Y - H:i', $this->config->event_start) . "<br>";
                $tooltip .= date('d.m.Y - H:i', $this->config->event_end);
               break;

            case '5':
                $qrcodecontent = "geo:" . $this->config->geolocation_br . "," . $this->config->geolocation_lng;
                $configcontent = get_string('geolocation', 'block_qr');
                $tooltip = $qrcodecontent;
        }

        $svgsize = isset($this->config->size) ? $this->config->size : '275px';

        // Use for multiple id for multiple QR codes on one page.
        $blockid = $this->context->id;

        $javascripturl = $CFG->wwwroot . '/blocks/qr/js/qrcode.min.js';

        $data = [
            'qrcodecontent' => $qrcodecontent,
            'message' => $configcontent,
            'javascript' => $javascripturl,
            'size' => $svgsize,
            'id' => $blockid,
            'tooltip' => $tooltip
        ];
        $this->content->text = $OUTPUT->render_from_template('block_qr/qr', $data);
        return $this->content;
    }



    /**
     * Locations where block can be displayed.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['all' => true];
    }

    /**
     * Allow multiple instances.
     *
     * @return boolean
     */
    public function instance_allow_multiple() {
        return true;
    }

}
