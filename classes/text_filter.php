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

use core\context;
use core\context\module as context_module;

/**
 * "Tap to load" filter: heavy media wait for the learner's consent.
 *
 * The filter always produces the same HTML whatever the learner's connection
 * mode, so Moodle's text cache stays valid for everyone; the decision to load
 * or wait is taken in the browser by the filter_iqralight/media module.
 *
 * Prudence rules:
 * - only elements of a short allow-list are touched; anything else, and any
 *   doubt, leaves the text byte-for-byte unchanged;
 * - videos and sounds hosted in Moodle are not moved, they only get
 *   preload="none": nothing is downloaded before the learner presses play;
 * - quizzes, question banks, H5P and virtual classrooms are always excluded;
 * - without JavaScript, each placeholder keeps a direct link to the media.
 *
 * @package    filter_iqralight
 * @copyright  2026 IQRATIC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \core_filters\text_filter {
    /** @var string[] Activities never filtered: evaluation and rich interactive players. */
    public const ALWAYS_EXCLUDED = ['quiz', 'qbank', 'h5pactivity', 'hvp', 'bigbluebuttonbn'];

    /** @var string[] Video platforms whose embedded players may be deferred. */
    public const VIDEO_HOSTS = [
        'youtube.com', 'youtube-nocookie.com', 'youtu.be', 'vimeo.com', 'dailymotion.com',
        'kaltura.com', 'panopto.com', 'panopto.eu', 'ted.com', 'wistia.com', 'wistia.net',
    ];

    /** @var int Default threshold in KB above which an image waits for a tap. */
    public const DEFAULT_THRESHOLD = 300;

    /** @var string A video or sound player built by Moodle's Video.js player, with its optional width wrapper. */
    public const VIDEOJS_PLAYER = '~<div class="mediaplugin mediaplugin_videojs[^"]*">\s*(<div style="max-width:[^"]*">)?\s*' .
        '<(video|audio)\b.*?</\2>\s*(?(1)</div>\s*)</div>~is';

    /** @var array File sizes already looked up in this request, by URL. */
    protected static $sizes = [];

    /**
     * Loads the browser module once per page.
     *
     * @param \moodle_page $page
     * @param context $context
     */
    public function setup($page, $context) {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $page->requires->js_call_amd('filter_iqralight/media', 'init', [[
            'showimage' => get_string('showimage', 'filter_iqralight'),
            'loadfailed' => get_string('loadfailed', 'filter_iqralight'),
        ]]);
    }

    /**
     * Filters a text.
     *
     * @param string $text
     * @param array $options
     * @return string
     */
    public function filter($text, array $options = []) {
        if (!is_string($text) || !preg_match('~<(img|iframe|object|embed|video|audio)\b~i', $text)) {
            return $text;
        }
        if ($this->is_excluded()) {
            return $text;
        }
        $text = preg_replace_callback(self::VIDEOJS_PLAYER, [$this, 'defer_player'], $text);
        $text = preg_replace_callback('~<(video|audio)\b[^>]*>~i', [$this, 'defer_native_media'], $text);
        $text = preg_replace_callback('~<iframe\b[^>]*>.*?</iframe>~is', [$this, 'defer_iframe'], $text);
        $text = preg_replace_callback('~<object\b[^>]*>.*?</object>~is', [$this, 'defer_document'], $text);
        $text = preg_replace_callback('~<embed\b[^>]*>~i', [$this, 'defer_document'], $text);
        $text = preg_replace_callback('~<a\b[^>]*>\s*<img\b[^>]*>\s*</a>~i', [$this, 'defer_linked_image'], $text);
        $text = preg_replace_callback('~<img\b[^>]*>~i', [$this, 'defer_image'], $text);
        return $text;
    }

    /**
     * Whether the current context must never be filtered.
     *
     * @return bool
     */
    protected function is_excluded(): bool {
        global $PAGE;
        $pagetype = (string) ($PAGE->pagetype ?? '');
        if (strpos($pagetype, 'question-') === 0 || strpos($pagetype, 'mod-quiz-') === 0) {
            return true;
        }
        $excluded = self::ALWAYS_EXCLUDED;
        $configured = (string) get_config('filter_iqralight', 'excludedmodules');
        if ($configured !== '') {
            $excluded = array_merge($excluded, explode(',', $configured));
        }
        foreach ($this->context->get_parent_context_ids(true) as $contextid) {
            $context = context::instance_by_id($contextid, IGNORE_MISSING);
            if ($context instanceof context_module) {
                $modname = $this->module_name($context->instanceid);
                return $modname === null || in_array($modname, $excluded, true);
            }
        }
        return false;
    }

    /**
     * Name of the module of a course module, or null when unknown.
     *
     * @param int $cmid
     * @return string|null
     */
    protected function module_name(int $cmid): ?string {
        global $DB;
        static $names = [];
        if (!array_key_exists($cmid, $names)) {
            $names[$cmid] = $DB->get_field_sql(
                'SELECT m.name FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module WHERE cm.id = ?',
                [$cmid]
            ) ?: null;
        }
        return $names[$cmid];
    }

    /**
     * Videos and sounds: nothing is downloaded before the learner presses play.
     *
     * @param array $match
     * @return string
     */
    protected function defer_native_media(array $match): string {
        $tag = $match[0];
        $tag = preg_replace('~\s(autoplay)(=("[^"]*"|\'[^\']*\'|\S+))?(?=[\s/>])~i', '', $tag);
        if (preg_match('~\spreload=~i', $tag)) {
            return preg_replace('~\spreload=("[^"]*"|\'[^\']*\'|[^\s>]+)~i', ' preload="none"', $tag);
        }
        return preg_replace('~^<(video|audio)\b~i', '<$1 preload="none"', $tag);
    }

    /**
     * Embedded players of known video platforms wait for a tap.
     *
     * @param array $match
     * @return string
     */
    protected function defer_iframe(array $match): string {
        $url = $this->attribute($match[0], 'src');
        if ($url === null || !$this->is_video_host($url)) {
            return $match[0];
        }
        return $this->placeholder($match[0], 'video', $url, null);
    }

    /**
     * PDF documents embedded in a page wait for a tap.
     *
     * @param array $match
     * @return string
     */
    protected function defer_document(array $match): string {
        $tag = $match[0];
        $isobject = stripos($tag, '<object') === 0;
        $url = $this->attribute($tag, $isobject ? 'data' : 'src');
        if ($url === null) {
            return $tag;
        }
        $pdf = preg_match('~\.pdf([?#]|$)~i', $url) || stripos((string) $this->attribute($tag, 'type'), 'pdf') !== false;
        if (!$pdf) {
            return $tag;
        }
        return $this->placeholder($tag, 'document', $url, $this->file_size($url));
    }

    /**
     * Heavy images wait for a tap; lighter ones are only marked for the text-only option.
     *
     * @param array $match
     * @return string
     */
    protected function defer_image(array $match): string {
        $tag = $match[0];
        if (stripos($tag, 'data-iqra-') !== false) {
            return $tag;
        }
        $url = $this->attribute($tag, 'src');
        if ($url === null || stripos($url, 'data:') === 0) {
            return $tag;
        }
        $size = $this->file_size($url);
        $threshold = 1024 * (int) (get_config('filter_iqralight', 'threshold') ?: self::DEFAULT_THRESHOLD);
        if ($size !== null && $size > $threshold) {
            return $this->placeholder($tag, 'image', $url, $size);
        }
        $marked = preg_replace('~^<img\b~i', '<img data-iqra-small="1"', $tag);
        if (!preg_match('~\sloading=~i', $marked)) {
            $marked = preg_replace('~^<img\b~i', '<img loading="lazy"', $marked);
        }
        return $marked;
    }

    /**
     * A heavy image inside a link waits with its link: the placeholder goes beside the
     * link, never inside it (a button or a link inside a link is not valid HTML, and
     * browsers would move them around).
     *
     * @param array $match
     * @return string
     */
    protected function defer_linked_image(array $match): string {
        $link = $match[0];
        if (stripos($link, 'data-iqra-') !== false || !preg_match('~<img\b[^>]*>~i', $link, $image)) {
            return $link;
        }
        $url = $this->attribute($image[0], 'src');
        if ($url === null || stripos($url, 'data:') === 0) {
            return $link;
        }
        $size = $this->file_size($url);
        $threshold = 1024 * (int) (get_config('filter_iqralight', 'threshold') ?: self::DEFAULT_THRESHOLD);
        if ($size === null || $size <= $threshold) {
            // A light image: the image pass marks it, the link stays as it is.
            return $link;
        }
        return $this->placeholder($link, 'image', $url, $size);
    }

    /**
     * Video and sound players built by Moodle (Video.js) wait for a tap, with their size.
     *
     * The whole player goes into a template: neither the media nor the player's script
     * (about 240 KB) is downloaded before the learner asks for it.
     *
     * @param array $match
     * @return string
     */
    protected function defer_player(array $match): string {
        if (strpos($match[0], 'data-iqra-') !== false) {
            return $match[0];
        }
        $kind = strtolower($match[2]) === 'audio' ? 'audio' : 'video';
        $url = preg_match('~<source\b[^>]*>~i', $match[0], $source) ? $this->attribute($source[0], 'src') : null;
        $url = $url ?? $this->attribute(preg_replace('~^.*?(<(?:video|audio)\b)~is', '$1', $match[0]), 'src');
        $size = $url === null ? null : $this->file_size($url);
        $bytes = $size === null ? '' : ' data-iqra-bytes="' . $size . '"';
        return '<div class="filter-iqra" data-filter-iqra="' . $kind . '"' . $bytes . '>' .
            '<template>' . $match[0] . '</template>' . $this->placeholder_markup($kind, $url, $size) . '</div>';
    }

    /**
     * Wraps a deferred element with its placeholder.
     *
     * @param string $element Original HTML of the element.
     * @param string $kind 'video', 'document' or 'image'.
     * @param string $url Address of the media, for the direct link.
     * @param int|null $size Size in bytes, when known.
     * @return string
     */
    protected function placeholder(string $element, string $kind, string $url, ?int $size): string {
        $deferred = preg_replace('~\s(src|data|srcset)=~i', ' data-iqra-$1=', $element, -1, $count);
        if (!$count) {
            return $element;
        }
        $bytes = $size === null ? '' : ' data-iqra-bytes="' . $size . '"';
        return '<span class="filter-iqra" data-filter-iqra="' . $kind . '"' . $bytes . '>' . $deferred .
            $this->placeholder_markup($kind, $url, $size) . '</span>';
    }

    /**
     * The placeholder itself: what is waiting, the load button and the direct link.
     *
     * The direct link is only offered for web addresses (http, https or relative): never
     * for another scheme, such as javascript:.
     *
     * @param string $kind 'video', 'audio', 'document' or 'image'.
     * @param string|null $url Address of the media.
     * @param int|null $size Size in bytes, when known.
     * @return string
     */
    protected function placeholder_markup(string $kind, ?string $url, ?int $size): string {
        $sizetext = $size === null ? get_string('unknownsize', 'filter_iqralight') : display_size($size);
        $scheme = $url === null ? null : parse_url($url, PHP_URL_SCHEME);
        $safe = $url !== null && ($scheme === null || in_array(strtolower($scheme), ['http', 'https'], true));
        return '<span class="filter-iqra-placeholder">' .
            '<span class="filter-iqra-label">' . get_string('notloaded_' . $kind, 'filter_iqralight') . '</span>' .
            '<button type="button" class="btn btn-primary filter-iqra-load" data-action="filter_iqralight/load">' .
            get_string('load_' . $kind, 'filter_iqralight', $sizetext) . '</button>' .
            ($safe ? '<a class="filter-iqra-direct" href="' . s($url) . '">' .
                get_string('opendirect', 'filter_iqralight') . '</a>' : '') .
            '</span>';
    }

    /**
     * Value of an attribute of an HTML tag, or null.
     *
     * @param string $tag
     * @param string $name
     * @return string|null
     */
    protected function attribute(string $tag, string $name): ?string {
        $opening = preg_replace('~>.*$~s', '>', $tag);
        if (preg_match('~\s' . preg_quote($name, '~') . '=("([^"]*)"|\'([^\']*)\'|([^\s>]+))~i', $opening, $m)) {
            $value = $m[2] !== '' ? $m[2] : ($m[3] ?? '') . ($m[4] ?? '');
            return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return null;
    }

    /**
     * Whether a URL points to a known video platform.
     *
     * @param string $url
     * @return bool
     */
    protected function is_video_host(string $url): bool {
        $host = strtolower((string) parse_url(strpos($url, '//') === 0 ? 'https:' . $url : $url, PHP_URL_HOST));
        foreach (self::VIDEO_HOSTS as $known) {
            if ($host === $known || substr($host, -strlen('.' . $known)) === '.' . $known) {
                return true;
            }
        }
        return false;
    }

    /**
     * Size of a file stored in Moodle, from its pluginfile.php address.
     *
     * @param string $url
     * @return int|null Size in bytes, or null for external or unknown files.
     */
    protected function file_size(string $url): ?int {
        global $CFG, $DB;
        if (array_key_exists($url, self::$sizes)) {
            return self::$sizes[$url];
        }
        $size = null;
        $prefix = '~^' . preg_quote($CFG->wwwroot, '~') .
            '/(?:webservice/)?pluginfile\.php/(\d+)/([a-z0-9_]+)/([a-z0-9_]+)/(.+)$~i';
        $path = preg_replace('~[?#].*$~', '', $url);
        if (preg_match($prefix, $path, $m)) {
            $filename = rawurldecode(basename($m[4]));
            // Sizes are kept for an hour across pages: -1 means "not a file of this site".
            $cache = \cache::make('filter_iqralight', 'filesizes');
            $key = sha1($m[1] . '/' . $m[2] . '/' . $m[3] . '/' . $filename);
            $cached = $cache->get($key);
            if ($cached === false) {
                $found = $DB->get_field_sql(
                    "SELECT MAX(filesize) FROM {files}
                      WHERE contextid = ? AND component = ? AND filearea = ? AND filename = ?",
                    [(int) $m[1], $m[2], $m[3], $filename]
                );
                $cached = $found ? (int) $found : -1;
                $cache->set($key, $cached);
            }
            $size = $cached >= 0 ? (int) $cached : null;
        }
        return self::$sizes[$url] = $size;
    }
}
