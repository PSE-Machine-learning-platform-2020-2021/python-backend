<?php 
error_reporting(E_ALL);
ini_set("display_errors", 1);
ob_start();
session_start();

# Ensure that we have all data required:
if $_SERVER["REQUEST_METHOD"] !== "POST" 
   OR !isset($result["job"], $result["id"])
   OR (!is_array($result) 
   ) {
	http_response_code(406); # Code 406 stands for "Not Acceptable" which is exactly what our input is in one of these cases
	die("{}");
}

$result = json_decode(file_get_contents("php://input"), true);

# Get E-mail address
require("../database/databaseConnection.php");
$db = new DatabaseConnection();
$address = $db->get_email($_SESSION["logged_in"]);
$sensor_types = implode(",", $db->get_sensor_types($result["id"]));

/**
 * Sends emails to recipients, informing them about a new model ready to their use.
 */
function send(): array {
	$addressList = [];
	if (array_key_exists("recipients", $result) AND is_array($result["recipients"])) {
		$addressList = $result["recipients"];
		$addressList[] = $address;
	}
	else {
		return ["result" => false];
	}
	
	# Set up Mailer and Mail server (maybe I should encapsulate this some day into a function).
	require('PHPMailer-5.2-stable/PHPMailerAutoload.php');
	$mailer = new PHPMailer();
	$mailer->CharSet = "UTF-8";
	$mailer->isSMTP();
	$mailer->Host = "mail.teco.edu";

	# Send email.
	$mailer->From = "no-reply@pse-w2020-t2.dmz.teco.edu";
	$mailer->FromName = "KI-App";
	$mailer->isHTML();
	$mailer->Subject = "Ein KI-Modell wurde Ihnen zur Nutzung freigegeben."; # Needs Inlcusion of the corresponding Texts!
	$mailer->Body = "<p>" . "Bitte folgen Sie diesem Link, um das KI-Modell anzuwenden:" . " <a href=\"https://129.13.170.59/build?useModel=true&modelID={$result["id"]}&sensorTypes={$sensor_types}\">" . "Startseite" . "</a>.</p><p>" . "Mit freundlichen Grüßen, <br />Ihre KI-App." . "</p>";
	foreach ($addressList as $address) {
		if (!isset($address["name"])) {
			$address["name"] = $address["email"];
		}
		$mailer->AddAddress($address["email"], $address["name"]);
		$mailer->Send();
		$mailer->ClearAllRecipients();
	}
	return ["result" => true];
}

/**
 * Returns the link to the AI model in WEB mode and the same in EXE mode.
 */
function get() {
	if (!array_key_exists("format", $result)) {
		return [];
	}
	switch($result["format"]) {
		case "EXE":
			# Not implemented.
		case "WEB_APP":
			return ["url" => "https://129.13.170.59/build?useModel=true&modelID={$result["id"]}&sensorTypes={$sensor_types}"];
		default:
			return [];
	}
}

$output = [];
switch($result["job"]) {
	case "get":
	case "send":
		echo json_encode(eval("{$result["job"]}();"), JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		break;
	default:
		http_response_code(501);
        echo "{}";
}
?>
