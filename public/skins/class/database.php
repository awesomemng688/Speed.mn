<?php
class DataBase {
	
    private $PDO;

    public function __construct() {
        $missingConfig = [];
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constantName) {
            if (!defined($constantName) || trim((string) constant($constantName)) === '') {
                $missingConfig[] = $constantName;
            }
        }

        if ($missingConfig) {
            $missingList = htmlspecialchars(implode(', ', $missingConfig), ENT_QUOTES, 'UTF-8');
            echo "<div style='display: flex; flex-direction: column;align-items: center;justify-content: center;text-align: center;'><h2>Database config is missing</h2>";
            die("<pre style='padding: 10px;text-wrap: balance; border: 2px solid #ed6bd3;background: #252525; color: #ed6bd3; width: 70%;'>Missing values: {$missingList}. Set SKINS_DB_* or DB_* values in the application .env file.</pre>");
        }

        try {
            $this->PDO = new PDO("mysql:host=".DB_HOST."; port=".DB_PORT."; dbname=".DB_NAME, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
        }
        catch(PDOException $ex)
        {
            echo "<div style='display: flex; flex-direction: column;align-items: center;justify-content: center;text-align: center;'><h2>Problem with database!</h2>";
            die("<pre style='padding: 10px;text-wrap: balance; border: 2px solid #ed6bd3;background: #252525; color: #ed6bd3; width: 50%;'>" . $ex . "</pre>");
        }
    }
    public function select($query, $bindings = []) {
        $STH = $this->PDO->prepare($query);
        $STH->execute($bindings);
        $result = $STH->fetchAll(PDO::FETCH_ASSOC);
		$result ??= false;
		return $result;
    }

    public function query($query, $bindings = []){
        $STH = $this->PDO->prepare($query);
        return $STH->execute($bindings);
    }

}
