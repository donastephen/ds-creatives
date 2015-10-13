<?php

function bubbleSort($arr)
{
	$i=0;
	echo count($arr);
	$arr_length = count($arr);
	for ($i = $arr_length-1; $i > 0; $i--){
		for ($j = 0; $j < $i ; $j++){
			if ($arr[$j] > $arr[$j+1]){
				$temp = $arr[$j];
				$arr[$j] = $arr[$j+1];
				$arr[$j+1] = $temp;
			}
		}
	}
	return $arr;

}

$myarr = [5,6,2,4,1];
$sorted = bubbleSort($myarr);
print_r($sorted);