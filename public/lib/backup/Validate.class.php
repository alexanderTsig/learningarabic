<?php

require_once 'Number.class.php';

class Validate {

	public static function level($level, $method_name = 'undefined_method') {
		if (Number::isInteger($level) && $level > 0 && $level <= count(Application::getLevels()))
			return true;
			
		Log::error("Invalid level $level in $method_name()");
		return false;
	}

	public static function lesson($level, $lesson, $method_name = 'undefined_method') {
		if (! Validate::level($level, $method_name))
			return false;

		$final_lesson = max(Application::getLessons($level));

		if (Number::isInteger($lesson) && $lesson > 0 && $lesson <= $final_lesson['lesson'])
			return true;

		Log::error("Invalid level, lesson tuple ($level, $lesson) in $method_name()");
		return false;
	}

	public static function gender($gender) {
		return ($gender === 'M' || $gender === 'F');
	}
}
