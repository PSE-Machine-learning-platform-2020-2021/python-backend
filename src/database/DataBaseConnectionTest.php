<?
use PHPUnit\Framework\TestCase;
include("databaseConnection.php");

final class DataBaseConnectionTest extends TestCase {
	private static $db;
	
	public static function setUpBeforeClass(): void {
		self::$db = new DataBaseConnection();
	}
	
	public static function tearDownAfterClass(): void {
		self::$db = null;
	}
	
	public function test_get_language_metas(): void {	
		@self::$db->get_language_metas();
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertGreaterThanOrEqual(1, count($data));
		foreach($data as $row) {
			$this->assertArrayHasKey("languageCode", $row);
			$this->assertIsString($row["languageCode"]);
			$this->assertArrayHasKey("languageName", $row);
			$this->assertIsString($row["languageName"]);
		}
	}
	
	public function test_load_language_1(): void {
		@self::$db->load_language(array("languageCode" => "DE-de"));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		foreach($data as $value) {
			$this->assertIsString($value);
		}
	}
	
	public function test_load_language_2(): void {
		@self::$db->load_language(array("languageCode" => 713));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertArrayHasKey("error", $data);
		$this->assertIsArray($data["error"]);
		$this->assertCount(1, $data["error"]);
	}
	
	public function test_load_language_3(): void {
		@self::$db->load_language(array());
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertArrayHasKey("error", $data);
		$this->assertIsArray($data["error"]);
		$this->assertCount(1, $data["error"]);
	}
	
	public function test_create_project_1(): void {
		@self::$db->create_project(array("userID" => 53, "projectName" => "PHP-TEST"));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertArrayHasKey("sessionID", $data);
		$this->assertIsInt($data["sessionID"]);
		$this->assertGreaterThanOrEqual(1, $data["sessionID"]);
		$this->assertArrayHasKey("projectID", $data);
		$this->assertIsInt($data["projectID"]);
		$this->assertGreaterThanOrEqual(1, $data["projectID"]);
	}
	
	public function test_create_project_2(): void {
		@self::$db->create_project(array("userID" => false, "projectName" => -1));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertArrayHasKey("error", $data);
		$this->assertIsArray($data["error"]);
		$this->assertCount(2, $data["error"]);
	}
	
	public function test_create_project_3(): void {
		@self::$db->create_project(array());
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertArrayHasKey("error", $data);
		$this->assertIsArray($data["error"]);
		$this->assertCount(2, $data["error"]);
	}
	
	public function test_create_data_set(): void {
		@self::$db->create_data_set(array("userID" => 9, "projectID" => 13, "sessionID" => 100, "dataSetName" => "PHP-TEST-1", "dataRow" => array(array("datarowName" => "T-14-1 1", "sensorID" => 42))));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertArrayHasKey("dataSetID", $data);
		$this->assertIsInt($data["dataSetID"]);
		$this->assertGreaterThanOrEqual(1, $data["dataSetID"]);
	}
	
	public function test_send_data_point(): void {
		@self::$db->send_data_point(array("dataRowID" => 1, "dataSetID" => 0, "sessionID" => 100, "datapoint" => array("value" => [1.0], "relativeTime" => 0.625)));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertArrayHasKey("result", $data);
		$this->assertIsBool($data["result"]);
	}
	
	public function test_send_data_points_again(): void {
		@self::$db->send_data_points_again(array("dataRowID" => 1, "dataSetID" => 0, "sessionID" => 100, "datapoints" => array(array("value" => [1.0], "relativeTime" => 0.625))));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertArrayHasKey("result", $data);
		$this->assertIsBool($data["result"]);
	}
	
	public function test_load_project(): void {
		@self::$db->load_project(array("userID" => 9, "projectID" => 13));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertTrue(isset($data["projectID"], $data["sessionID"], $data["projectName"], $data["projectData"]));
		$this->assertIsInt($data["projectID"]);
		$this->assertIsInt($data["sessionID"]);
		$this->assertIsString($data["projectName"]);
		$this->assertIsArray($data["projectData"]);
		
		$data = $data["projectData"];
		$this->assertTrue(isset($data["aiModelID"], $data["dataSet"]));
		$this->assertIsArray($data["aiModelID"]);
		$this->assertIsArray($data["dataSet"]);

		$data = $data["dataSet"];
		foreach($data as $set) {
			$this->assertTrue(isset($set["dataRowSensors"], $set["dataSetId"], $set["dataSetName"], $set["generateDate"], $set["dataRows"], $set["label"]));
			$this->assertIsInt($set["dataSetId"]);
			$this->assertIsString($set["dataSetName"]);
			$this->assertIsInt($set["generateDate"]);
			$this->assertIsArray($set["dataRowSensors"]);
			foreach($set["dataRowSensors"] as $sensor) {
				$this->assertTrue(isset($sensor["sensorID"], $sensor["deviceUniqueSensorID"], $sensor["sensorTypeID"], $sensor["sensorName"], $sensor["deviceID"]));
				$this->assertIsInt($sensor["sensorID"]);
				$this->assertIsInt($sensor["deviceUniqueSensorID"]);
				$this->assertIsInt($sensor["sensorTypeID"]);
				$this->assertIsString($sensor["sensorName"]);
				$this->assertIsInt($sensor["deviceID"]);
			}
			$this->assertIsArray($set["dataRows"]);
			foreach($set["dataRows"] as $row) {
				$this->assertTrue(isset($row["dataRowID"], $row["recordingStart"], $sensor["dataRow"]));
				$this->assertIsInt($sensor["dataRowID"]);
				$this->assertEquals($sensor["recordingStart"], -1);
				$this->assertIsArray($sensor["dataRow"]);
			}
			$this->assertIsArray($set["label"]);
			foreach($set["label"] as $label) {
				$this->assertTrue(isset($label["name"], $label["labelID"], $label["start"], $label["end"]));
				$this->assertIsString($label["name"]);
				$this->assertIsInt($label["labelID"]);
				$this->assertIsInt($label["start"]);
				$this->assertIsInt($label["end"]);
			}
		}

	}
	
	public function test_get_project_metas(): void {
		@self::$db->get_project_metas(array("userID" => 53));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		foreach($data as $project) {
			$this->assertTrue(isset($project["projectID"], $project["projectName"], $project["AIModelID"]));
			$this->assertIsInt($project["projectID"]);
			$this->assertIsString($project["projectName"]);
			$this->assertIsArray($project["AIModelID"]);
		}
	}
	
	public function test_delete_data_set(): void {
		@self::$db->delete_data_set(array("userID" => 53, "dataSetID" => 27, "projectID" => 13));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertArrayHasKey("result", $data);
		$this->assertIsBool($data["result"]);

	}
	
	public function test_register_admin(): void {
		@self::$db->register_admin(array("adminEmail" => bin2hex(random_bytes(16)) . "@example.com", "adminName" => "John Doe", "password" => "fixi", "device" => array(
			"deviceName" => "KV-2", "deviceType" => "Soviet Heavy Tank", "firmware" => "152 mm U-11", "generation" => "WW2", "MACADRESS" => "", "sensorInformation"=> array(array(
			"sensorTypeID" => 1, "sensorName" => "RK-10", "deviceUniqueSensorID" => 0)))));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
		$this->assertArrayHasKey("adminID", $data);
		$this->assertIsInt($data["adminID"]);
		$this->assertArrayHasKey("device", $data);
		$this->assertIsArray($data["device"]);
		$this->assertCount(2, $data["device"]);
		$this->assertArrayHasKey("deviceID", $data["device"]);
		$this->assertIsInt($data["device"]["deviceID"]);
		$this->assertArrayHasKey("sensorID", $data["device"]);
		$this->assertIsArray($data["device"]["sensorID"]);
		foreach($data["device"]["sensorID"] as $sensor) {
			$this->assertIsInt($sensor);
		}
	}
	
	public function test_register_dataminer(): void {
		@self::$db->register_dataminer(array("dataminerName" => "Otto Normalverbraucher", "sessionID" => 13, "device" => array(
			"deviceName" => "ISU-152", "deviceType" => "Soviet Tank Destroyer", "firmware" => "152 mm BL-10", "generation" => "WW2", "MACADRESS" => "", "sensorInformation" => array(array(
			"sensorTypeID" => 1, "sensorName" => "RK-13", "deviceUniqueSensorID" => 0)))));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(3, $data);
		$this->assertArrayHasKey("dataminerID", $data);
		$this->assertIsInt($data["dataminerID"]);
		$this->assertArrayHasKey("project", $data);
		$this->assertIsArray($data["project"]);
		$this->assertArrayHasKey("projectID", $data["project"]);
		$this->assertIsInt($data["project"]["projectID"]);
		$this->assertArrayHasKey("projectName", $data["project"]);
		$this->assertIsString($data["project"]["projectName"]);
		$this->assertArrayHasKey("sessionID", $data["project"]);
		$this->assertIsInt($data["project"]["sessionID"]);
		$this->assertArrayHasKey("device", $data);
		$this->assertIsArray($data["device"]);
		$this->assertCount(2, $data["device"]);
		$this->assertArrayHasKey("deviceID", $data["device"]);
		$this->assertIsInt($data["device"]["deviceID"]);
		$this->assertArrayHasKey("sensorID", $data["device"]);
		$this->assertIsArray($data["device"]["sensorID"]);
		foreach($data["device"]["sensorID"] as $sensor) {
			$this->assertIsInt($sensor);
		}
	}
	
	public function test_login_admin(): void {
		@self::$db->login_admin(array("adminEmail" => "Neu@gmail.com", "password" => "neu"));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertArrayHasKey("admin", $data);
		$this->assertIsArray($data["admin"]);
		
		$data = $data["admin"];
		$this->assertArrayHasKey("adminID", $data);
		$this->assertIsInt($data["adminID"]);
		$this->assertArrayHasKey("email", $data);
		$this->assertIsString($data["email"]);
		$this->assertArrayHasKey("adminName", $data);
		$this->assertIsString($data["adminName"]);
		$this->assertArrayHasKey("deviceID", $data);
		$this->assertIsInt($data["deviceID"]);
	}
	
	public function test_create_label(): void {
		@self::$db->create_label(array("datasetID" => 36, "label" => array("labelName" => "PHP-TEST-LABEL-1", "span" => array("start" => 0.125, "end" => 1.125))));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertArrayHasKey("labelID", $data);
		$this->assertIsInt($data["labelID"]);
	}
	
	public function test_delete_label(): void {
		@self::$db->delete_label(array("dataSetID" => 36, "labelID" => 42));
		$output = $this->getActualOutput();
		$data = json_decode($output, true);
		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertArrayHasKey("result", $data);
		$this->assertIsBool($data["result"]);
	}
	
	public function test_get_email(): void {
		$result = self::$db->get_email(53);
		$this->assertIsArray($result);
		$this->assertArrayHasKey("name", $result);
		$this->assertIsString($result["name"]);
		$this->assertArrayHasKey("email", $result);
		$this->assertIsString($result["email"]);
	}
}
?>