<?php

function report_qmulogs_extend_navigation_course($navigation, $course, $context)
{
	try {
		if(has_capability('report/myreport:view', $context)) {
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
	function report_myreport_supports_logstore($instance)
	{
		return $instance instanceof \core\log\sql_reader;
	}