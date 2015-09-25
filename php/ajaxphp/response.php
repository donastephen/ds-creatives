<?php
/**
 * Created by PhpStorm.
 * User: donastephen
 * Date: 9/10/15
 * Time: 12:14 AM
 */

header('application/json');
$data = $_POST['favorite_beverage'];
$data = $_POST['favorite_restaurant'];

echo json_encode($data);