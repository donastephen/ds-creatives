<?php
final class Singleton
{

	//this pattern can be used for global configuration classes

	private static $instance = null;

	private __construct(){}

	public static function getInstance()
	{
		if(self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __clone()
	{
		/* To prevent outside world to not clone*/
	}

	private function __wakeup()
	{
		
	}
}