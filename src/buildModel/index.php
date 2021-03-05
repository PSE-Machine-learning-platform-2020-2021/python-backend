<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ob_start();
session_start();

$result = json_decode(file_get_contents("php://input"), true);

# Ensure that we have all data required:
if(!is_array($result) 
   OR !isset($result["dataSets"], $result["features"], $result["scaler"], $result["classifier"], $result["imputator"]) 
   OR !is_array($result["dataSets"]) 
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
$output = exec("python3.9 buildModel.py " . $fn . " 2>&1", $_, $code);

# Get E-mail address
require("../database/databaseConnection.php");
$db = new DatabaseConnection();
$address = $db->get_email($_SESSION["logged_in"]);

# Set up Mailer and Mail server (maybe I should encapsulate this some day into a function).
require('PHPMailer-5.2-stable/PHPMailerAutoload.php');
$mailer = new PHPMailer();
$mailer->CharSet = "UTF-8";
$mailer->isSMTP();
$mailer->Host = "mail.teco.edu";

# Send email.
$mailer->From = "no-reply@pse-w2020-t2.dmz.teco.edu";
$mailer->FromName = "KI-App";
$mailer->AddAddress($address["email"], $address["name"]);
$mailer->isHTML();
if ($code === 0) {
	$mailer->Subject = "Ihr KI-Modell ist fertig"; # Needs Inlcusion of the corresponding Texts!
	$mailer->Body = "<p>Bitte folgen Sie diesem Link, um ihr KI-Modell auszuliefern: <a href=\"https://129.13.170.59/build?deliverModel=true&modelID={$output}\">Auslieferungsseite</a>.</p><p>Mit freundlichen Grüßen, <br />die KI-Modell-Trainingseinheit</p>";
}
else {
	$mailer->Subject = "Fehler in Modell-Erstellung";
	$mailer->Body = "<p>Bei der Erstellung Ihres KI-Modells ist ein Fehler aufgetreten. Der Bau der Anwendung konnte nicht erfolgreich abgeschlossen werden.<br />Bitte wenden Sie sich an Ihren Administrator und übermitteln Sie ihm folgende Daten:<pre>" . implode("\r\n", $_) . "</pre></p></p>Mit freundlichen Grüßen, <br />die KI-Modell-Trainingseinheit</p>";
}
$mailer->Send();
?>