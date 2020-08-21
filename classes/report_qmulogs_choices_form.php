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
namespace report_qmulogs;
use Exception;
use moodleform;
use stdClass;
/** @var object $CFG */
require_once $CFG->libdir . '/formslib.php';
require_once $CFG->dirroot . '/report/qmulogs/locallib.php';

class report_qmulogs_choices_form extends moodleform
{

	private $field_choices;

	/**
	 * @return object|null
	 */
	final public function get_data(): ?object
	{
		return parent::get_data();
	}

	/**
	 * @param array|stdClass $default_values
	 */
	final public function set_data($default_values): void
	{
		if(is_object($default_values)) {
			$default_values = (array)$default_values;
		}
		$this->_form->setDefaults($default_values);
	}

	/**
	 * form definition
	 */
	final public function definition(): void
	{
		global $USER, $DB, $strings;
		try {
			$user_courses = enrol_get_all_users_courses($USER->id, $only_active = FALSE,
														'id, idnumber, shortname, 
															  fullname, category, startdate, visible');
/*
			foreach($user_courses as $cid => $course){
				if(!is_teacher_in_list($USER, get_course_teacher_ids($cid))) {
					unset($user_courses[$cid]);
				}
			}
*/
			$moodle_form = &$this->_form;
			$moodle_form->addElement('hidden', 'sesskey', sesskey());
			$moodle_form->addElement('header', $strings['fields_choice_header'], $strings['fields_choice_header_desc']);

			$moodle_form->addElement('static', $strings['table_name'], $strings['table_name_desc'],
									 report_qmulogs_lib::get_standard_log_table_name(), ['font-weight' => 'bold']);

			# Get all the table fields to prepare the form elements
			$fields = report_qmulogs_lib::get_table_fields();
			foreach($fields as $name => $record){
				$element = 'field_' . $name;
				$this->field_choices[$element] = new stdClass();
				$this->field_choices[$element]->field = $name;
				$this->field_choices[$element]->always = in_array($name,
																  report_qmulogs_lib::mandatory_fields(),
																  TRUE);
				$this->field_choices[$element]->selected = $this->field_choices[$element]->always;
			}

			$static_fields = [];
			foreach($this->field_choices as $name => $field_choice){
				$field_element_id = $name;
				# $field_label = ' : ' . $field_choice->field;
				if($field_choice->always) {
					$static_fields[] = $moodle_form->createElement('static', $field_element_id,
																   NULL, $text = $field_choice->field);
				}
			}
			$moodle_form->addGroup($static_fields, 'static_fields', 'Mandatory fields',
								   ', ', FALSE);

			foreach($this->field_choices as $name => $field_choice){
				$field_element_id = $name;
				$field_label = ' : ' . $field_choice->field;
				if(!$field_choice->always) {
					$moodle_form->addElement('advcheckbox', $field_element_id, $field_label);
					$moodle_form->setType($field_element_id, PARAM_INT);
					$default_value = ($field_choice->always || $field_choice->selected);
					$moodle_form->setDefault($field_element_id, $default_value);
				}
			}

			$moodle_form->addElement('header', $strings['course_choice_header'],
									 $strings['course_choice_header_desc'] . fullname($USER));

			$moodle_form->createElement('static', $id = $strings['courses_label'],
										$label = $strings['courses_label'], $text = $strings['courses_label']);
			$moodle_form->addElement('static', $strings['courses_label']);

			$course_select_records = $DB->get_records_select_menu('course'
				, ' id IN (' . implode(',', array_keys($user_courses)) . ')'
				, [], '', 'id, shortname');
			$moodle_form->addElement('select', $strings['course_id'],
									 $strings['course_id'], $course_select_records,
									 'title="' . $strings['course_id'] . '"');

			$button_array = array();
			$button_array[] = $moodle_form->createElement('submit', $strings['export_button'],
														  $submit_label = $strings['export_button_desc']);
			$button_array[] = $moodle_form->createElement('cancel');
			$moodle_form->addGroup($button_array, 'button_array', '', array(' '), FALSE);
			$moodle_form->closeHeaderBefore('button_array');

		} catch(Exception $exception) {
			error_log($exception->getMessage());
		}
	}

	/**
	 * @param array $data
	 * @param array $files
	 *
	 * @return array
	 */
	final public function validation($data, $files): array
	{
		return parent::validation($data, $files);
	}
}