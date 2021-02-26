<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ob_start();
session_start();

$result = json_decode(file_get_contents("php://input"), true);

# Ensure that we have all data required:
if(!is_array($result) 
   OR !isset($result["dataSets"], $result["sensors"], $result["features"], $result["scaler"], $result["classifier"]) 
   OR !is_array($result["dataSets"]) 
   OR !is_array($result["sensors"])
   OR !is_array($result["features"])
   OR $_SERVER["REQUEST_METHOD"] !== "POST"
   ) {
	http_response_code(406); # Code 406 stands for "Not Acceptable" which is exactly what our input is in one of these cases
	die();
}

# Close request as the front end does not require any further information from us and we need a very long time for calculating our stuff.
http_response_code(200);
header('Connection: close');
header('Content-Length: '.ob_get_length());
ob_end_flush();
ob_flush();
flush();

# Let's build some ai model.
$fn = tempnam(sys_get_temp_dir(), "BM_");
$file = @fopen($fn, "w");
$lock = flock($file, LOCK_EX);
fwrite($file, json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT));
if($lock) {
	flock($file, LOCK_UN);
}
fclose($file);
$output = exec("python 3.9 classify.py " . $fn);
# TODO build that damn email!!!
# mail();
# E-Mail kommt über 
$sql = "SELECT eMail FROM Admin WHERE userID = {$_SESSION["logged_in"]}";
?>