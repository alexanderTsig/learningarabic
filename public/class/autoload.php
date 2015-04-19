<?php

class Autoloader {
	public static $prefix = 'class/';

	static public function loader($className) {
		$filename = self::$prefix . str_replace('\\', '/', $className) . '.php';

		if (file_exists($filename)) {
			include($filename);
			if (class_exists($className))
				return TRUE;
		}

		return FALSE;
	}
}

spl_autoload_register('Autoloader::loader');
