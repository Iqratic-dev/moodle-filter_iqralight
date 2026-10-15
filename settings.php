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

/**
 * Settings for filter_iqralight.
 *
 * @package    filter_iqralight
 * @copyright  2026 IQRATIC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'filter_iqralight/threshold',
        get_string('threshold', 'filter_iqralight'),
        get_string('threshold_desc', 'filter_iqralight'),
        \filter_iqralight\text_filter::DEFAULT_THRESHOLD,
        '/^(?:[5-9]\d|[1-9]\d{2}|1\d{3}|20[0-3]\d|204[0-8])$/',
        6
    ));

    $modules = [];
    foreach (core_component::get_plugin_list('mod') as $name => $unused) {
        if (!in_array($name, \filter_iqralight\text_filter::ALWAYS_EXCLUDED, true)) {
            $modules[$name] = get_string('pluginname', 'mod_' . $name);
        }
    }
    core_collator::asort($modules);
    $settings->add(new admin_setting_configmultiselect(
        'filter_iqralight/excludedmodules',
        get_string('excludedmodules', 'filter_iqralight'),
        get_string('excludedmodules_desc', 'filter_iqralight'),
        [],
        $modules
    ));
}
