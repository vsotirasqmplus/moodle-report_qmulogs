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
 * Lang strings.
 *
 * Language strings to be used by report/logs
 *
 * @package    report_qmulogs
 * @copyright  2020 onwards Vasileios Sotiras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use core\log\sql_reader;

function report_qmulogs_extend_navigation_course($navigation, $course, $context)
{
	try {
		if(has_capability('report/qmulogs:view', $context)) {
			$url = new moodle_url('/report/qmulogs/index.php', array('id' => $course->id));
			$navigation->add(get_string('pluginname', 'report_qmulogs'), $url, navigation_node::TYPE_SETTING, NULL, NULL, new pix_icon('i/report', ''));
		}
	} catch(Exception $exception) {
		error_log($exception->getMessage() . $exception->getTraceAsString());
	}
}

/**
 * Callback to verify if the given instance of store is supported by this report or not.
 *
 * @param string $instance store instance.
 *
 * @return bool returns true if the store is supported by the report, false otherwise.
 */
function report_qmulogs_supports_logstore($instance)
{
	return $instance instanceof sql_reader;
}