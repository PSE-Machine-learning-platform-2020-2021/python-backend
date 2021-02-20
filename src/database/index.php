<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ob_start();
session_start();

class DataBaseConnection extends PDO {
    public $last_statement;
    /**
     * Creates a database connection
     * @param $file - a json file containing the following JSON data:
     *                {
     *                    "host": ""
     *                    "db": ""
     *                    "username": ""
     *                    "password": ""
     *                }
     *                Whereof db is the name of the database to connect to.
     *
    */
    public function __construct($file = "config.json") {
        $connection_data = json_decode(file_get_contents($file), true);
        if(!isset($connection_data["host"], $connection_data["db"]) and !isset($connection_data["dns"])) {
            throw new InvalidArgumentException("Config file invalid!");
        }
        if(!isset($connection_data["username"], $connection_data["password"])) {
            throw new InvalidArgumentException("Authentication credentials not included!");
        }
        $dns = "";
        if (isset($connection_data["dns"])) {
            $dns .= $connection_data["dns"];
        }
        else {
            $dns  = (isset($connection_data["driver"])) ? $connection_data["driver"] : "mysql";
            $dns .= ":host=" . $connection_data["host"];
            $dns .= (isset($connection_data["port"])) ? ";port=" . $connection_data["port"] : "";
            $dns .= ";dbname=" . $connection_data["db"];
        }
        parent::__construct($dns, $connection_data["username"], $connection_data["password"]);
        $this->setAttribute(parent::ATTR_ERRMODE, parent::ERRMODE_EXCEPTION);
    }

    /**
	 * Executes mindlessly an SQL query Please use only for queries without input data.
     * @param $what SQL query to execute
	 * @return true if query was successful, false otherwise, as PDOStatement::execute does.
     */
    private function get_data($what) {
        $this->last_statement = $this->prepare($what);
        $result = $this->last_statement->execute();
		$this->last_statement->setFetchMode(parent::FETCH_ASSOC);
		return $result;
    }

	/**
	 * Retrieves meta data about languages from corresponding database table.
	 */
    public function get_language_metas() {
        $sql = "SELECT languageCode, languageName FROM Language;";
        $this->get_data($sql);
		$result = [];
        foreach($this->last_statement->fetchAll() as $k => $v) {
            $result[$k] = $v;
        }
		header("Content-Type: application/json");
        print_r(json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT));
    }
	
	public function load_language($params) {
		$query_suffix = "";
		foreach($params as $k => $v) {
			$query_suffix .= "${k} = \"${v}\" OR";
		}
		$query_suffix = substr($query_suffix, 0, -3);
		$sql = "SELECT language FROM Language WHERE " . $query_suffix;
        $this->get_data($sql);
		$result = $this->last_statement->fetch()["language"];
		header("Content-Type: application/json");
        echo $result;
	}
	
	public function create_project($params) {
		$result = [];
		
		# Create Session id
		$this->last_statement = $this->prepare("INSERT INTO Session () VALUES ()");
		$this->last_statement->execute();
		$result["sessionID"] = $this->lastInsertId();
		
		# Execute the real statement
		$sql = "INSERT INTO Project (adminID, name, sessionID) VALUES (?, ?, ?)";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->bindValue(1, $params["userID"], PDO::PARAM_INT);
		$this->last_statement->bindValue(2, $params["projectName"]);
		$this->last_statement->bindValue(3, $result["sessionID"], PDO::PARAM_INT);
		$this->last_statement->execute();
		$result["projectID"] = $this->lastInsertId();
		
		# Print out result
		header("Content-Type: application/json");
        echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);	
	}
	
	/**
	 * This function inserts a new Datasets together with its datarows in the according tables.
	 * @param sessionID   - the id of the current Session to compare it against the given project id for verification purposes.
	 *                      It is also used to retrieve the id of the admin of the project passed in projectID
	 * @param projectID   - the project id to store in the dataset for better retrieving it later
	 * @param userID      - the id of the user creating this dataset
	 * @param dataSetName - the name of the dataset
	 * @param dataRow     - a list of datarows belonging to this dataset.
	 */
	public function create_data_set($params) {
		# Execute statement creating the dataset.
		$sql = "INSERT INTO Dataset (projectID, userID, dataSetName, projectAdminID) VALUES (?, ?, ?, ?)";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->bindValue(1, $params["projectID"], PDO::PARAM_INT);
		$this->last_statement->bindValue(2, $params["userID"], PDO::PARAM_INT);
		$this->last_statement->bindValue(3, $params["dataSetName"]);
		$this->last_statement->bindValue(4, $params["sessionID"], PDO::PARAM_INT);
		$this->last_statement->execute();
		$result = $this->lastInsertId();
		
		# Build and execute the statement creating empty datarows for this dataset
		$sql = "INSERT INTO Datarow (datasetID, name, sensorID, dataJSON) VALUES";
		$first = true;
		$values = [];
		foreach($params["dataRow"] as $dr) {
			if(!$first) {
				$sql .= ",";
			}
			else {
				$values[] = null;
				$first = false;
			}
			$sql .= " ($result, ?, ?, '[]')";
			$values[] = (isset($dr["datarowName")) ? $dr["datarowName"] : null;
			$values[] = $dr["sensorID"];
		}
		$this->last_statement = $this->prepare($sql);
		for($i = 1; $i < count($values) - 1; $i += 2) {
			$this->last_statement->bindValue($i, $values[i]);
			$this->last_statement->bindValue($i + 1, $values[i + 1], PDO::PARAM_INT);
		}
		$this->last_statement->execute();
		
		# Print out result
		header("Content-Type: application/json");
		echo '{' . $result . '}';
	}
	
	public function send_data_point($params) {
		# Get data to update
		$sql = "SELECT dataJSON FROM Datarow WHERE datarowID = ${params["dataRowID"]} AND datasetID = ${params["dataSetID"]}";
		$this->get_data($sql);
		
		# Add new data point
		$data = json_decode($this->last_statement->fetch()["dataJSON"], true);
		$data[] = $params["datapoint"];
		$data = json_encode($data, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
		
		#  Update table
		$sql = "UPDATE Datarow SET dataJSON = ${data} WHERE datarowID = ${params["dataRowID"]} AND datasetID = ${params["dataSetID"]}";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->execute();
		header("Content-Type: application/json");
        echo '{"result": ' . ($this->last_statement->rowCount() == 1) . '}';
	}
	
	public function load_project($params) {
		# Load the project itself.
		$sql = "SELECT projectID, sessionID, name AS projectName FROM Project WHERE projectID = ${params["projectID"]} AND adminID = ${params["userID"]};";
		$this->get_data($sql);
		$result = $this->last_statement->fetch();
		
		#Load the ai models associated with the loaded project.
		$sql = "SELECT aiModelID AS id FROM AIModel WHERE projectID = ${params["projectID"]} AND projectAdminID = ${params["userID"]}";
		$this->get_data($sql);
		$result["aiModelID"] = [];
		foreach($this->last_statement->fetchAll() as $ai_model) {
			$result["aiModelID"][] = $ai_model["id"];
		}
		
		# Load the datasets associated with the loaded project.
		$result["dataSet"] = [];
		$sql = "SELECT datasetID AS dataSetID, dataSetName, generateDate FROM Dataset WHERE projectID = ${params["projectID"]} AND projectAdminID = ${params["userID"]};";
		$this->get_data($sql);
		foreach($this->last_statement->fetchAll() as $data_set) {
			# Load data rows associated with each loaded data set.
			$sql = "SELECT datarowID AS id, dataJSON AS json FROM Datarow WHERE datasetID = ${data_set["dataSetID"]}";
			$stmt1 = $this->prepare($sql);
			$stmt1->execute();
			$data_rows = [];
			foreach($stmt1->fetchAll() as $dr) {
				$data_rows[] = array("dataRowID" => $dr["id"], "recordingStart" => -1, "dataRow" => json_decode($dr["json"]));
			}
			
			# Load all the sensors from the data rows.
			$sql = "SELECT * FROM Sensor WHERE sensorID IN (SELECT sensorID FROM Datarow WHERE datasetID = ${data_set["dataSetID"]})";
			$stmt2 = $this->prepare($sql);
			$stmt2->execute();
			$data_row_sensors = $stmt2->fetchAll();
			
			# Load all the labels belonging to each loaded data set.
			$sql = "SELECT name, labelID, start, end FROM Label WHERE datasetID = ${data_set["dataSetID"]}";
			$stmt3 = $this->prepare($sql);
			$stmt3->execute();
			$labels = $stmt3->fetchAll();
			
			# Put everything together.
			$result["dataSet"][] = array("dataRowSensors" => $data_row_sensors, 
			                             "dataSetId" => $data_set["dataSetID"], 
										 "dataSetName" => $data_set["dataSetName"], 
										 "generateDate" => $data_set["generateDate"], 
										 "dataRows" => $data_rows, 
										 "label" => $labels
								   );
		}
		
		# Print out result.
		header("Content-Type: application/json");
        echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}
	
	/**
	 * Requests all project with all affiliated data belonging to a specific user.
	 * @param userID - The number associated with the specific user from description.
	 * @return All projects.
	 */
	public function get_project_metas($params) {
		$result = [];
		$sql = "SELECT projectID, name as projectName FROM Project WHERE adminID = ${params["userID"]}";
		$this->get_data($sql);
		foreach($this->last_statement->fetchAll(); as $v) {
			$r = [];
			$r = array_merge($r, $v);
			$sql = "SELECT aiModelID as AIModelID FROM AIModel WHERE projectID = ${v["projectID"]} AND projectAdminID = ${params["userID"]}";
			$this->get_data($sql);
			$ai_model_ids = [];
			foreach($this->last_statement->fetchAll(PDO::FETCH_NUM); as $id) {
				$ai_model_ids[] = $id[0];
			}
			$r["AIModelID"] = $ai_model_ids;
			$result[] = $r;
		}
		
		# Print out result.
		header("Content-Type: application/json");
        echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}
	
	public function delete_data_set($params) {
		# Build and execute statement
		$sql = "DELETE FROM Datarow WHERE datasetID = ${params["dataSetID"]};\r\n";
		$sql .= "DELETE FROM Dataset WHERE datasetID = ${params["dataSetID"]} AND userID = ${params["userID"]} AND projectID = ${params["projectID"]}";
		$result = $this->get_data($sql);
		
		# Print out result.
		header("Content-Type: application/json");
        echo '{"result": ' . $result . '}';
	}
	
	public function register_admin($params) {		
		$result = [];
		
		# Build and execute statement.
		$sql = "INSERT INTO User (name) VALUES (?);";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->bindValue(1, $params["adminName"]);
		$this->execute();
		$result["adminID"] = $this->lastInsertId();
		$sql = "INSERT INTO Admin (userID, password, eMail) VALUES (?, ?, ?);";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->bindValue(1, $result["adminID"], PDO::PARAM_INT);
		$this->last_statement->bindValue(2, password_hash($params["password"]));
		$this->last_statement->bindValue(3, $params["adminEmail"]);
		$this->last_statement->execute();
		
		$result["device"] = register_device($params["device"], $result["dataminerID"]);
				
		# Print out result.
		header("Content-Type: application/json");
        echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}
	
	public function register_dataminer($params) {
		$result = [];
		
		# Build and execute registration statement
		$sql = "INSERT INTO User (name) VALUES (?);"
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->bindValue(1, $params["dataminerName"]);
		$this->last_statement->execute();
		$result["dataminerID"] = $this->lastInsertId();
		
		# Build and execute 'get project' statement
		$sql = "SELECT projectID, name as projectName, sessionID FROM Project WHERE sessionID = ${params["sessionID"]};";
		$this->get_data($sql);
		$result["project"] = $this->last_statement->fetch();
		
		$result["device"] = register_device($params["device"], $result["dataminerID"]);
		
		# Print out result.
		header("Content-Type: application/json");
		echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}
	
# TODO Add possibility to registrate	
#	public function register_ai_model_user($params) {
#		$result["device"] = register_device($params["device"], $result["dataminerID"]);
#
#		# Print out result.
#		header("Content-Type: application/json");
#		echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
#	}
	
	/**
	 * Creates a new device in the Device table.
	 *
	 * @param deviceName        - the name of the device
	 * @param deviceType        - the specific type of the device
	 * @param firmware          - the firmware of the device
	 * @param generation        - the generation of the device
	 * @param MACADRESS         - the MAC address of the device
	 * @param sensorInformation - an array containing some information about the device's sensors. 
	 * @return the device id and the global sensor ids
	 */
	private function register_device($params, $user_id) {
		$sql = "SELECT deviceID, userID FROM Device WHERE MACADDRESS = ${params["MACADRESS"]}";
		$this->get_data($sql);
		foreach($this->last_statement->fetchAll() as $row) {
			if ($user_id === $row["userID"] {
				$sql = "SELECT sensorID FROM Sensor WHERE deviceID = ${row["deviceID"]}";
				$stmt = $this->prepare($sql);
				$stmt->execute();
				$result = [];
				foreach($stmt->fetchAll() as $sensor) {
					$result[] = $sensor["sensorID"];
				}
				return array("deviceID" => $row["deviceID"], "sensorID" => $result);
			}
			return array("deviceID" => -1, "sensorID" => array(-1));
		}
		
		# Create Device entry.		
		$sql = "INSERT INTO Device (firmware, generation, MACADDRESS, name, type, userID) VALUES (?, ?, ?, ?, ?, ?)";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->bindValue(1, $params["firmware"]);
		$this->last_statement->bindValue(2, $params["generation"]);
		$this->last_statement->bindValue(3, $params["MACADRESS"]);
		$this->last_statement->bindValue(4, $params["deviceName"]);
		$this->last_statement->bindValue(5, $params["deviceType"]);
		$this->last_statement->bindValue(6, $user_id, PDO::PARAM_INT);
		$this->last_statement->execute();
		$result["deviceID"] = $this->lastInsertId();
		
		# Create sensor information
		$result["sensorID"] = [];
		foreach($params["sensorInformation"] as $sensor) {
			$sql = "INSERT INTO Sensor (sensorTypeID, sensorName, deviceUniqueSensorID, deviceID) VALUES (?, ?, ?, ?)";
			$stmt = $this->prepare($sql);
			$stmt->bindValue(1, $sensor["sensorTypeID"]);
			$stmt->bindValue(2, $sensor["sensorName"]);
			$stmt->bindValue(3, $sensor["deviceUniqueSensorID"]);
			$stmt->bindValue(4, $result["deviceID"]);
			$stmt->execute();
			$result["sensorID"][] = $this->lastInsertId();
		}
		
		return $result;
	}

	public function login_admin($params) {
		$result = [];
		
		# Build and execute data comparing statement
		$sql = "SELECT userID, password FROM Admin WHERE eMail = ${params["adminEmail"]};";
		$this->get_data($sql);
		
		foreach($this->last_statement->fetchAll() as $row) {
			if(password_verify($params["password"], $row["password"])) {
				$sql = "SELECT userID AS adminID, eMail AS email, name AS adminName, deviceID FROM Admin, User, Device WHERE userID = ${row["userID"]};";
				$this->get_data($sql);
				$result["admin"] = $this->last_statement->fetch();
				break;
			}
		}
		
		# Print out result.
		header("Content-Type: application/json");
		echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}
	
	public function create_label($params) {

		$sql = "INSERT INTO Label (datasetID, name, start, end) VALUES (?, ?, ?, ?);";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->bindValue(1, $params["datasetID"], PDO::PARAM_INT);
		$this->last_statement->bindValue(2, $params["label"]["labelName"]);
		$this->last_statement->bindValue(3, $params["label"]["span"]["start"], PDO::PARAM_INT);
		$this->last_statement->bindValue(4, $params["label"]["span"]["end"], PDO::PARAM_INT);
		$this->last_statement->execute();

		header("Content-Type: application/json");
		echo '{"labelID": ' . $this->lastInsertId() . '}';
	}
	
	public function set_label($params) {		
		$sql_prefix = "UPDATE Label SET ";
		$sql_suffix = " WHERE datasetID = ${params["datasetID"]} AND labelID = ${params["label"]["labelID"]}";
		$result = [];
		
		# Handle updating in three spearate non-exclusive if clauses to reduce complexity to a minimum.
		if(isset($params["label"]["labelName"])) {
			$sql = $sql_prefix . "name = ?" . $sql_suffix;
			$this->last_statement = $this->prepare($sql);
			$this->last_statement->bindValue(1, $params["label"]["labelName"]);
			$result[] = $this->last_statement->execute();
		}
		if(isset($params["label"]["span"]["start"])) {
			$sql = $sql_prefix . "start = ?" . $sql_suffix;
			$this->last_statement = $this->prepare($sql);
			$this->last_statement->bindValue(1, $params["label"]["span"]["start"], PDO::PARAM_INT);
			$result[] = $this->last_statement->execute();
		}
		if(isset($params["label"]["span"]["end"])) {
			$sql = $sql_prefix . "end = ?" . $sql_suffix;
			$this->last_statement = $this->prepare($sql);
			$this->last_statement->bindValue(1, $params["label"]["span"]["end"], PDO::PARAM_INT);
			$result[] = $this->last_statement->execute();
		}
		
		# Return false as soon as at least one query fails.
		header("Content-Type: application/json");
		echo '{"success": ' . !in_array(false, $result, true) . '}';
	}
	
	public function delete_label($params) {
		# Build and execute statement
		$sql = "DELETE FROM Label WHERE datasetID = ${params["dataSetID"]} AND labelID = ${params["labelID"]}";
		$result = $this->get_data($sql);
		
		# Print out result.
		header("Content-Type: application/json");
        echo '{"result": ' . $result . '}';
	}
}

# Stop idle Script calls.
if(!isset($_GET["action"])) {
    throw new InvalidArgumentException("Job not specified!");
}

$db = new DataBaseConnection();

# Ensure that always a good value in $_POST
if(($result = json_decode(file_get_contents("php://input"), true)) !== null) {
	$_POST = $result;
}
else if($_POST === null or !is_array($_POST)) {
	$_POST = [];
}

# Ensure that only valid tasks are executed
switch($_GET["action"]) {
    case "get_language_metas":
	    eval("\$db->${_GET["action"]}();");
        break;
    case "load_language":
	case "create_project":
	case "create_data_set":
	case "send_data_point":
	case "load_project":
	case "get_project_metas":
	case "delete_data_set":
	case "register_admin":
	case "register_dataminer":
#	case "register_ai_model_user":
	case "login_admin":
	case "create_label":
	case "set_label":
	case "delete_label":
		eval("\$db->${_GET["action"]}(\$_POST);");
		break;
	default:
        throw new BadMethodCallException("This value is illegal. Intelligence Agency is informed.");
}
ob_end_flush();
ob_flush();
flush();
?>
