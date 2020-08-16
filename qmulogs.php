<?php /** @noinspection GlobalVariableUsageInspection */
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

use report_qmulogs\report_qmulogs_lib;

require_once '../../config.php';
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
/** @var object $CFG */
require_once './locallib.php';
try {
	$sesskey = required_param('sesskey', PARAM_RAW);
	$course_id = required_param('course_id', PARAM_INT);
	/** @var object $USER */
	if(((int)$USER->id > 1) && (sesskey() === $sesskey)) {
		$title = 'Log Reader Extended Export CSV';
		$url = '/test_csv.php';
		/** @var object $PAGE */
		$PAGE->set_url($url);
		$PAGE->set_context(context_system::instance());
		$PAGE->set_title($title);
		# $PAGE->set_pagelayout('');
		$PAGE->set_heading($title);
		# $PAGE->navbar->add($title, new moodle_url($url));

		# Save posted preferences
		$pref_name = report_qmulogs_lib::get_preference_name();
		$form_data = [];
		if($_POST) {
			foreach($_POST as $key => $value){
				if(strpos($key, 'field_') === 0) {
					$form_data[$key] = (int)$value;
				}
				if($key === 'course_id') {
					$form_data[$key] = $value;
				}
			}
			$errors = report_qmulogs_lib::form_store_data($pref_name, $form_data);
		}

		# Get the logs and transform them to CSV
		$sql = report_qmulogs_lib::get_logs_sql($form_data);
		/** @var object $DB */
		$course_short_name = $DB->get_field('course', 'shortname', ['id' => $course_id]);
		$course_short_name = preg_replace('~\P{Xan}++~u', '-', $course_short_name);
		# $course_short_name = base64_safe_encode($course_short_name);
		$logs = report_qmulogs_lib::get_course_logs($sql);
		if($logs) {
			header('HTTP/1.1 200 OK');
			header("Content-type: text/csv");
			header("Cache-Control: no-store, no-cache");
			header("Pragma: no-cache");
			header("Expires: 0");
			header('Content-Disposition: attachment; filename="moodle_logstore_extended_logs_course_'
				   . $course_short_name . '_id_' . ($course_id) . '.csv"');
			$outfile = fopen("php://output", 'wb');
			$first_key = array_keys($logs)[0];
			$header = $logs[$first_key];
			fputcsv($outfile, array_keys(get_object_vars($header)), ',', '"');
			foreach($logs as $log){
				fputcsv($outfile, get_object_vars($log), ',', '"');
			}
			fclose($outfile);
			flush();
		}
		exit;
	}
} catch(Exception $exception) {
	error_log($exception->getMessage() . $exception->getTraceAsString());
}