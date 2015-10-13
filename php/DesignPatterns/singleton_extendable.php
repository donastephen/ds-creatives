<?php
class Singleton
{

	protected static $instances = array();

	public static function getInstance()
	{
		$class = get_called_class();
		if(self::$instance[$class] === null)
		{
			self::$instance[$class] = new static();
		}
		return self::$instance[$class];
	}

	public function __clone()
	{
		
	}
}