<?php

ini_set('max_execution_time', 86400);
ini_set('memory_limit', '-1');
ini_set('display_startup_errors',1);
ini_set('display_errors',1);
error_reporting(E_ALL);

$from_file_column = (isset($_POST['from_file_column']) && $_POST['from_file_column'] !='' ? (int) $_POST['from_file_column'] : 0);
$to_file_column = (isset($_POST['to_file_column']) && $_POST['to_file_column'] !='' ? (int) $_POST['to_file_column'] : 0);
$to_file_name = (isset($_POST['to_file_name']) && $_POST['to_file_name'] !='' ? $_POST['to_file_name'].'.csv' : $_FILES['to_file']['name']);

// Checkbox
$form_file_checkbox = (isset($_POST['form_file_checkbox']) ? true : false);
$to_file_checkbox = (isset($_POST['to_file_checkbox']) ? true : false);

function phone_number_format($number) {
	// Allow only Digits, remove all other characters.
	$number = preg_replace("/[^\d]/","",$number);

	// get number length.
	$length = strlen($number);

	// if number = 10
	if($length == 10) {
		$number = preg_replace("/^1?(\d{3})(\d{3})(\d{4})$/", "$1$2$3", $number);
	}

	return $number;

}

$form_file = $_FILES['form_file']['tmp_name'];
$to_file = $_FILES['to_file']['tmp_name'];

// disable caching
$now = gmdate("D, d M Y H:i:s");
header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
header("Last-Modified: {$now} GMT");

// force download
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");

// disposition / encoding on response body
header("Content-Disposition: attachment;filename={$to_file_name}");
header("Content-Transfer-Encoding: binary");


$file = fopen($form_file, "r");
$row = 1;
$numbers = [];
while (($getData = fgetcsv($file, 10000, ",")) !== FALSE)
{
	if($row == 1){ $row++; continue; }

	if($form_file_checkbox)
	{
		array_push($numbers, phone_number_format($getData[$from_file_column]));
	}
	else
	{
		array_push($numbers, $getData[$from_file_column]);
	}
}


$file = fopen($to_file, "r");
$header = fgetcsv($file);
$row = 1;
$arr = [];


$df = fopen('php://output', 'w');
fputcsv($df, $header);
while (($getData = fgetcsv($file, 10000, ",")) !== FALSE)
{
	if($to_file_checkbox)
	{
		if (!in_array(phone_number_format($getData[$to_file_column]), $numbers))
		{
			$getData[$to_file_column] = phone_number_format($getData[$to_file_column]);
			fputcsv($df, $getData);
		}
	}
	else
	{
		if (!in_array($getData[$to_file_column], $numbers))
		{
			fputcsv($df, $getData);
		}
	}
}
fclose($df);
die();
