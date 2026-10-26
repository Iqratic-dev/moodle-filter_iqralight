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

namespace filter_iqralight\local;

/**
 * Place of the filter in Moodle's filter order.
 *
 * The filter must come after every other filter, in particular after "Multimedia plugins",
 * which builds the video and sound players: the players can only wait for a tap if they
 * already exist when this filter runs.
 *
 * @package    filter_iqralight
 * @copyright  2026 IQRATIC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class order {
    /**
     * Whether the filter is the last of the site's filters.
     *
     * @return bool
     */
    public static function is_last(): bool {
        global $DB;
        $filters = $DB->get_fieldset_select(
            'filter_active',
            'filter',
            'contextid = ? ORDER BY sortorder ASC',
            [\core\context\system::instance()->id]
        );
        return $filters && end($filters) === 'iqralight';
    }

    /**
     * Moves the filter to the last place, keeping its state.
     */
    public static function move_last(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filterlib.php');
        $state = $DB->get_field(
            'filter_active',
            'active',
            ['filter' => 'iqralight', 'contextid' => \core\context\system::instance()->id]
        );
        if ($state === false) {
            return;
        }
        $steps = $DB->count_records('filter_active', ['contextid' => \core\context\system::instance()->id]);
        while (!self::is_last() && $steps-- > 0) {
            filter_set_global_state('iqralight', (int) $state, 1);
        }
    }
}
