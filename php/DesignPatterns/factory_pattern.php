<?php

// Factory Pattern
/*
if you need to change, rename, or replace the Automobile class later on you can do so and you will only have to modify the
code in the factory, instead of every place in your project that uses the Automobile class

if creating the object is a complicated job you can do all of the work in the factory, 
instead of repeating it every time you want to create a new instance

Very nice example

Ikea store differnt product types
http://coderoncode.com/design-patterns/programming/php/coding/development/2014/01/19/design-patterns-php-factories.html

*/
class Automobile
{

	private $make;
	private $model;
	public function __construct($make,$model)
	{
		$this->make = $make;
		$this->model = $model;

	}

	public function getMakeAndModel()
	{
		echo 'Make - '.$this->make.' :Model -'.$this->model;
	}
}

class AutomobileFactory
{

		public static function create($make,$model)
		{
			return new Automobile($make,$model);
		}

}