<?php
/**
 * Configuration Reader
 * This script contains an helper to read configuration files
 *
 * @license MIT
 * @copyright 2015 Tommy Teasdale
 */

/**
 * Configuration Reader
 * Read and write project's configuration file
 */
class Config {
	
	/**
	 * Instance of the Config reader
	 * Singleton Implementation
	 * 
	 * @var Config
	 */
	private static $_instance;
	
	
	/**
	 * Setting strings extracted from the configuration file
	 * 
	 * @var array
	 */
	private $settings;
	//private $settings = [];
	
	/**
	 * Construct the Conguration Reader handler
	 * Extract string from the configuration file 
	 */
	private function __construct () {
		
		if (file_exists('config.ini')) {
			$this->settings = parse_ini_file('config.ini', true);
		} else {
			die("No config file founded.");
		}
		
	}
	
	/**
	 * Singleton design pattern implementation
	 * 
	 * @static
	 * @return Config
	 */
	public static function get_instance () {
		
		if (!isset(self::$_instance)) {
			self::$_instance = new static();
		}
		
		return self::$_instance;
		
	}
	
	/**
	 * Fetch a configuration string
	 * 
	 * @param string $prefix
	 * @param string $key
	 * @return <NULL|array>
	 */
	public static function get ($prefix, $key) {
		
		$prefix = strtolower($prefix);
		$key = strtolower($key);
		return isset(self::get_instance()->settings[$prefix][$key]) ? self::get_instance()->settings[$prefix][$key] : null;
		
	}
	
	/**
	 * Write or update a configuration string
	 * 
	 * @param string $prefix
	 * @param string $key
	 * @param string $value
	 */
	public static function set ($prefix, $key, $value) {
		
		$prefix = strtolower($prefix);
		$key = strtolower($key);
		
		self::get_instance()->settings[$prefix][$key] = $value;
		write_ini_file(self::get_instance()->settings, 'config.ini', true);
		
	}
}