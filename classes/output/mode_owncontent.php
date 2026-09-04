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

namespace block_qr\output;

/**
 * Renders a QR code from user-defined content (plain text or URL).
 *
 * @package     block_qr
 * @copyright   2025 ISB Bayern
 * @author      Thomas Schönlein
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mode_owncontent implements \renderable, \templatable {
    /**
     * Constructor.
     */
    public function __construct(
        /** @var string $raw The raw content of the QR code */
        private string $raw,
    ) {
        $this->raw = trim($raw);
    }

    #[\Override]
    public function export_for_template($output): array {
        $isurl = filter_var($this->raw, FILTER_VALIDATE_URL) !== false;
        return [
            'description' => $this->raw,
            'qrurl' => $isurl,
            'qrcodecontent' => $this->raw,
            'qrcodelink' => $isurl ? $this->raw : null,
        ];
    }
}
