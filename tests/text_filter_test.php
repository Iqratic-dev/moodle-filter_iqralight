<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace filter_iqralight;

use core\context\module as context_module;

/**
 * Tests of the "tap to load" filter.
 *
 * @package    filter_iqralight
 * @category   test
 * @copyright  2026 IQRATIC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_iqralight\text_filter
 */
final class text_filter_test extends \advanced_testcase {
    /** @var string A YouTube player. */
    public const YOUTUBE = '<iframe src="https://www.youtube.com/embed/abc123"></iframe>';

    /**
     * The filter for a new page of a new course.
     *
     * @return array [filter, module context]
     */
    protected function page_filter(): array {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = context_module::instance($page->cmid);
        return [new text_filter($context, []), $context];
    }

    /**
     * Players of known platforms wait for a tap; anything else is left untouched.
     */
    public function test_players(): void {
        $this->resetAfterTest();
        [$filter] = $this->page_filter();

        $output = $filter->filter(self::YOUTUBE);
        $this->assertStringContainsString('data-filter-iqra="video"', $output);
        $this->assertStringContainsString('data-iqra-src="https://www.youtube.com/embed/abc123"', $output);
        $this->assertStringContainsString(get_string('opendirect', 'filter_iqralight'), $output);

        $unknown = '<iframe src="https://tool.example.org/quiz"></iframe>';
        $this->assertSame($unknown, $filter->filter($unknown));

        $plain = '<p>Text with <a href="x">a link</a> &amp; characters é ü ع.</p>';
        $this->assertSame($plain, $filter->filter($plain));

        $once = $filter->filter(self::YOUTUBE);
        $this->assertSame($once, $filter->filter($once), 'Filtering twice changes nothing.');
    }

    /**
     * Videos and sounds hosted in Moodle download nothing before the learner presses play.
     */
    public function test_native_media(): void {
        $this->resetAfterTest();
        [$filter] = $this->page_filter();
        $output = $filter->filter('<video src="clip.mp4" autoplay controls preload="auto"></video>');
        $this->assertStringContainsString('preload="none"', $output);
        $this->assertStringNotContainsString('autoplay', $output);
        $this->assertStringNotContainsString('preload="auto"', $output);
    }

    /**
     * Heavy images stored in Moodle wait for a tap, with their size; light ones are only marked.
     */
    public function test_images(): void {
        $this->resetAfterTest();
        set_config('threshold', 300, 'filter_iqralight');
        [$filter, $context] = $this->page_filter();
        $fs = get_file_storage();
        $record = ['contextid' => $context->id, 'component' => 'mod_page', 'filearea' => 'content', 'itemid' => 0,
            'filepath' => '/'];
        $fs->create_file_from_string($record + ['filename' => 'big.jpg'], str_repeat('x', 500 * 1024));
        $fs->create_file_from_string($record + ['filename' => 'small.png'], str_repeat('x', 10 * 1024));
        $url = fn($name) => \moodle_url::make_pluginfile_url($context->id, 'mod_page', 'content', 0, '/', $name)->out(false);

        $output = $filter->filter('<img src="' . $url('big.jpg') . '" alt="Market">');
        $this->assertStringContainsString('data-filter-iqra="image"', $output);
        $this->assertStringContainsString('data-iqra-bytes="' . (500 * 1024) . '"', $output);
        $this->assertStringContainsString(display_size(500 * 1024), $output);

        $output = $filter->filter('<img src="' . $url('small.png') . '" alt="Icon">');
        $this->assertStringNotContainsString('data-filter-iqra', $output);
        $this->assertStringContainsString('data-iqra-small="1"', $output);
    }

    /**
     * Quizzes are never filtered, nor activity types excluded by the administrator.
     */
    public function test_exclusions(): void {
        $this->resetAfterTest();
        set_config('excludedmodules', '', 'filter_iqralight');
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $filter = new text_filter(context_module::instance($quiz->cmid), []);
        $this->assertSame(self::YOUTUBE, $filter->filter(self::YOUTUBE));

        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $filter = new text_filter(context_module::instance($forum->cmid), []);
        $this->assertNotSame(self::YOUTUBE, $filter->filter(self::YOUTUBE));

        set_config('excludedmodules', 'forum', 'filter_iqralight');
        $filter = new text_filter(context_module::instance($forum->cmid), []);
        $this->assertSame(self::YOUTUBE, $filter->filter(self::YOUTUBE));
    }
}
