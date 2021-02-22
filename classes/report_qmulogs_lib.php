<?php /** @noinspection SqlDialectInspection */
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


use context_course;
use core\log\sql_internal_table_reader;
use ddl_exception;
use dml_exception;
use Exception;
use moodle_url;
use Throwable;
use xmldb_table;

class report_qmulogs_lib
{

	/**
	 * @param object $user
	 * @param array  $teacher_records
	 *
	 * @return bool
	 */
	public static function is_teacher_in_list(object $user, array $teacher_records): bool
	{
		$response = FALSE;
		foreach($teacher_records as $teacher_record){
			if($user->username === $teacher_record->username && $user->email === $teacher_record->email) {
				$response = TRUE;
				break;
			}
		}
		return $response;
	}

	/**
	 * @param       $input
	 * @param false $stripPadding
	 *
	 * @return string|string[]
	 */
	public static function base64_safe_encode(string $input, bool $stripPadding = FALSE): string
	{
		$encoded = strtr(base64_encode($input), '+/=', '-_~');
		return ($stripPadding) ? str_replace("~", "", $encoded) : $encoded;
	}

	/**
	 * @param $input
	 *
	 * @return false|string
	 */
	public static function base64_safe_decode(string $input): string
	{
		return base64_decode(strtr($input, '-_~', '+/='));
	}

	/**
	 * @param int $courseid
	 *
	 * @return array
	 */
	public static function get_course_teacher_ids(int $courseid): array
	{
		global $DB;
		$teachers = [];

		try {
			$role = $DB->get_record('role', ['shortname' => 'editingteacher']);
			$context = context_course::instance($courseid);
			$teachers = get_role_users($role->id, $context, FALSE);
		} catch(dml_exception $exception) {
			error_log($exception->getMessage() . $exception->getTraceAsString());
		}

		return $teachers;
	}

	/**
	 * @return array
	 */
	public static function get_teacher_non_student_core_capabilities(): array
	{
		global $DB;
		$return = [];
		$sql = "SELECT DISTINCT capability
FROM {role_capabilities} AS mrc
JOIN {role} AS mr ON mr.id = mrc.roleid
WHERE mr.archetype = 'teacher'
  AND capability 
  NOT IN ( SELECT capability
           FROM {role_capabilities} AS mrc
           JOIN {role} AS mr ON mr.id = mrc.roleid
           WHERE mr.archetype = 'student' )
AND capability LIKE 'moodle/%'
ORDER BY capability";
		try {
			$return = $DB->get_records_sql($sql);
		} catch(dml_exception $exception) {
			error_log($exception->getMessage() . $exception->getTraceAsString());
		}
		return $return;
	}

	/**
	 * @param $database_name
	 * @param $table_name
	 *
	 * @return array
	 * @throws dml_exception
	 */
	public static function get_table_foreign_keys_mysql(string $database_name, string $table_name): array
	{
		global $strings;
		$relations = [];
		if($database_name > '' && $table_name > '') {
			global $DB;
			#  MySQL support for relationships
			if($DB->get_dbfamily() == 'mysql' && $DB->get_server_info()['version'] >= '5.6') {
				$sql = "
SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
FROM information_schema.key_column_usage 
WHERE  
constraint_schema = :database_name
AND table_name = :table_name 
";
			} else {
				$sql = '';
			}
			if($sql) {
				$r = $DB->get_records_sql($sql, ['database_name' => $database_name, 'table_name' => $table_name]);
			}
			$fk = NULL;
			if(count($r) > 0) {
				$fk = $r;
			} elseif(count($r) === 0) {
				echo '<p>' . $strings['relations_for'] . " {$database_name}.{$table_name} " . $strings['empty_set'] . "</p>";
			} else {
				echo "<p>" . $strings['relations_for'] . " {$database_name}.{$table_name} " . $strings['error_set'] . "</p>";
				var_dump($r);
			}
			#  MySQL support for relationships
			if($DB->get_dbfamily() == 'mysql' && $DB->get_server_info()['version'] >= '5.6') {
				$r = $DB->get_records_sql("SELECT c.ordinal_position, c.* 
FROM information_schema.columns AS c 
WHERE TABLE_SCHEMA = ? 
AND TABLE_NAME = ?", [$database_name, $table_name]);
				if(count($r) > 0) {
					$relations = $fk;
				}
			}
		}
		return $relations;
	}

	/**
	 * @return array
	 */
	public static function get_logstore_names(): array
	{
		# global $CFG;
		$table_names = [];
		if($modern_logging = function_exists('get_log_manager')) {
			# $prefix = $CFG->prefix;
			# $postfix = '_log';
			$log_manager = get_log_manager();
			$readers = $log_manager->get_readers();
			foreach($readers as $logstore => $reader){
				if($reader instanceof sql_internal_table_reader) {
					$log_table = '{' . $reader->get_internal_log_table_name() . '}';
					$table_names[] = $log_table;
				}
			}
		}
		return $table_names;
	}

	/**
	 * @return string
	 */
	public static function get_preference_name(): string
	{
		return 'report_qmulogs_preferences';
	}

	/**
	 * @param $form_data
	 *
	 * @return string
	 */
	public static function get_logs_sql(array $form_data): string
	{
		global $strings;
		$table_name = static::get_standard_log_table_name();
		$selected_fields = static::mandatory_fields();
		foreach($form_data as $field => $value){
			if((int)$value > 0) {
				$selected_fields[] = $field;
			}
		}
		$transform_fields = static::transform_fields();
		$mandatory_fields = static::mandatory_fields();
		# prepare fields to select and add conditional joins and joined fields
		$fields_Str = '';
		$joins = '';
		$where = ''; # ' WHERE 1 = 1 '
		foreach($selected_fields as $selected_field){
			$selected_field = str_replace('field_', '', $selected_field);
			if($selected_field === $strings['course_id']) {
				continue;
			}

			unset($join_table_name, $join_table_alias, $join_field_primary, $join_field_match, $join_fields);
			if(array_key_exists($selected_field, $transform_fields)) {

				[$join_table_name, $join_table_alias, $join_field_primary, $join_field_match, $join_fields]
					= explode('|', $transform_fields[$selected_field]);
				$join_fields_str = '';
				if($join_table_name > '') {
					$join_fields = explode(',', $join_fields);

					# Add fields remember to trim the above field names
					foreach($join_fields as $join_field){
						$join_field = trim($join_field);
						$join_fields_str .= "{$join_table_alias}.{$join_field} AS {$join_table_alias}_{$selected_field}_{$join_field},\n";
					}

					# Add join if mandatory field set it to JOIN else to LEFT JOIN
					if(!array_key_exists($selected_field, $mandatory_fields)) {
						$joins .= "LEFT ";
					}
					$joins .= "JOIN {{$join_table_name}} AS {$join_table_alias} ON {$join_table_alias}.{$join_field_match} = {$table_name}.{$join_field_primary}\n";
				} else {
					$replaced_field = str_replace($selected_field, "{$table_name}.{$selected_field}", $join_fields);
					$join_fields_str .= "{$replaced_field} AS {$table_name}_{$selected_field},\n";
				}
			}
			if($selected_field) {
				if(isset($join_fields, $join_fields_str)) {
					$fields_Str .= $join_fields_str;
				} else {
					$fields_Str .= "{$table_name}.{$selected_field} AS {$table_name}_{$selected_field},\n";
				}
			}
		}

		# remove the comma and new line from the end of the last field
		$fields_Str = substr($fields_Str, 0, -2);
		# Default order is the id
		$order = "{$table_name}.id";
		# Do not set limits if there is no need
		$limit = '';# 'LIMIT  500';
		# Get the course ID
		$course_id = $form_data->course_id ?? $form_data['course_id'];
		# Get the table field names
		$table_fields = implode(', ', array_keys(static::get_table_fields()));
		# Prepare the base query where an index on the fields is utilised
		$base_sql = "(SELECT {$table_fields} FROM {{$table_name}} AS l WHERE l.courseid = {$course_id} ORDER BY id {$limit} )";

		# Then from the selected records make the joins. This is really fast way to extract logs for a course
		# and have them only joined to other tables.
		return "SELECT {$fields_Str} 
FROM {$base_sql} AS {$table_name} 
{$joins}
{$where}
ORDER BY {$order}";
	}

	/**
	 * @return string
	 */
	public static function get_default_preferences(): string
	{
		$fields = [];
		foreach(static::get_table_fields() as $name => $field){
			$fields['field_' . $name] = 1;
		}
		return static::encode_preferences($fields);
	}

	/**
	 * @param string|null $table_name
	 *
	 * @return array
	 */
	public static function get_table_fields(?string $table_name = NULL): array
	{
		global $DB;
		$fields = [];

		try {
			$table_name = $table_name ?? static::get_standard_log_table_name();
			if(static::table_exists($table_name)) {

				$fields = $DB->get_columns($table_name);
			}
		} catch(Exception $exception) {
			error_log($exception->getMessage() . $exception->getTraceAsString());
		}
		return $fields;
	}

	/**
	 * @return string[]
	 */
	public static function mandatory_fields(): array
	{
		return ['id', 'userid', 'courseid', 'timecreated'];
	}

	/**
	 * @return string[]
	 */
	public static function transform_fields(): array
	{
		# 'fieldname' => 'join_table_name|join_table_alias|join_field|join_target_field|join_table_field_list'
		return ['userid' => 'user|student|userid|id|firstname, middlename, lastname, idnumber',
			'courseid' => 'course|course|courseid|id|id, idnumber, shortname',
			'relateduserid' => 'user|relateduser|relateduserid|id|firstname, middlename, lastname, idnumber',
			'realuserid' => 'user|realuser|realuserid|id|firstname, middlename, lastname, idnumber',
			'timecreated' => '|||timecreated|from_unixtime(timecreated)'
		];
	}

	/**
	 * @param string $url
	 */
	public static function safe_redirect(string $url): void
	{
		try {
			redirect(new moodle_url($url, [], NULL));
		} catch(Throwable $e) {
			header('Location: ' . $url);
		}
	}

	/**
	 * @param $pref_name
	 * @param $data array
	 *
	 * @return bool
	 */
	public static function form_store_data(string $pref_name, array $data): bool
	{
		global $USER;
		try {
			set_user_preference($pref_name, static::encode_preferences($data), (int)$USER->id);
		} catch(Exception $exception) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * @param $data
	 *
	 * @return string
	 *
	 * The zipping and encoding are utilised to minimise and standardise the set of preferences in large objects or
	 * arrays as the preference field is of limited length
	 */
	public static function encode_preferences(array $data): string
	{
		return base64_encode(gzcompress(json_encode($data)));
	}

	/**
	 * @param $data
	 *
	 * @return mixed
	 */
	public static function decode_preferences(string $data): array
	{
		return json_decode(gzuncompress(base64_decode($data)), TRUE);
	}

	/**
	 * @param string $sql
	 *
	 * @return array
	 */
	public static function get_course_logs(string $sql): ?array
	{
		global $DB;
		try {
			return $DB->get_records_sql($sql);
		} catch(Exception $exception) {
			error_log($exception->getMessage() . $exception->getTraceAsString());
			return NULL;
		}
	}

	/**
	 * @param $table_name
	 *
	 * @return bool
	 */
	public static function table_exists(string $table_name): bool
	{
		global $DB;
		$return = FALSE;
		try {
			$db_man = $DB->get_manager();
			$return = $db_man->table_exists(new xmldb_table(str_replace(['{', '}'], '', $table_name)));
		} catch(ddl_exception $exception) {
			error_log($exception->getMessage() . $exception->getTraceAsString());
		}
		return $return;
	}

	/**
	 * @return string
	 */
	public static function get_standard_log_table_name(): string
	{
		global $CFG;
		$table_name = '';
		try {
			$text = file_get_contents($CFG->dirroot
									  . DIRECTORY_SEPARATOR . $CFG->admin
									  . '/tool/log/store/standard/db/install.xml');
			$lines = preg_grep('/TABLE NAME/', explode("\n", $text));
			if($lines) {
				foreach($lines as $line){
					$words = explode('"', $line);
					if($table_name === '' && $words[1] !== '') {
						$table_name = $words[1];
						break;
					}
				}
				if($table_name > '') {
					$table_name = self::table_exists($table_name) ? $table_name : '';
				}
			}
		} catch(Exception $exception) {
			error_log($exception->getMessage() . $exception->getTraceAsString());
		}
		return $table_name;
	}

	public static function export_records_to_csv(array $logs, string $course_short_name, int $course_id): void
	{
		global $strings;
		header('HTTP/1.1 200 OK');
		header("Content-type: text/csv");
		header("Cache-Control: no-store, no-cache");
		header("Pragma: no-cache");
		header("Expires: 0");
		header("Content-Disposition: attachment; filename=\"{$strings['base_filename']}{$course_short_name}_id_{$course_id}.csv\"");
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

}
