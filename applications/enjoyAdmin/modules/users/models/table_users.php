<?php

class table_users {

    var $primaryKey = "id";
    var $fieldsConfig;
    var $registerConditions;

    function __construct() {

        $this->fieldsConfig = array(
            "id" => array(
                "definition" => array(
                    "options"=>array(),
                    "type" => "number",
                    "default" => "",
                    "label" => array(
                        "es_es" => "Serial",
                    ),                    
                ),
            ),
            
            "user_name" => array(
                "definition" => array(
                    "options"=>array("required"),
                    "type" => "string",
//                    "widget" => "text",
                    "default" => "",
                    "label" => array(
                        "es_es" => "Usuario",
                    ),
                ),
            ),
            
            "password" => array(
                "definition" => array(
                    "options"=>array("password"),
                    "type" => "string",
                    "default" => "",
                    "label" => array(
                        "es_es" => "Clave",
                    ),                    
                ),
            ),
            
            "id_role" => array(
                "definition" => array(
//                    "dataSourceArray"=>"crudDataCall('enjoyAdmin','roles','name');",
//                    "modelRelationConfig"=>"modelRelation('roles','roles','name');",
                    "foreignKey"=>array(),
                    "options"=>array(),
                    "type" => "number",
                    "default" => "",
                    "label" => array(
                        "es_es" => "Rol",
                    ),                    
                ),
            ),            
            "active" => array(
                "definition" => array(
                    "options"=>array("required"),
                    "type" => "bool",
//                    "widget" => "text",
                    "label" => array(
                        "es_es" => "Activo",
                    ),
                ),
            ),          

            "enjoy_registerConditions" => array(
                0 => array (
                    "field1"=>"user_name",
                    "field2"=>"password",
                    "comparison"=>"dif",
                    "label" => array(
                        "es_es" => "La clave debe ser diferente al nombre de usuario",
                    ),                            
                ),
            ),
        );
    }
}

?>
