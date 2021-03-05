<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ob_start();
session_start();

$result = json_decode(file_get_contents("php://input"), true);

# Ensure that we have all data required:
if(!is_array($result) OR !isset($result["dataSets"], $result["classifier"]) OR !is_array($result["dataSets"]) OR $_SERVER["REQUEST_METHOD"] !== "POST") {
	http_response_code(406); # Code 406 stands for "Not Acceptable" which is exactly what our input is in one of these cases
	die();
}
$fn = tempnam(sys_get_temp_dir(), "CLS");
$file = @fopen($fn, "w");
$lock = flock($file, LOCK_EX);
fwrite($file, json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT));
if($lock) {
	flock($file, LOCK_UN);
}
fclose($file);
$output = [];
exec("python 3.9 classify.py " . $fn . " 2>&1", $output);
header("Content-Type: application/json");
echo json_encode($output, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
?>