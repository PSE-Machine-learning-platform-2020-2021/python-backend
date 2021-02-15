<?php
ob_start();
session_start();

class DataBaseConnection extends PDO {
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
        if (isset($connection_data["dns"]) {
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
     *
     */
    private function get_data($what) {
        $this->last_statement = $this->prepare($what);
        $this->last_statement->execute();
    }

    public function get_language_metas() {
        $sql = "SELECT LanguageCode, LanguageName from Language";
        $this->get_data($sql);
        $result = $this->last_statement->setFetchMode(parent::FETCH_ASSOC);
        $assoc_result = []
        foreach($stmt->fetchAll() as $k=>$v) {
            $assoc_result[$k] = $v;
        }
        print_r(json_encode($assoc_result, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT));
    }
}

if(!isset($_GET["action"])) {
    throw new InvalidArgumentException("Job not specified!");
}
$db = new DataBaseConnection();
switch($_GET["action"]) {
    case "get_language_metas":
        eval("$db->" . $_GET["action"] . "();");
        break;
    default:
        throw new BadMethodCallException("This value is illegal. Intelligence Agency is informed.");
}
ob_end_flush();
ob_flush();
flush();
?>