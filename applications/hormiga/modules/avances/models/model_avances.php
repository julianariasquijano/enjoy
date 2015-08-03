<?php

//Model Class

require_once "lib/enjoyClassBase/modelBase.php";
require_once "applications/hormiga/modules/avances/models/table_avances.php";

class avancesModel extends modelBase {

    var $tables="avances";
    var $usuarios_proyectosModel;
    var $usuarios_proyectos_tareasModel;

    function __construct($dataRep, &$config,&$incomingModels=array()) {
        parent::__construct($dataRep, $config,$incomingModels);
        $table=new table_avances();
        $this->fieldsConfig=$table->fieldsConfig;
        $this->primaryKey=$table->primaryKey;
        
        $tareasModel=$this->getModuleModelInstance("tareas");
        $this->usuarios_proyectosModel=$this->getModuleModelInstance("usuarios_proyectos");
        $this->usuarios_proyectos_tareasModel=$this->getModuleModelInstance("usuarios_proyectos_tareas");
                
        $this->label=array(
            "es_es"=>"Avances",
        );
        
        $this->foreignKeys = array (
            "id_tareas" => array(
                "model"=>&$tareasModel,
                "keyField"=>"tareas.id",
                "dataField"=>"_CONCAT(proyectos.proyecto,'_',tareas.tarea)",
             ),
        );

//        $this->subModels=array( //For many to many relations for example
//            0=>array(
//                "model"=>&$tareasModel ,
//                "linkerField"=>$this->primaryKey,
//                "linkedField"=>"external field that references this primary key",
//                "linkedDataField"=>"similar as a foreignKey dataField",
//                "type"=>"multiple",
//            ),
//        );

//        $this->dependents=array(
//            "id"=>array(
//                0=>array(
//                  "mod"=>"someModule",
//                    "act"=>"index",
//                    "keyField"=>"field of the dependent table that references here",
//                    "label"=>array(
//                        "es_es" =>"DependentCaption",
//                    ),
//                ),              
//            ),
//        );

    }
    
    function traerTareasDisponibles() {

        $userId=$this->usuarios_proyectosModel->usersModel->getId();
        
        $options['fields'][]='proyectos.proyecto';
        $options['fields'][]='tareas.id AS id_tarea';
        $options['fields'][]='tareas.tarea';
        $options['where'][]="usuarios_proyectos.id_users='$userId'";
        $result=$this->usuarios_proyectos_tareasModel->fetch($options);
        $tareas=$result["results"];
        return $tareas;
        
        //return $this->usuarios_proyectos_tareasModel->quickfetch("",$options);
        
    }
    
}