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
     */
    private function get_data($what) {
        $this->last_statement = $this->prepare($what);
        $this->last_statement->execute();
		$this->last_statement->setFetchMode(parent::FETCH_ASSOC);
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
		# Unused params:
		#	adminEmail
		
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
	
	public function create_data_set($params) {
		# Unused params:
		#	dataRow
		# Abused params:
		# 	sessionID
		# Not filled fields:
		# 	userDataSetID
		
		# Execute statement
		$sql = "INSERT INTO Dataset (projectID, userID, dataSetName, projectAdminID) VALUES (?, ?, ?, ?)";
		$this->last_statement = $this->prepare($sql);
		$this->last_statement->bindValue(1, $params["projectID"], PDO::PARAM_INT);
		$this->last_statement->bindValue(2, $params["userID"], PDO::PARAM_INT);
		$this->last_statement->bindValue(3, $params["dataSetName"]);
		$this->last_statement->bindValue(4, $params["sessionID"], PDO::PARAM_INT);
		$this->last_statement->execute();
		
		# Print out result
		header("Content-Type: application/json");
		echo "{${this->lastInsertId()}";
	}
	
	public function send_data_point($params) {
		# Database table columns and functions params do not match.
		# No Implementation due to that.
		
		header("Content-Type: application/json");
        echo "{}";
	}
	
	public function load_project($params) {
		# Unused params
		# 	adminEmail
		# Missing fields:
		#	aiModelID
		
		# Build up our mighty multi query and execute it
		$sql = "SELECT * FROM Project WHERE projectID = ${params["projectID"]} AND adminID = ${params["userID"]};\r\n";
		$sql .= "SELECT * FROM Dataset WHERE projectID = ${params["projectID"]};\r\n";
		$sql .= "SELECT * FROM Datarow WHERE datasetID IN (SELECT dataSetID FROM Dataset WHERE projectID = ${params["projectID"]});";
		$this->get_data($sql);
		$result = $this->last_statement->fetchAll();
		
		# Print out result.
		header("Content-Type: application/json");
        echo json_encode($result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}
	
	/**
	 * Requests all project with all affiliated data belonging to a specific user.
	 * @param $params['userID'] - The number associated with the specific user from description.
	 * @return All projects.
	 */
	public function get_project_metas($params) {
		# Unused params
		# 	adminEmail
		
		$result = [];
		$sql = "SELECT projectID, name as projectName FROM Project WHERE adminID = ${params["userID"]}";
		$this->get_data($sql);
		foreach($this->last_statement->fetchAll(); as $v) {
			$r = [];
			$r = array_merge($r, $v);
			$sql = "SELECT aiModelID as AIModelID FROM AIModel WHERE projectID = ${v["projectID"]}";
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
		header("Content-Type: application/json");
        echo "{}";
	}
	
	public function register_device($params) {
		header("Content-Type: application/json");
        echo "{}";
	}
	
	public function register_admin($params) {
		header("Content-Type: application/json");
        echo "{}";
	}
	
	public function register_dataminer($params) {
		header("Content-Type: application/json");
        echo "{}";
	}
	
	public function register_ai_model_user($params) {
		header("Content-Type: application/json");
        echo "{}";
	}

	public function login_admin($params) {
		header("Content-Type: application/json");
        echo "{}";
	}
	
	public function logout_admin($params) {
		header("Content-Type: application/json");
        echo "{}";
	}
	
	public function send_label($params) {
		header("Content-Type: application/json");
        echo "{}";
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
	case "register_device":
	case "create_data_set":
	case "send_data_point":
	case "load_project":
	case "get_project_metas":
	case "delete_data_set":
	case "register_admin":
	case "register_dataminer":
	case "register_ai_model_user":
	case "login_admin":
	case "logout_admin":
	case "send_label":
		eval("\$db->${_GET["action"]}(\$_POST);");
		break;
	default:
        throw new BadMethodCallException("This value is illegal. Intelligence Agency is informed.");
}
ob_end_flush();
ob_flush();
flush();
?>
