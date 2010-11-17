<?php
class Log {
	private static $handle = array();
    private static $levels = array(
        "verbose" => 10,
        "debug" => 20,
        "warn" => 30,
    );

	public static function debug($str) {
		self::_log($str, "debug");
	}

    public static function verbose($str) {
        self::_log($str, "verbose");
    }

    public static function warn($str) {
        self::_log($str, "warn");
    }
	
	private static function _log($str, $logLevel) {
        $allowed = Settings::getValue("log.level", "warn");

        if (self::$levels[$logLevel] < self::$levels[$allowed]) {
            return;
        }
        foreach (self::$levels as $level => $val) {
            if ($val < self::$levels[$allowed]) {
                continue;
            }
            if ($val > self::$levels[$logLevel]) {
                break;
            }
            $path = Settings::getValue("log.".$level);
            if (!isset(self::$handle[$path])) {
                if (!file_exists($path)) {
                    //@todo obviously this is rancid. improve - move to utils maybe?
                    $file = fopen($path, "w");
                    fclose($file);
                    chmod($path, 0777);
                }
                if (!is_writable($path)) {
                    throw new CoreException("Logfile is not writable", CoreException::LOG_FILE_ERROR, array("path" => $path));
                }
                self::$handle[$path] = fopen($path, "a");
                if (!self::$handle[$path]) {
                    throw new CoreException("Could not open logfile for writing", CoreException::LOG_FILE_ERROR, array("path" => $path));
                }
            }
            fwrite(self::$handle[$path], date("d/m/Y H:i:s")." - ".$str.PHP_EOL);
        }
	}
}
