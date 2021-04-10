<?php
class DataBaseConnection extends PDO {
    private $last_statement;
    /**
     * Creates a database connection
	 * Important note for the usage of this class! All functions with parameter $params expect an array containing at least the keys that are specified in the doc string!!!
	 *
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
    public function __construct($file = __DIR__."/config.json") {
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
		$dns .= ";charset=utf8mb4";
        parent::__construct($dns, $connection_data["username"], $connection_data["password"]);
        $this->setAttribute(parent::ATTR_ERRMODE, parent::ERRMODE_EXCEPTION);
    }

    /**
	 * Executes mindlessly an SQL query Please use only for queries without input data.
	 *
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
	 * Checks params by given list of needed params.
	 * 
	 * @param array $needed the list of needed params as assoc array with the keys being the param names and the values their types.
	 * @param int   $line   the line where the params are needed.
	 * @param array $actual the array of actual passed params.
	 * @return array all the error messages, if any.
	 */
	private function check_params(array $needed, int $line, array $actual): array {
		$result = [];
		foreach($needed as $name => $type) {
			if(!isset($actual[$name])) {
				$result[] = "Param {$name} not set in " . __FILE__ . " on line {$line}.";
			}
			elseif(gettype($actual[$name]) !== $type) {
				$result[] = "Param {$name} has not type {$type}, but type " . gettype($actual[$name]) . " in " . __FILE__ . " on line {$line}.";
			}
		}
		return $result;
	}

	/**
	 * Retrieves meta data about languages from corresponding database table.
	 
	 * @return void Therefore, it has print output in JSON format, as follows. Each Language has its own list entry.
	 * 		[
	 *			{"languageCode": "xx-xx", "languageName":"xxxxx"}
	 * 		]
	 */
    public function get_language_metas() {
        $sql = "SELECT languageCode, languageName FROM Language;";
        $this->get_data($sql);
		$result = [];
        foreach($this->last_statement->fetchAll() as $k => $v) {
            $result[$k] = $v;
        }
		header("Content-Type: application/json");
        echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
	
	/**
	 * Retrieves all stored data associated to the values passed as params
	 
	 * @param string languageCode The iso code of the language in form xx-xx
	 * @return void  It prints the contents of the field 'language' of the first database entry matching to languageCode. This is a list containing row by row some texts. If the param is not set or its type not correct, an error message in json format is printed instead.
	 */
	public function load_language($params) {
		header("Content-Type: application/json");
		$error = $this->check_params(["languageCode" => "string"], 100, $params);
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}
		$sql = "SELECT language FROM Language WHERE languageCode = \"{$params["languageCode"]}\"";
        $this->get_data($sql);
		$result = $this->last_statement->fetch()["language"];
        echo $result;
	}
	
	/**
	 * Creates a new entry in project database table.
	 *
	 * @param int 	 userID		 The id of the current user's database entry in the user table.
	 * @param string projectName THe name of the new project.
	 * @return void  Prints an json object as seen below. If the params are not set or their types not correct, error messages in json format are printed instead.
	 *		{
	 *			"sessionID": 1,
	 *			"projectID": 1
	 *		}
	 */
	public function create_project($params) {
		header("Content-Type: application/json");
		$error = $this->check_params(["userID" => "integer", "projectName" => "string"], 124, $params);
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}
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
        echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);	
	}
	
	/**
	 * This function inserts a new Dataset together with its datarows in the according database tables.
	 
	 * @param int    sessionID   - the id of the current Session to compare it against the given project id for verification purposes.
	 *            	               It is also used to retrieve the id of the admin of the project passed in projectID
	 * @param int    projectID   - the project id to store in the dataset for better retrieving it later
	 * @param int    userID      - the id of the user creating this dataset
	 * @param string dataSetName - the name of the dataset
	 * @param array  dataRow     - a list of datarows belonging to this dataset. it has to come in following format:
	 * 		[
	 *			[
	 *				"datarowName": "xxx", 	// optional
	 *				"sensorID": 1 			// use sensorTypeID!
	 *			]
	 *		]
	 * @return void  Prints a json array with the ID of the newly created data set, as described below or json formatted error messages if the parameters don't fit.
	 *		{
	 * 			"dataSetID": 1
	 *		}
	 */
	public function create_data_set($params) {
		header("Content-Type: application/json");
		$error = $this->check_params(["sessionID" => "integer", "projectID" => "integer", "userID" => "integer", "dataSetName" => "string", "dataRow" => "array"], 171, $params);
		if(isset($params["dataRow"]) and is_array($params["dataRow"])) {
			foreach($params["dataRow"] as $id => $dr) {
				$error = array_merge($error, $this->check_params([$id => "array"], 171, $params["dataRow"]));
				if(isset($dr) and is_array($dr)) {
					$error = array_merge($error, $this->check_params(["sensorID" => "integer"], 171, $dr));
				}
			}
		}
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}
		
		$result = [];
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
		$sql = "INSERT INTO Datarow (datarowID, datasetID, name, sensorID, dataJSON) VALUES";
		$first = true;
		$values = [];
		foreach($params["dataRow"] as $id => $dr) {
			if(!$first) {
				$sql .= ",";
			}
			else {
				$values[] = null;
				$first = false;
			}
			$sql .= " (?, $result, ?, ?, '[]')";
			$values[] = $id;
			$values[] = (isset($dr["datarowName"])) ? $dr["datarowName"] : "";
			$values[] = $dr["sensorID"];
		}
		$this->last_statement = $this->prepare($sql);
		for($i = 1; $i < count($values) - 2; $i += 3) {
			$this->last_statement->bindValue($i, $values[$i], PDO::PARAM_INT);
			$this->last_statement->bindValue($i + 1, $values[$i + 1]);
			$this->last_statement->bindValue($i + 2, $values[$i + 2], PDO::PARAM_INT);
		}
		$this->last_statement->execute();
		
		# Print out result
 		echo '{"dataSetID": ' . $result . '}';
	}
	
	/**
	 * Updates the data row specified in dataRowID by a new value associated with a relative time value.
	 *
	 * @param int   dataRowID the data row to update
	 * @param int   dataSetID the data set the data row belongs to.
	 * @param array datapoint the new data point as in following format. Note that both values are required to be floats.
	 * 		[
	 *			"value": [1.0],
	 * 			"relativeTime": 1.0
	 *		]
	 * @return void            Please see the documentation of update_data_row for further details.
	 */
	public function send_data_point($params) {
		$error = $this->check_params(["dataRowID" => "integer", "dataSetID" => "integer", "datapoint" => "array"], 239, $params);
		if(isset($params["datapoint"]) and is_array($params["datapoint"])) {
			$error = array_merge($error, $this->check_params(["value" => "array", "relativeTime" => "double"], 239, $params["datapoint"]));
		}
		header("Content-Type: application/json");
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}
		# Get data to update
		$sql = "SELECT dataJSON FROM Datarow WHERE datarowID = {$params["dataRowID"]} AND datasetID = {$params["dataSetID"]}";
		$this->get_data($sql);
	
		# Add new data point
		$data = json_decode($this->last_statement->fetch()["dataJSON"], true);
		$data[] = $params["datapoint"];
		$this->update_data_row($params["dataSetID"], $params["dataRowID"], $data);
	}
	
	/**
	 * Updates the data row specified in dataRowID by setting the data field to the object passed in the parameter datapoints.
	 *
	 * @param int   dataRowID  the data row to update
	 * @param int   dataSetID  the data set the data row belongs to.
	 * @param array datapoints An array that is transformed to json and then inserted into the data row. THERE IS NO CONTENT CHECKING FOR THIS PARAMETER!
	 * @return void            Please see the documentation of update_data_row for further details.
	 */
	public function send_data_points_again($params) {
		$error = $this->check_params(["dataRowID" => "integer", "dataSetID" => "integer", "datapoints" => "array"], 267, $params);
		header("Content-Type: application/json");
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}
		$this->update_data_row($params["dataSetID"], $params["dataRowID"], $params["datapoints"]);
	}
	
	/**
	 * This method combines the database updating part of the two quite similar functions send_data_point and send_data_points_again.
	 *
	 * @param int   $set  The id number of the data set of the data row to update
	 * @param int   $row  The id number of the data row to update
	 * @param array $data The actual data to put into the data row on update.
	 * @return void  Prints a json formatted object with an only member result indicating successful datapoint insertion. Prints instead json formatted error messages if the params do not match in any way.
	 */
	private function update_data_row(int $set, int $row, array $data) {
		$data = json_encode($data, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
		$sql = "UPDATE Datarow SET dataJSON = '{$data}' WHERE datarowID = {$row} AND datasetID = {$set}";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->execute();
        echo '{"result": ' . (($this->last_statement->rowCount() == 1) ? 'true' : 'false') . '}';
	}
	
	/**
	 * Loads a project from the project database table. It is identified via project and user id.
	 *
	 * @param int   projectID the id of the project in its table
	 * @param int   userID    the id of the user owning that specific project
	 * @return void Prints a json formatted object in a format as described below. Prints instead json formatted error messages if the params do not match in any way.
	 * {
	 *		"projectID": 1,
	 *		"sessionID": 1,
	 *		"projectName": "xxx",
	 *		"projectData": {
	 *			"aiModelID": [
	 *				1
	 *			],
	 *			"dataSet": [
	 *				{
	 *					"dataRowSensors": [
	 *						{
	 * 							"sensorID": 1,
	 *							"deviceUniqueSensorID": 1,
	 *							"sensorTypeID": 1,
	 *							"sensorName": "xxx",
	 *							"deviceID": 1
	 *						}
	 *					],
	 *					"dataSetID": 1,
	 *					"dataSetName": "xxx",
	 *					"generateDate": 1,	// as UNIX Timestamp
	 *					"dataRows": [
	 *						{
	 *							"dataRowID": 1,
	 *							"recordingStart": -1, // hard value as it is not av. in db
	 *							"dataRow": [
	 *								{"value": 1.0, "relativeTime": 1.0}
	 *							]
	 *						}
	 *					],
	 *					"label": [
	 *						{
	 *							"name": "xxx",
	 *							"labelID": 1,
	 *							"start": 1.0,
	 *							"end": 1.0
	 *						}
	 *					]
	 *				}
	 *			]
	 *		}
	 *	}
	 */
	public function load_project($params) {
		header("Content-Type: application/json");
		$error = $this->check_params(["userID" => "integer", "projectID" => "integer"], 343, $params);
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}
		# Load the project itself.
		$sql = "SELECT projectID, sessionID, name AS projectName FROM Project WHERE projectID = {$params["projectID"]} AND adminID = {$params["userID"]};";
		$this->get_data($sql);
		$result = $this->last_statement->fetch();
		
		#Load the ai models associated with the loaded project.
		$sql = "SELECT ID FROM Classifiers WHERE ProjectID = {$params["projectID"]}";
		$this->get_data($sql);
		$result["projectData"] = [];
		$result["projectData"]["aiModelID"] = [];
		foreach($this->last_statement->fetchAll() as $ai_model) {
			$result["projectData"]["aiModelID"][] = $ai_model["ID"];
		}
		
		# Load the datasets associated with the loaded project.
		$result["projectData"]["dataSet"] = [];
		$sql = "SELECT datasetID AS dataSetID, dataSetName, generateDate 
			FROM Dataset 
			WHERE projectID = {$params["projectID"]} 
			AND projectAdminID = {$result["sessionID"]}";
		$this->get_data($sql);
		foreach($this->last_statement->fetchAll() as $data_set) {
			# Load data rows associated with each loaded data set.
			$sql = "SELECT sensorID, datarowID AS id, dataJSON AS json FROM Datarow WHERE datasetID = {$data_set["dataSetID"]}";
			$stmt1 = $this->prepare($sql);
			$stmt1->execute();
			$data_rows = [];
			foreach($stmt1->fetchAll() as $dr) {
				$data_rows[] = array("sensorType" => $dr["sensorID"], "dataRowID" => $dr["id"], "recordingStart" => -1, "dataRow" => json_decode($dr["json"]));
			}
			
			# Load all the sensors from the data rows.
			$sql = "SELECT * FROM Sensor WHERE sensorID IN (SELECT sensorID FROM Datarow WHERE datasetID = {$data_set["dataSetID"]})";
			$stmt2 = $this->prepare($sql);
			$stmt2->execute();
			$data_row_sensors = $stmt2->fetchAll();
			
			# Load all the labels belonging to each loaded data set.
			$sql = "SELECT name, labelID, start, end FROM Label WHERE datasetID = {$data_set["dataSetID"]}";
			$stmt3 = $this->prepare($sql);
			$stmt3->execute();
			$labels = $stmt3->fetchAll();
			
			# Put everything together.
			$result["projectData"]["dataSet"][] = array("dataRowSensors" => $data_row_sensors, 
														"dataSetID" => $data_set["dataSetID"], 
														"dataSetName" => $data_set["dataSetName"], 
														"generateDate" => strtotime($data_set["generateDate"]),
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
	 
	 * @param int userID - The number associated with the specific user from description.
	 * @return void Prints out a json array containing all matching projects as described below or a json formatted error message, if the parameter does not fit.
	 * [
	 *		{
	 *			"projectID": 1,
	 *			"projectName": "xxx",
	 *			"AIModelID": [
	 *				1
	 *			],
	 *		}
	 *	]	
	 */
	public function get_project_metas($params) {
		header("Content-Type: application/json");
		$error = $this->check_params(["userID" => "integer"], 423, $params);
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}
		$result = [];
		$sql = "SELECT projectID, name as projectName FROM Project WHERE adminID = {$params["userID"]}";
		$this->get_data($sql);
		foreach($this->last_statement->fetchAll() as $v) {
			$r = [];
			$r = array_merge($r, $v);
			$sql = "SELECT aiModelID as AIModelID FROM AIModel WHERE projectID = {$v["projectID"]} AND projectAdminID = {$params["userID"]}";
			$this->get_data($sql);
			$ai_model_ids = [];
			foreach($this->last_statement->fetchAll(PDO::FETCH_NUM) as $id) {
				$ai_model_ids[] = $id[0];
			}
			$r["AIModelID"] = $ai_model_ids;
			$result[] = $r;
		}
		
		# Print out result.
        echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}
	
	/**
	 * Deletes a data set from its data base table.
	 *
	 * @param int dataSetID the id of the data set to delete
	 * @param int userID    the id of the admin user that own the project to which the data set belongs to
	 * @param int projectID the id of the project the data set belongs to 
	 * @return void 		Prints json formatted if the data set was successfully deleted as described below or json formatted error messages if the parameters don't fit.
	 *		{
	 *			"result": true|false
	 *		}
	 */
	public function delete_data_set($params) {
		header("Content-Type: application/json");
		$error = $this->check_params(["dataSetID" => "integer", "userID" => "integer", "projectID" => "integer"], 461, $params);
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}
		# Build and execute statement
		$sql = "DELETE FROM Datarow WHERE datasetID = {$params["dataSetID"]};\r\n";
		$sql .= "DELETE FROM Dataset WHERE datasetID = {$params["dataSetID"]} AND userID = {$params["userID"]} AND projectID = {$params["projectID"]}";
		$result = $this->get_data($sql);
		
		# Print out result.
        echo '{"result": ' . (($result) ? 'true' : 'false') . '}';
	}
	
	/**
	 * Creates a new entry in user and admin data base table and thus registers a new admin user.
	 * There is no parameter check for the contents of parameter device as the data is not really needed persistant and should be removed in future releases.
	 *
	 * @param string adminEmail the email address of the new admin user.
	 * @param string adminName  the user name.
	 * @param string password   the log in password.
	 * @param array  device     an array containing information about the device of the user, as follows:
	 *		[
	 *			"deviceName": "xxx",
	 *			"deviceType": "xxx",
	 *			"firmware": "xxx",
	 *			"generation": "xxx",
	 *			"MACADRESS": "xxx",
	 *			"sensorInformation:" [
	 *				[
	 *					"sensorTypeID": 1,
	 *					"sensorName": "xxx",
	 *					"deviceUniqueSensorID": 1,
	 *				]
	 *			]
	 *		]
	 * @return void 		    Prints out a json formatted object as described below or an also json formatted error message, if the parameters don't fit.
	 * 		{
	 *	 		"adminID": 1,
	 *			"device": {
	 *				"deviceID": 1,
	 *				"sensorID": [
	 *					1
	 *				]
	 *			}
	 *		}
	 */
	public function register_admin($params) {
		header("Content-Type: application/json");
		$error = $this->check_params(["adminEmail" => "string", "adminName" => "string", "password" => "string", "device" => "array"], 510, $params);
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}
		$sql = "SELECT * FROM Admin WHERE eMail = \"{$params["adminEmail"]}\";";
		$this->get_data($sql);
		if (count($this->last_statement->fetchAll()) > 0) {
			header("Content-Type: application/json");
			echo "{\"adminID\": -1, \"device\": null}";
			return;
		}
	
		$result = [];
		
		# Build and execute statement.
		$sql = "INSERT INTO User (name) VALUES (?);";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->bindValue(1, $params["adminName"]);
		
		$this->last_statement->execute();
		$result["adminID"] = $this->lastInsertId();
		$sql = "INSERT INTO Admin (userID, password, eMail) VALUES (?, ?, ?);";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->bindValue(1, $result["adminID"], PDO::PARAM_INT);
		$this->last_statement->bindValue(2, password_hash($params["password"], PASSWORD_DEFAULT));
		$this->last_statement->bindValue(3, $params["adminEmail"]);
		$this->last_statement->execute();
		
		$result["device"] = $this->register_device($params["device"], $result["adminID"]);
		
		@session_start();
		$_SESSION["loogged_in"] = $result["adminID"];
				
		# Print out result.
        echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}
	
	/**
	 * Creates a new entry in user data base and thus registers a new data miner user. 
	 * There is no validation check for the contents of parameter device. See register_admin for further details.
	 *
	 * @param string dataminerName  the user name.
	 * @param int    sessionID   the session id unter which the data miner user started the program.
	 * @param array  device     an array containing information about the device of the user, as follows:
	 *		[
	 *			"deviceName": "xxx",
	 *			"deviceType": "xxx",
	 *			"firmware": "xxx",
	 *			"generation": "xxx",
	 *			"MACADRESS": "xxx",
	 *			"sensorInformation:" [
	 *				[
	 *					"sensorTypeID": 1,
	 *					"sensorName": "xxx",
	 *					"deviceUniqueSensorID": 1,
	 *				]
	 *			]
	 *		]
	 * @return void 		    Prints out a json formatted object as described below or an also json formatted error message, if the parameters don't fit.
	 * 		{
	 *	 		"dataminerID": 1,
	 *			"project": {
	 *				"projectID": 1,
	 *				"projectName": "xxx",
	 *				"sessionID": 1
	 *			}
	 *			"device": {
	 *				"deviceID": 1,
	 *				"sensorID": [
	 *					1
	 *				]
	 *			}
	 *		}
	 */
	public function register_dataminer($params) {
		header("Content-Type: application/json");
		$error = $this->check_params(["dataminerName" => "string", "sessionID" => "integer", "device" => "array"], 587, $params);
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}
		$result = [];
		
		# Build and execute registration statement
		$sql = "INSERT INTO User (name) VALUES (?);";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->bindValue(1, $params["dataminerName"]);
		$this->last_statement->execute();
		$result["dataminerID"] = $this->lastInsertId();
		
		# Build and execute 'get project' statement
		$sql = "SELECT projectID, name as projectName, sessionID FROM Project WHERE sessionID = {$params["sessionID"]};";
		$this->get_data($sql);
		$result["project"] = $this->last_statement->fetch();
		
		$result["device"] = $this->register_device($params["device"], $result["dataminerID"]);
		
		# Print out result.
		echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}
	
	/**
	 * Creates a new device in the Device table.
	 *
	 * @param string deviceName - the name of the device
	 * @param string deviceType        - the specific type of the device
	 * @param string firmware          - the firmware of the device
	 * @param string generation        - the generation of the device
	 * @param string MACADRESS         - the MAC address of the device
	 * @param array sensorInformation - an array containing some information about the device's sensors:
	 * 		[
	 *			[
	 *				"sensorTypeID": 1,
	 *				"sensorName": "xxx",
	 *				"deviceUniqueSensorID": 1,
	 *			]
	 *		]
	 * @param int $user_id
	 * @return array - the device id and the global sensor ids.
	 */
	private function register_device($params, $user_id) {
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
		$result = [];
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

	/**
	 * This function retrieves data about the user authenticating with email and password.
	 * 
	 * @param string adminEmail - the email address of the user that wants to log in
	 * @param string password   - the password corresponding to that email address
	 * @return void             - prints a json formatted object that contains various data about the user that just successfully logged in, or an empty array, if there was no success. Prints json formatted error messages if the parameters don't fit.
	 *		{
	 *	 		"admin": {
	 *				"adminID": 1,
	 *				"email": "xxx",
	 *				"adminName": "xxx",
	 *				"deviceID": 1
	 *			}
	 * 		}
	 */
	public function login_admin($params) {
		header("Content-Type: application/json");
		$error = $this->check_params(["adminEmail" => "string", "password" => "string"], 678, $params);
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}
		$result = [];
		
		# Build and execute data comparing statement
		$sql = "SELECT userID, password FROM Admin WHERE eMail = \"{$params["adminEmail"]}\";";
		$this->get_data($sql);
		
		foreach($this->last_statement->fetchAll() as $row) {
			if(password_verify($params["password"], $row["password"])) {
				$sql = "SELECT User.userID AS adminID, eMail AS email, User.name AS adminName, deviceID FROM Admin, User, Device WHERE User.userID = {$row["userID"]} AND Admin.userID = {$row["userID"]} AND Device.userID = {$row["userID"]};";
				$this->get_data($sql);
				$result["admin"] = $this->last_statement->fetch();
				break;
			}
		}
		if (isset($result["admin"]) AND (is_iterable($result["admin"]) OR is_countable($result["admin"]))) {
			@session_start();
			$_SESSION["logged_in"] = $result["admin"]["adminID"];
		}
		
		# Print out result.
		echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}
	
	/**
	 * Creates a new label entry in its data base table.
	 *
	 * @param int   datasetID - the numeric id of the data set this label belongs to.
	 * @param array label     - an array containing all information about this label, as described below:
	 *		[
	 *			"labelName": "xxx",
	 *			"span": [
	 *				"start": 1.0,
	 *				"end": 1.0
	 *			]
	 *		]
	 * @return void           - prints out a json formatted object containing as only key the labelID which is an unsigned int >= 1 or json formatted error messages if the parameters don't fit.
	 */
	public function create_label($params) {
		header("Content-Type: application/json");
		$error = $this->check_params(["datasetID" => "integer", "label" => "array"], 722, $params);
		if(isset($params["label"]) and is_array($params["label"])) {
			$error = array_merge($error, $this->check_params(["labelName" => "string", "span" => "array"], 722, $params["label"]));
			if(isset($params["label"]["span"]) and is_array($params["label"]["span"])) {
				$start = $this->check_params(["start" => "double", "start" => "int"], 722, $params["label"]["span"]);
				$end = $this->check_params(["end" => "double", "end" => "int"], 722, $params["label"]["span"]);
				if(count($start) >= 2) {
					$error[] = $start[0];
				}
				if(count($end) >= 2) {
					$error[] = $start[0];
				}
			}
		}
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}

		$sql = "INSERT INTO Label (datasetID, name, start, end) VALUES (?, ?, ?, ?);";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->bindValue(1, $params["datasetID"], PDO::PARAM_INT);
		$this->last_statement->bindValue(2, $params["label"]["labelName"]);
		$this->last_statement->bindValue(3, $params["label"]["span"]["start"]);
		$this->last_statement->bindValue(4, $params["label"]["span"]["end"]);
		$this->last_statement->execute();

		echo '{"labelID": ' . $this->lastInsertId() . '}';
	}
	
	/**
	 * This method updates an already existing label.
	 *
	 * @param int    datasetID        - the if of the data set the label belongs to.
	 * @param int    label.labelID    - the if of the label itself.
	 * @param string label.labelName  - Optional. The new name of the label.
	 * @param float  label.span.start - Optional. The new starting time of the label.
	 * @param float  label.span.end   - Optional. The new ending time of the label.
	 * @return void                   - Prints out a json formatted object containing as only key 'success' which is only true if no update operation failed or json formatted error messages if the parameters don't fit.
	 */
	public function set_label($params) {
		header("Content-Type: application/json");
		$error = $this->check_params(["datasetID" => "integer", "label" => "array"], 764, $params);
		if(isset($params["label"]) and is_array($params["label"])) {
			$error = array_merge($error, $this->check_params(["labelName" => "string", "labelID" => "integer"], 764, $params["label"]));
		}
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}		
		$sql_prefix = "UPDATE Label SET ";
		$sql_suffix = " WHERE datasetID = {$params["datasetID"]} AND labelID = {$params["label"]["labelID"]}";
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
			$this->last_statement->bindValue(1, $params["label"]["span"]["start"]);
			$result[] = $this->last_statement->execute();
		}
		if(isset($params["label"]["span"]["end"])) {
			$sql = $sql_prefix . "end = ?" . $sql_suffix;
			$this->last_statement = $this->prepare($sql);
			$this->last_statement->bindValue(1, $params["label"]["span"]["end"]);
			$result[] = $this->last_statement->execute();
		}
		
		# Return false as soon as at least one query fails.
		echo '{"success": ' . ((in_array(false, $result, true)) ? "false" : "true") . '}';
	}
	
	/**
	 * This method deletes an existing label.
	 *
	 * @param int    dataSetID - the if of the data set the label belongs to.
	 * @param int    labelID   - the if of the label itself.
	 * @return void            - Prints out a json formatted object containing as only key 'result' which is true if the label was successfully deleted or json formatted error messages if the parameters don't fit.
	 */
	public function delete_label($params) {
		header("Content-Type: application/json");
		$error = $this->check_params(["dataSetID" => "integer", "labelID" => "integer"], 809, $params);
		if(count($error) > 0) {
			echo json_encode(["error" => $error], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			return;
		}
		# Build and execute statement
		$sql = "DELETE FROM Label WHERE datasetID = {$params["dataSetID"]} AND labelID = {$params["labelID"]};";
		$result = $this->get_data($sql);
		
		# Print out result.
        echo '{"result": ' . (($result) ? "true" : "false") . '}';
	}
	
	/**
	 * This method retrieves an email address by user id from the admins database table and returns it - in opposite to all other methods, that simply print their result.
	 *
	 * @param int $uid - the numeric user id of of whom the email address is to be retrieved.
	 * @return array   - the corresponding email address together with the name, each located under 'email', resp. 'name'
	 */
	public function get_email(int $uid): array {
		$sql = "SELECT name, eMail AS email FROM User, Admin WHERE Admin.userID = {$uid} AND User.UserID = {$uid}";
		$this->get_data($sql);
		$result = $this->last_statement->fetchAll();
		if(count($result) !== 1) {
			throw new UnexpectedValueException("Illegal number of records - database is corrupted!");
		}
		return $result[0];
	}
	
	/**
	 * This method returns the sensor types necessairy to use the model encoded by its running number.
	 * @param int $model_id - the numeric id of the classifier in its data base.
	 * @return array        - the numeric sensor types necessairy for this model.
	 */
	public function get_sensor_types(int $model_id): array {
		$sql = "SELECT Sensors FROM Classifiers WHERE ID = {$model_id}";
		$this->get_data($sql);
		$result = $this->last_statement->fetchAll();
		if($this->last_statement->rowCount() !== 1) {
			throw new UnexpectedValueException("Illegal number of records - database is corrupted!");
		}
		return json_decode($result[0]["Sensors"], true);
	}
}
?>
