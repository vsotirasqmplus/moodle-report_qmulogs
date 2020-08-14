<?php /** @noinspection GlobalVariableUsageInspection */
/** @noinspection PhpUndefinedVariableInspection */
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
 * Index of report log reader extended.
 *
 * @package    report_log_reader_extended
 * @copyright  2020 Vasileios Sotiras <vsotiras@qmul.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once '../../config.php';
require_once $CFG->dirroot . '/lib/enrollib.php';
require_once $CFG->dirroot . '/report/qmulogs/locallib.php';
use report_qmulogs\report_qmulogs_choices_form;

# Main page contents
try {
	require_login();
	($USER->id > 1) || redirect('/');
	$title = $strings['title'];
	$url = '/test.php';
	$PAGE->set_url($url);
	$PAGE->set_context(context_system::instance());
	$PAGE->set_title($title);
	$PAGE->set_pagelayout('report');
	$PAGE->set_heading($title);
	$PAGE->navbar->add($title, new moodle_url($url));
	$html = '';

	if($te = table_exists($table_name = get_standard_log_table_name())) {

		$courses = enrol_get_all_users_courses($USER->id, $only_active = FALSE,
											   'id, idnumber, shortname, fullname, category, startdate, visible');
		if($courses) {
			$default_preferences = get_default_preferences();
			$pref_name = get_preference_name();
			$preferences = get_user_preferences($pref_name, $default_preferences, $USER->id);
			$courses_form = new report_qmulogs_choices_form('./qmulogs.php', $preferences, 'post',
															'_blank', $attr = NULL,
															$editable = TRUE, $ajax_form_data = []);

			if($courses_form->is_cancelled()) {
				safe_redirect($_SERVER['HTTP_REFERER']);
			} elseif($courses_form->is_submitted() && ($form_data = $courses_form->get_data())) {
				$html .= $strings['redirect_desc'];
				safe_redirect($_SERVER['HTTP_REFERER']);
			} else {
				# Get and prepare form data
				$field_checks = [];
				$default_preferences = get_default_preferences();
				$preferences = get_user_preferences($pref_name, $default_preferences, $USER->id);
				if($preferences) {
					$preferences = decode_preferences($preferences);
					foreach($preferences as $field => $preference){
						$field_checks[$field] = $preference;
					}
				}
				# Send data to the form before displaying it
				$courses_form->set_data($field_checks);

				# Store the Display of the form
				ob_start();
				$courses_form->display();
				$html .= ob_get_clean();
			}

		} else {
			$html .= '<span class="alert-info">' . $strings['no_teaching_courses'] . '</span>';
		}
	}
	# Display the page headers
	echo $OUTPUT->header();
	echo $html;
	echo $OUTPUT->footer();
} catch(Exception $exception) {
	error_log($exception->getMessage() . $exception->getTraceAsString());
}