<?php

function getMaxOccuringLetter( $str )
{
	if(strlen($str) > 1000000){
		return 'Maximun string size reached';
	}
	$newArray = array();
	$maxCount = 0;
	$maxLetter = null;

	for($i = 0; $i < strlen( $str ); $i++)
	{

		if(array_key_exists($str[$i], $newArray))
		{
			$newArray[$str[$i]] = $newArray[$str[$i]] + 1;
		}
		else{
			$newArray[$str[$i]] = 1;
		}

		if($newArray[$str[$i]] > $maxCount){
			$maxCount = $newArray[$str[$i]];
			$maxLetter = $str[$i];
		}
	}

	return $maxLetter;
}

$letter=getMaxOccuringLetter('LKKNHKKKK');
echo $letter;