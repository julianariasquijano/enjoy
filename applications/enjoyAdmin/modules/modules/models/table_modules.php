<?php

class table_modules {

    var $primaryKey = "id";
    var $fieldsConfig;

    function __construct() {

        $this->fieldsConfig = array(
            "id" => array(
                "definition" => array(
                    "type" => "number",
                    "default" => "",
                    "label" => array(
                        "es_es" => "Serial",
                    ),                    
                ),
            ),
            "id_app" => array(
                "definition" => array(
                    "type" => "number",
                    "options"=>array("required"),
                    "default" => "",
                    "label" => array(
                        "es_es" => "Aplicacion",
                    ),                    
                ),
            ),
            "name" => array(
                "definition" => array(
                    "options"=>array("required"),
                    "type" => "string",
                    "default" => "",
                    "label" => array(
                        "es_es" => "Modulo",
                    ),
                ),
            ),
        );
    }

}

?>
