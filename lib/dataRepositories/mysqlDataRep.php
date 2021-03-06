<?php

require_once "lib/dataRepositories/interfaces.php";

class dataRep implements dataRep_Interface {

    var $pdo=null;
    var $host;
    var $port=3306;
    var $dbname;
    var $username;
    var $password;
    
    var $uniqueErrorCode="1062";
    
    function __construct() {
        //The extender has to define in the __construct the connection parameters
    }

    function getInstance() {

        $this->pdo = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->dbname, $this->username, $this->password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        return $this->pdo;
        
    }
    
    function close() {
        $this->pdo = null;
    }

    public function dbExists($dataBase=null) {
        
        if ($dataBase == null) $dataBase=$this->dbname;
        
        $sql="SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$dataBase'";
        $query = $this->dataRep->prepare($sql);
        $query->execute();
        $results = $query->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($results)) {
            return true;
        }
        else return false;
        
    }
    
    
    function getLastInsertId($sequence) {
        
        $sql = "SELECT LAST_INSERT_ID() AS lastId";

        $query = $this->pdo->prepare($sql);
        $query->execute();
        $results = $query->fetchAll(PDO::FETCH_ASSOC);
        return $results[0]["lastId"];    
    }    
    
    public function getFieldFromErrorMessage($errorCode,$errorMessage) {
        $messageArray=explode(" ", $errorMessage);
        $field="";
        if ($errorCode==$this->uniqueErrorCode) {
            $fieldData=trim($messageArray[count($messageArray)-1],"'");
            $field=rtrim($fieldData,"_UNIQUE");
        }
        
        return $field;
        
    }

    public function __destruct() {
        $this->close();
    }


}