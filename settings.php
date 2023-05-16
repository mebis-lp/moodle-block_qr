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
 * Admin settings for block_qr
 *
 * @package    block_qr
 * @copyright  2023 ISB Bayern
 * @author     Florian Dagner <florian.dagner@outlook.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'block_qr/shortlinkservice',
        new lang_string('shortlinkservice', 'block_qr'),
        new lang_string('shortlinkservice_description', 'block_qr'),
        '',
        PARAM_TEXT)
    );
    $settings->add(new admin_setting_configtext(
        'block_qr/urlparameterbefore',
        new lang_string('urlparameterbefore', 'block_qr'),
        '',
        '',
        PARAM_TEXT)
    );
    $settings->add(new admin_setting_configtext(
        'block_qr/urlparameterafter',
        get_string('urlparameterafter', 'block_qr'),
        new lang_string('shortlinkparameter_description', 'block_qr'),
        '',
        PARAM_TEXT)
    );
}
