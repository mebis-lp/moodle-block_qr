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
 * Block QR functions unit tests
 *
 * @package     block_qr
 * @copyright   2025 ISB Bayern
 * @author      Thomas Schönlein
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class qr_test extends \advanced_testcase {
    /** @var \stdClass $course */
    private $course;
    /** @var int $sectionid */
    private $sectionid;
    /** @var int $sectionnum */
    private $sectionnum;
    /** @var int $secondsectionid */
    private $secondsectionid;
    /** @var int $cmid */
    private $cmid;
    /** @var \stdClass $student */
    private $student;
    /** @var \stdClass $teacher */
    private $teacher;

    /**
     * Setup test environment
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest(true);
        date_default_timezone_set('UTC');

        $gen = self::getDataGenerator();
        $this->course = $gen->create_course(['format' => 'topics']);

        require_once($CFG->dirroot . '/lib/blocklib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/blocks/qr/db/upgradelib.php');

        $modinfo = get_fast_modinfo($this->course->id);
        $sectioninfo = $modinfo->get_section_info(1);

        course_update_section($this->course, $sectioninfo, ['name' => 'Testabschnitt 1']);
        rebuild_course_cache($this->course->id, true);

        $modinfo = get_fast_modinfo($this->course->id);
        $secinfo = $modinfo->get_section_info_by_id($sectioninfo->id);
        $this->sectionid = $secinfo->id;
        $this->sectionnum = $secinfo->section;
        $page = $gen->create_module('page', [
            'course' => $this->course->id,
            'section' => 1,
            'name' => 'Testseite',
        ]);
        $this->cmid = $page->cmid;

        $secondsection = course_create_section($this->course, 0);
        $this->secondsectionid = $secondsection->id;

        $this->teacher = $gen->create_user();
        $gen->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');

        $this->student = $gen->create_user();
        $gen->enrol_user($this->student->id, $this->course->id, 'student');
    }

    /**
     * Tests the QR-Block content output for the provided data set.
     *
     * @param string $mode QR block mode
     * @param array $config QR block data
     * @param \moodle_url $pageurl Page URL
     * @param array $expect Expected output
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('get_content_provider')]
    public function test_get_content(string $mode, array $config, \moodle_url $pageurl, array $expect): void {
        global $PAGE;

        $PAGE->set_course($this->course);
        $PAGE->set_url($pageurl);

        // The data provider uses symbolic placeholders (e.g. __CMID__) instead of real IDs,
        // because the actual IDs are only known after setUp() runs. Replace them here.
        $replacements = [
            '__COURSEID__' => (string)$this->course->id,
            '__SECTIONID__' => (string)$this->sectionid,
            '__CMID__' => (string)$this->cmid,
        ];

        foreach ($config as $key => $value) {
            if (is_string($value)) {
                $config[$key] = strtr($value, $replacements);
            }
        }

        if (!array_key_exists('internal', $config) || !is_string($config['internal'])) {
            $config['internal'] = '';
        } else {
            $config['internal'] = strtr($config['internal'], $replacements);
        }

        if (!empty($expect['contains']) && is_array($expect['contains'])) {
            foreach ($expect['contains'] as $index => $value) {
                if (is_string($value)) {
                    $expect['contains'][$index] = strtr($value, $replacements);
                }
            }
        }

        $config['options'] = $mode;
        $block = $this->create_block_with_config($config, \context_system::instance());

        $content = $block->get_content();

        $this->assertNotEmpty($content->text, 'HTML should not be empty');

        foreach ($expect['contains'] as $test) {
            $this->assertStringContainsString($test, $content->text);
        }
    }

    /**
     * dataProvider for test_get_content
     * @return array Sets of data for test_get_content
     */
    public static function get_content_provider(): array {
        $urlcourse = new \moodle_url('/course/view.php');
        $urlmycourses = new \moodle_url('/my/courses.php');

        $cases = [
            'currenturl' => [
                'mode' => 'currenturl',
                'config' => [],
                'pageurl' => $urlmycourses,
                'expect' => [
                    'contains' => [
                        $urlmycourses->out(false),
                        get_string('thisurl', 'block_qr'),
                    ],
                ],
            ],
            'courseurl' => [
                'mode' => 'courseurl',
                'config' => ['courseid' => '__COURSEID__'],
                'pageurl' => $urlcourse,
                'expect' => [
                    'contains' => [
                        (new \moodle_url('/course/view.php', ['id' => '__COURSEID__']))->out(false),
                    ],
                ],
            ],
            'owncontent_url' => [
                'mode' => 'owncontent',
                'config' => ['owncontent' => 'https://example.com/abc'],
                'pageurl' => $urlmycourses,
                'expect' => [
                    'contains' => ['https://example.com/abc'],
                ],
            ],
            'owncontent_text' => [
                'mode' => 'owncontent',
                'config' => ['owncontent' => 'nur Text'],
                'pageurl' => $urlmycourses,
                'expect' => [
                    'contains' => ['Show in full screen'],
                ],
            ],
            'event' => [
                'mode' => 'event',
                'config' => [
                    'event_summary' => 'Hackathon',
                    'event_location' => 'HS06',
                    'event_start' => gmmktime(10, 0, 0, 10, 15, 2025),
                    'event_end' => gmmktime(12, 0, 0, 10, 17, 2025),
                    'allday' => 0,
                ],
                'pageurl' => $urlmycourses,
                'expect' => [
                    'contains' => ['Hackathon', 'HS06'],
                ],
            ],
            'geolocation_osm' => [
                'mode' => 'geolocation',
                'config' => [
                    'geolocation_br' => '48.137',
                    'geolocation_lng' => '11.575',
                    'link' => 'osm',
                ],
                'pageurl' => $urlmycourses,
                'expect' => [
                    'contains' => ['openstreetmap.org', '48.137', '11.575'],
                ],
            ],
            'wifi' => [
                'mode' => 'wifi',
                'config' => [
                    'wifissid' => 'SchoolNet',
                    'wifipasskey' => 'secret',
                    'wifiauthentication' => 'WPA',
                    'wifissidoptions' => 'false',
                ],
                'pageurl' => $urlmycourses,
                'expect' => [
                    'contains' => ['SchoolNet', 'WPA', 'secret'],
                ],
            ],
        ];

        foreach ($cases as $name => $case) {
            $cases[$name] = [
                $case['mode'],
                $case['config'],
                $case['pageurl'],
                $case['expect'],
            ];
        }

        return $cases;
    }

    /**
     * Exercises the internal content handling using the supplied scenario.
     *
     * Tests that the QR block renders the correct output (visible link or error message)
     * after performing an action (move, hide, delete) on a cmid or section target,
     * depending on whether the current user has editing capabilities.
     *
     * @param string $mode Target type (cmid or section)
     * @param string $action Action performed (none, move, hide, delete)
     * @param bool $usercanedit Whether user has editing capabilities
     * @param bool $expectvisible Whether the QR link should be visible
     * @param ?string $errorexpected Expected error string identifier (null if visible)
     * @param ?string $expectdescription Expected description placeholder/value (null if error)
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('internalcontent_provider')]
    public function test_get_content_internal(
        string $mode,
        string $action,
        bool $usercanedit,
        bool $expectvisible,
        ?string $errorexpected,
        ?string $expectdescription
    ): void {
        global $DB, $PAGE, $USER;

        $coursecontext = \context_course::instance($this->course->id);

        $PAGE->set_course($this->course);
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $this->course->id]));
        $PAGE->set_context($coursecontext);

        $this->setUser($this->teacher);
        $USER->editing = 1;

        // Perform the action on the target (cmid or section) before rendering the block.
        // Possible actions: 'none' (no change), 'move' (to another section), 'hide', 'delete'.
        if ($mode === 'cmid') {
            $cmactions = new \core_courseformat\local\cmactions($this->course);
            if ($action === 'move') {
                $cmactions->move_end_section($this->cmid, $this->secondsectionid);
            } else if ($action === 'hide') {
                $cmactions->set_visibility($this->cmid, 0);
            } else if ($action === 'delete') {
                $cmactions->delete($this->cmid);
            }
        } else {
            $sectionactions = new \core_courseformat\local\sectionactions($this->course);
            $sections = get_fast_modinfo($this->course)->get_section_info_all();
            if ($action === 'move') {
                $sectionactions->move_after($sections[$this->sectionnum], $sections[$this->sectionnum + 1]);
                $this->sectionnum = $this->sectionnum + 1;
            } else if ($action === 'hide') {
                $sectionactions->set_visibility($sections[$this->sectionnum], 0);
            } else if ($action === 'delete') {
                $sectionactions->delete($sections[$this->sectionnum]);
            }
        }

        rebuild_course_cache($this->course->id, true);

        if ($usercanedit) {
            $this->setUser($this->teacher);
            $USER->editing = 1;
        } else {
            $this->setUser($this->student);
            $USER->editing = 0;
        }

        $internalvalue = '';
        if ($mode === 'cmid') {
            $internalvalue = 'cmid=' . $this->cmid;
        } else {
            $internalvalue = 'section=' . $this->sectionid;
        }

        $blockconfig = [
            'options' => 'internalcontent',
            'internal' => $internalvalue,
        ];

        $block = $this->create_block_with_config($blockconfig);

        $content = $block->get_content();

        $sectionnameexpected = null;
        if ($expectdescription === '__MODULENAME__') {
            $modinfo = get_fast_modinfo($this->course->id);
            $cm = $modinfo->get_cm($this->cmid);
            $expectdescription = $cm->name;
        } else if ($expectdescription === '__SECTIONNAME__') {
            $modinfo = get_fast_modinfo($this->course->id);
            $sectioninfo = $modinfo->get_section_info_by_id($this->sectionid);
            if ($sectioninfo) {
                $sectionnameexpected = get_section_name($this->course, $sectioninfo->section);
            }
        }

        if ($expectvisible) {
            $expectedurl = null;

            if ($mode === 'cmid') {
                $modinfo = get_fast_modinfo($this->course->id);
                $cm = $modinfo->get_cm($this->cmid);

                if ($cm->url) {
                    $expectedurl = $cm->url->out(false);
                } else {
                    $url = new \moodle_url('/course/view.php', ['id' => $this->course->id]);
                    $url->set_anchor('module-' . $this->cmid);
                    $expectedurl = $url->out(false);
                }

                $this->assertNotNull($expectedurl, 'Expected URL should be available.');
                $this->assertStringContainsString($expectedurl, $content->text);
                $this->assertStringContainsString($cm->name, $content->text);

                if (!empty($expectdescription)) {
                    $this->assertStringContainsString($expectdescription, $content->text);
                }
            } else {
                $modinfo = get_fast_modinfo($this->course->id);
                $sectioninfo = $modinfo->get_section_info_by_id($this->sectionid);

                if ($sectioninfo) {
                    $sectionnameexpected = get_section_name($this->course, $sectioninfo->section);
                    $format = course_get_format($sectioninfo->course);
                    $url = $format->get_view_url($sectioninfo, ['navigation' => true]);
                    if ($url) {
                        $expectedurl = $url->out(false);
                    }
                }

                $this->assertNotNull($expectedurl, 'Expected URL should be available.');
                $this->assertStringContainsString($expectedurl, $content->text);
                $this->assertNotNull($sectionnameexpected, 'Section name should be resolved.');

                if (!empty($sectionnameexpected)) {
                    $this->assertStringContainsString($sectionnameexpected, $content->text);
                }
            }
        } else {
            $this->assertNotNull($errorexpected, 'Error string identifier expected for invisible case.');
            $this->assertStringContainsString(get_string($errorexpected, 'block_qr'), $content->text);
        }
    }

    /**
     * Data provider for internal content scenarios.
     *
     * @return array
     */
    public static function internalcontent_provider(): array {
        return [
            'cmid_visible_when_untouched_with_editing' => [
                // Module exists and is visible; editor sees the QR link with module name.
                'mode' => 'cmid',
                'action' => 'none',
                'usercanedit' => true,
                'expectvisible' => true,
                'errorexpected' => null,
                'expectdescription' => '__MODULENAME__',
            ],
            'cmid_visible_when_untouched_without_editing' => [
                // Module exists and is visible; viewer also sees the QR link.
                'mode' => 'cmid',
                'action' => 'none',
                'usercanedit' => false,
                'expectvisible' => true,
                'errorexpected' => null,
                'expectdescription' => '__MODULENAME__',
            ],
            'cmid_visible_after_move_with_editing' => [
                // Module moved to another section; editor still sees the QR link.
                'mode' => 'cmid',
                'action' => 'move',
                'usercanedit' => true,
                'expectvisible' => true,
                'errorexpected' => null,
                'expectdescription' => '__MODULENAME__',
            ],
            'cmid_visible_after_move_without_editing' => [
                // Module moved to another section; viewer still sees the QR link.
                'mode' => 'cmid',
                'action' => 'move',
                'usercanedit' => false,
                'expectvisible' => true,
                'errorexpected' => null,
                'expectdescription' => '__MODULENAME__',
            ],
            'cmid_visible_when_hidden_with_editing' => [
                // Module is hidden; editor can still see hidden modules, so QR link is shown.
                'mode' => 'cmid',
                'action' => 'hide',
                'usercanedit' => true,
                'expectvisible' => true,
                'errorexpected' => null,
                'expectdescription' => '__MODULENAME__',
            ],
            'cmid_error_when_hidden_without_editing' => [
                // Module is hidden; viewer cannot see hidden modules, so error message is shown.
                'mode' => 'cmid',
                'action' => 'hide',
                'usercanedit' => false,
                'expectvisible' => false,
                'errorexpected' => 'errormodulenotavailable',
                'expectdescription' => null,
            ],
            'cmid_error_when_deleted_with_editing' => [
                // Module is deleted; even editors see the error message.
                'mode' => 'cmid',
                'action' => 'delete',
                'usercanedit' => true,
                'expectvisible' => false,
                'errorexpected' => 'errormodulenotavailable',
                'expectdescription' => null,
            ],
            'cmid_error_when_deleted_without_editing' => [
                // Module is deleted; viewers see the error message.
                'mode' => 'cmid',
                'action' => 'delete',
                'usercanedit' => false,
                'expectvisible' => false,
                'errorexpected' => 'errormodulenotavailable',
                'expectdescription' => null,
            ],
            'section_visible_when_untouched_with_editing' => [
                // Section exists and is visible; editor sees the QR link with section name.
                'mode' => 'section',
                'action' => 'none',
                'usercanedit' => true,
                'expectvisible' => true,
                'errorexpected' => null,
                'expectdescription' => '__SECTIONNAME__',
            ],
            'section_visible_when_untouched_without_editing' => [
                // Section exists and is visible; viewer also sees the QR link.
                'mode' => 'section',
                'action' => 'none',
                'usercanedit' => false,
                'expectvisible' => true,
                'errorexpected' => null,
                'expectdescription' => '__SECTIONNAME__',
            ],
            'section_visible_after_move_with_editing' => [
                // Section moved to a different position; editor still sees the QR link.
                'mode' => 'section',
                'action' => 'move',
                'usercanedit' => true,
                'expectvisible' => true,
                'errorexpected' => null,
                'expectdescription' => '__SECTIONNAME__',
            ],
            'section_visible_after_move_without_editing' => [
                // Section moved to a different position; viewer still sees the QR link.
                'mode' => 'section',
                'action' => 'move',
                'usercanedit' => false,
                'expectvisible' => true,
                'errorexpected' => null,
                'expectdescription' => '__SECTIONNAME__',
            ],
            'section_visible_when_hidden_with_editing' => [
                // Section is hidden; editor can still see hidden sections, so QR link is shown.
                'mode' => 'section',
                'action' => 'hide',
                'usercanedit' => true,
                'expectvisible' => true,
                'errorexpected' => null,
                'expectdescription' => '__SECTIONNAME__',
            ],
            'section_error_when_hidden_without_editing' => [
                // Section is hidden; viewer cannot see hidden sections, so error message is shown.
                'mode' => 'section',
                'action' => 'hide',
                'usercanedit' => false,
                'expectvisible' => false,
                'errorexpected' => 'errorsectionnotavailable',
                'expectdescription' => null,
            ],
            'section_error_when_deleted_with_editing' => [
                // Section is deleted; even editors see the error message.
                'mode' => 'section',
                'action' => 'delete',
                'usercanedit' => true,
                'expectvisible' => false,
                'errorexpected' => 'errorsectionnotavailable',
                'expectdescription' => null,
            ],
            'section_error_when_deleted_without_editing' => [
                // Section is deleted; viewers see the error message.
                'mode' => 'section',
                'action' => 'delete',
                'usercanedit' => false,
                'expectvisible' => false,
                'errorexpected' => 'errorsectionnotavailable',
                'expectdescription' => null,
            ],
        ];
    }

    /**
     * Tests migration of legacy section-number config for a course-context block.
     */
    public function test_migrate_section_num_to_id_for_course_context(): void {
        $blockid = $this->create_persisted_block_instance(
            \context_course::instance($this->course->id),
            [
                'options' => 'internalcontent',
                'internal' => 'section=' . $this->sectionnum,
            ]
        );

        $this->assertSame(1, block_qr_migrate_section_num_to_id());

        $config = $this->load_persisted_block_config($blockid);
        $this->assertSame('section=' . $this->sectionid, $config->internal);
    }

    /**
     * Tests migration of legacy section-number config for a module-context block.
     */
    public function test_migrate_section_num_to_id_for_module_context(): void {
        $blockid = $this->create_persisted_block_instance(
            \context_module::instance($this->cmid),
            [
                'options' => 'internalcontent',
                'internal' => 'section=' . $this->sectionnum,
            ]
        );

        $this->assertSame(1, block_qr_migrate_section_num_to_id());

        $config = $this->load_persisted_block_config($blockid);
        $this->assertSame('section=' . $this->sectionid, $config->internal);
    }

    /**
     * Tests migration leaves already migrated section ids untouched.
     */
    public function test_migrate_section_num_to_id_skips_already_migrated_config(): void {
        $blockid = $this->create_persisted_block_instance(
            \context_course::instance($this->course->id),
            [
                'options' => 'internalcontent',
                'internal' => 'section=' . $this->sectionid,
            ]
        );

        $this->assertSame(0, block_qr_migrate_section_num_to_id());

        $config = $this->load_persisted_block_config($blockid);
        $this->assertSame('section=' . $this->sectionid, $config->internal);
    }

    /**
     * Tests migration skips unknown legacy section numbers.
     */
    public function test_migrate_section_num_to_id_skips_unknown_section(): void {
        $blockid = $this->create_persisted_block_instance(
            \context_course::instance($this->course->id),
            [
                'options' => 'internalcontent',
                'internal' => 'section=99',
            ]
        );

        $this->assertSame(0, block_qr_migrate_section_num_to_id());

        $config = $this->load_persisted_block_config($blockid);
        $this->assertSame('section=99', $config->internal);
    }

    /**
     * Tests migration skips blocks that are not using internalcontent mode.
     */
    public function test_migrate_section_num_to_id_skips_non_internalcontent_block(): void {
        $blockid = $this->create_persisted_block_instance(
            \context_course::instance($this->course->id),
            [
                'options' => 'currenturl',
                'internal' => 'section=' . $this->sectionnum,
            ]
        );

        $this->assertSame(0, block_qr_migrate_section_num_to_id());

        $config = $this->load_persisted_block_config($blockid);
        $this->assertSame('section=' . $this->sectionnum, $config->internal);
    }

    /**
     * Tests migration skips blocks whose parent context no longer exists.
     */
    public function test_migrate_section_num_to_id_skips_orphaned_block(): void {
        global $DB;

        $blockid = $this->create_persisted_block_instance(
            \context_course::instance($this->course->id),
            [
                'options' => 'internalcontent',
                'internal' => 'section=' . $this->sectionnum,
            ]
        );

        // Break the parent context so the block becomes an orphan.
        $DB->set_field('block_instances', 'parentcontextid', 999999, ['id' => $blockid]);

        $this->assertSame(0, block_qr_migrate_section_num_to_id());
    }

    /**
     * Creates a QR block instance with the provided config.
     *
     * @param array $config Configuration data to apply
     * @param \context|null $context Optional context for the block
     * @return \block_qr
     */
    private function create_block_with_config(array $config, ?\context $context = null): \block_qr {
        global $PAGE;

        $block = block_instance('qr');
        if ($context === null) {
            if (!empty($PAGE->context)) {
                $context = $PAGE->context;
            } else {
                $context = \context_course::instance($this->course->id);
            }
        }

        $block->instance = (object)[
            'id' => 1,
            'blockname' => 'qr',
            'parentcontextid' => $context->id,
        ];
        $block->config = (object)$config;
        $block->page = $PAGE;
        $block->context = $context;
        return $block;
    }

    /**
     * Creates a persisted block instance for migration tests.
     *
     * @param \context $parentcontext Parent context of the block
     * @param array $config Configuration data
     * @return int
     */
    private function create_persisted_block_instance(\context $parentcontext, array $config): int {
        global $DB, $PAGE;

        $PAGE->set_course($this->course);
        $PAGE->set_context($parentcontext);
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $this->course->id]));

        $record = self::getDataGenerator()->create_block('qr', ['parentcontextid' => $parentcontext->id]);
        $block = block_instance('qr', $record, $PAGE);
        $block->instance_config_save((object) $config);

        return (int) $DB->get_field('block_instances', 'id', ['id' => $record->id], MUST_EXIST);
    }

    /**
     * Loads a persisted block config through the Moodle block API.
     *
     * @param int $blockid Block instance id
     * @return \stdClass
     */
    private function load_persisted_block_config(int $blockid): \stdClass {
        global $DB, $PAGE;

        $record = $DB->get_record('block_instances', ['id' => $blockid], '*', MUST_EXIST);
        $block = block_instance('qr', $record, $PAGE);

        return $block->config;
    }
}
