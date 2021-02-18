<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ob_start();
session_start();
var_dump($_POST);
die();
	
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
     * @param $what SQL query to execute
     */
    private function get_data($what) {
        $this->last_statement = $this->prepare($what);
        $this->last_statement->execute();
		$this->last_statement->setFetchMode(parent::FETCH_ASSOC);
        $result = [];
        foreach($this->last_statement->fetchAll() as $k=>$v) {
            $result[$k] = $v;
        }
		header("Content-Type: application/json");
        print_r(json_encode($assoc_result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT));
    }

    public function get_language_metas() {
        $sql = "SELECT languageCode, languageName from Language;";
        $this->get_data($sql);
    }
	
	public function load_language($params) {
		$query_suffix = "";
		foreach($params as $k => $v) {
			$query_suffix .= "$k = $v OR";
		}
		$query_suffix = substr($query_suffix, 0, -3) . ";";
		$sql = "SELECT language from Language WHERE" . $query_suffix;
        $this->get_data($sql);
	}
}

if(!isset($_GET["action"])) {
    throw new InvalidArgumentException("Job not specified!");
}
$db = new DataBaseConnection();
switch($_GET["action"]) {
    case "get_language_metas":
	    eval("\$db->${_GET["action"]}();");
        break;
    case "load_language":
		eval("\$db->${_GET["action"]}(${_POST["params"]});");
		break;
	default:
        throw new BadMethodCallException("This value is illegal. Intelligence Agency is informed.");
}
ob_end_flush();
ob_flush();
flush();
?>
