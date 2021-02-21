<?php 
if($_SERVER["REQUEST_METHOD"] !== "POST") {
	die('{"url":""}');
}
error_reporting(E_ALL);
ini_set("display_errors", 1);
ob_start();
session_start();

include("databaseConnection.php");

$db = new DataBaseConnection();
$input = json_decode(file_get_contents("php://input"), true);
if ($input === null) {
	die('{"url":""}');
}
?>