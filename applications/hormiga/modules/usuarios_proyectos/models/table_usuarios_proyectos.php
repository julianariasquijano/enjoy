<?php

class table_usuarios_proyectos {

    var $primaryKey = "id";
    var $fieldsConfig;

    function __construct() {

        $this->fieldsConfig = array(
            "id" => array(
                "definition" => array(
                    "type" => "number",
                    //"options"=>array("required"),
                    "default" => "",
                    "label" => array(
                        "es_es" => "Serial",
                    ),
                    //"widget" => "textarea",
                ),
                "viewsPresence"=>array("createForm","editForm","editFkForm"),
            ),
            "id_usuarios" => array(
                "definition" => array(
                    "type" => "number",
                    //"options"=>array("required"),
                    "default" => "",
                    "label" => array(
                        "es_es" => "Usuario",
                    ),
                    //"widget" => "textarea",
                ),
                //"viewsPresence"=>array("list","createForm","editForm","editFkForm"),
            ),
            "id_proyectos" => array(
                "definition" => array(
                    "type" => "number",
                    //"options"=>array("required"),
                    "default" => "",
                    "label" => array(
                        "es_es" => "Proyecto",
                    ),
                    //"widget" => "textarea",
                ),
                //"viewsPresence"=>array("list","createForm","editForm","editFkForm"),
            ),
        );
    }        
}   