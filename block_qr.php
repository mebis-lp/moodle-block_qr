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

    /**
     * Sets the block title.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_qr');
    }

     /**
      * Returns the contents.
      *
      * @return stdClass
      */
    public function get_content() {
        global $CFG, $OUTPUT, $USER;
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
        }

        $format = core_courseformat\base::instance($context->courseid);

        $configcontent = null;
        $qrcodecontent = null;
        $qrurl = false;
        $qrcodelink = null;
        $geocoordinates = null;
        $calendar = false;
        $calendarsummary = null;
        $calendarlocation = null;
        $calendarstart = null;
        $calendarend = null;
        $fullview = false;
        $svgsize = null;
        switch ($this->config->options ?? 0) {
            case 'currenturl':
                $qrcodecontent = $this->page->url->out(false);
                $configcontent = get_string('thisurl', 'block_qr');
                $qrcodelink = $qrcodecontent;
                $qrurl = true;
                $calendar = false;
                break;
            case 'courseurl':
                $qrcodecontent = (
                    new moodle_url(
                    '/course/view.php',
                        ['id' => $context->courseid]
                    )
                )->out(false);
                $qrcodelink = $qrcodecontent;
                $qrurl = true;
                $calendar = false;
                break;
            case 'internalcontent':
                list($type, $id) = explode('=', $this->config->internal);
                $qrurl = true;
                $calendar = false;
                switch ($type) {
                    case 'cmid':
                        $module = $modinfo->get_cm($id);
                        if (!is_null($module->get_url())) {
                            $configcontent = $module->name;
                            $qrcodecontent = $module->url;
                            $qrcodelink = $qrcodecontent;
                        } else {
                            $configcontent = $module->name;
                            $qrcodecontent = $format->get_view_url($module->sectionnum)->out(false);
                            $anchor = 'module-' . $id;
                            $qrcodecontent->set_anchor($anchor);
                            $qrcodelink = $qrcodecontent;
                        }
                        break;
                    case 'section':
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
                            
                            $qrcodecontent = $format->get_view_url($id)->out(false);
                            $anchor = 'section-' . $id;
                            $qrcodelink = $qrcodecontent;
                        }
                        break;
                }
                break;
            case 'owncontent':
                $url = $this->config->owncontent;
                $configcontent = "";
                $qrcodecontent = $url;
                $qrcodelink = $qrcodecontent;
                if (filter_var($qrcodecontent, FILTER_VALIDATE_URL) === false) {
                    $qrurl = false;
                } else {
                    $qrurl = true;
                    $calendar = false;
                }
                break;

            case 'event':
                $qrcodecontent = "BEGIN:VCALENDAR" . '\n';
                $qrcodecontent .= "VERSION:2.0" . '\n';
                $qrcodecontent .= "BEGIN:VEVENT" . '\n';
                $qrcodecontent .= "SUMMARY:" . $this->config->event_summary . '\n';
                $qrcodecontent .= "LOCATION:" . $this->config->event_location . '\n';
                switch ($this->config->allday) {
                    case '0':
                        $dateformat = get_string('strftimedate', 'block_qr');
                        $timeformat = get_string('strftimedatetime', 'block_qr');
                        $qrcodecontent .= "DTSTART:" . date('Ymd\THis', $this->config->event_start) . '\n';
                        $qrcodecontent .= "DTEND:" . date('Ymd\THis', $this->config->event_end) . '\n';
                        if (date('ymd', $this->config->event_end) != date('ymd', $this->config->event_start)) {
                            $calendarstart = date($dateformat, $this->config->event_start) . " - ";
                            $calendarend = date($dateformat, $this->config->event_end);
                        } else {
                            $calendarstart = date($dateformat, $this->config->event_start) . " - ";
                            $calendarend = date($timeformat, $this->config->event_end);
                        }
                        break;
                    case '1':
                        $dateformat = get_string('strftimedateallday', 'block_qr');
                        $timeformat = get_string('strftimedatetime', 'block_qr');
                        $qrcodecontent .= "DTSTART:" . date('Ymd', $this->config->event_start) . '\n';
                        $qrcodecontent .= "DTEND:" . date('Ymd', $this->config->event_end) . '\n';
                        if (date('ymd', $this->config->event_end) != date('ymd', $this->config->event_start)) {
                            $calendarstart = date($dateformat, $this->config->event_start) . " - ";
                            $calendarend = date($dateformat, $this->config->event_end);
                        } else {
                            $calendarstart = date($dateformat, $this->config->event_start);
                            $calendarend = null;
                        }
                }
                $qrcodecontent .= "END:VEVENT" . '\n';
                $qrcodecontent .= "END:VCALENDAR" . '\n';
                $configcontent = get_string('event', 'block_qr');
                $calendarsummary = $this->config->event_summary;
                $calendarlocation = $this->config->event_location;
                $qrurl = false;
                $calendar = true;
                $fullview = false;
                break;

            case 'geolocation':
                $qrcodecontent = "geo:" . $this->config->geolocation_br . "," . $this->config->geolocation_lng;
                $configcontent = get_string('geolocation', 'block_qr');
                $geocoordinates = $this->config->geolocation_br . ', ' . $this->config->geolocation_lng;
                $calendar = false;
                switch ($this->config->link) {
                    case 'nolink':
                        $qrurl = false;
                        break;
                    case 'osm':
                        $qrcodelink = 'https://www.openstreetmap.org/?mlat=';
                        $qrcodelink .= $this->config->geolocation_br . '&mlon=' . $this->config->geolocation_lng;
                        $qrcodelink .= '#map=10/' . $this->config->geolocation_br . '/' . $this->config->geolocation_lng;
                        $qrurl = true;
                        break;
                }
        }

        // Short link option only in edit mode.
        if ($USER->editing == 0) {
            $fullview = false;
        } else {
            $fullview = true;
        }

        $urlshort = urlencode($qrcodelink);

        if (isset($this->config->size)) {
            $svgsize = $this->config->size;
        }

        // Use for multiple id for multiple QR codes on one page.
        $blockid = $this->context->id;

        $javascripturl = $CFG->wwwroot . '/blocks/qr/js/qrcode.min.js';

        $data = [
            'qrcodecontent' => $qrcodecontent,
            'message' => $configcontent,
            'javascript' => $javascripturl,
            'size' => $svgsize,
            'id' => $blockid,
            'geocoordinates' => $geocoordinates,
            'qrurl' => $qrurl,
            'calendar' => $calendar,
            'calendarsummary' => $calendarsummary,
            'calendarlocation' => $calendarlocation,
            'calendarstart' => $calendarstart,
            'calendarend' => $calendarend,
            'qrcodelink' => $qrcodelink,
            'urlshort' => $urlshort,
            'fullview' => $fullview
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
