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

namespace core_courseformat\output\local\overview;

/**
 * Tests for courseformat
 *
 * @package    core_courseformat
 * @category   test
 * @copyright  2025 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_courseformat\output\local\overview\missingoverviewnotice
 */
final class missingoverviewnotice_test extends \advanced_testcase {
    /**
     * Test overview integrations.
     *
     * @covers ::export_for_template
     * @dataProvider overview_integrations_provider
     * @param string $modname
     * @param bool $expectempty
     */
    public function test_overview_integrations(
        string $modname,
        bool $expectempty,
    ): void {
        global $PAGE;

        $this->resetAfterTest();

        $renderer = $PAGE->get_renderer('core');
        $course = $this->getDataGenerator()->create_course();

        $missingoverviewnotice = new missingoverviewnotice($course, $modname);
        $export = $missingoverviewnotice->export_for_template($renderer);

        if ($expectempty) {
            $this->assertEquals((object) [], $export);
        } else {
            $this->assertNotEquals((object) [], $export);
        }
    }

    /**
     * Data provider for test_overview_integrations.
     *
     * @return array
     */
    public static function overview_integrations_provider(): array {
        return [
            'assign' => ['modname' => 'assign', 'expectempty' => true],
            'bigbluebuttonbn' => ['modname' => 'bigbluebuttonbn', 'expectempty' => false],
            'book' => ['modname' => 'book', 'expectempty' => false],
            'choice' => ['modname' => 'choice', 'expectempty' => true],
            'data' => ['modname' => 'data', 'expectempty' => true],
            'feedback' => ['modname' => 'feedback', 'expectempty' => true],
            'folder' => ['modname' => 'folder', 'expectempty' => false],
            'forum' => ['modname' => 'forum', 'expectempty' => true],
            'glossary' => ['modname' => 'glossary', 'expectempty' => true],
            'h5pactivity' => ['modname' => 'h5pactivity', 'expectempty' => true],
            'imscp' => ['modname' => 'imscp', 'expectempty' => false],
            'label' => ['modname' => 'label', 'expectempty' => false],
            'lesson' => ['modname' => 'lesson', 'expectempty' => true],
            'lti' => ['modname' => 'lti', 'expectempty' => false],
            'page' => ['modname' => 'page', 'expectempty' => false],
            'qbank' => ['modname' => 'qbank', 'expectempty' => false],
            'quiz' => ['modname' => 'quiz', 'expectempty' => false],
            'resource' => ['modname' => 'resource', 'expectempty' => true],
            'scorm' => ['modname' => 'scorm', 'expectempty' => false],
            'url' => ['modname' => 'url', 'expectempty' => false],
            'wiki' => ['modname' => 'wiki', 'expectempty' => true],
            'workshop' => ['modname' => 'workshop', 'expectempty' => true],
        ];
    }
}
