<?php

$enjoyHelper=$config['base']['enjoyHelper'];
require_once "lib/languages/$language.php"; //Exposes base_lenguage() for this file and for the helper file
require_once "lib/enjoyHelpers/$enjoyHelper.php"; //Brings crud() among other helpers
require_once 'lib/misc/encryption.php';


$moduleModelFile=$modelsDir."model_$mod.php";// Brings "moduleName"Model()
if (file_exists($moduleModelFile)) {
    require_once $moduleModelFile;
}

$appDataRepFile=$applicationDataRepDir."app_dataRep.php"; //Brings app_dataRep()
if (file_exists($appDataRepFile)) {
    require_once $appDataRepFile;
}
 
class controllerBase {

    var $resultData=array();
    var $config;
    var $dataRep=null;
    var $baseModel=null;
    //var $directories=array();

    /**
     * Controller constructor
     * @param array $config General Config
     */
    
    function __construct($config) {
        $this->config=&$config;
        
        $dataRepName=$this->config['flow']['app'].'DataRep';
        
        if (class_exists($dataRepName)) {
            $dataRepObject = new $dataRepName();        
            $this->dataRep=$dataRepObject->getInstance();
            $baseModelClass=$this->config['flow']['mod'].'Model';
            if (class_exists($baseModelClass)) {
                $this->baseModel = new $baseModelClass($this->dataRep, $this->config);
            }
        }
        
    }
    
    function __destruct() {
        $this->dataRep= null;
    }
    
    /**
     * Handles the execution of actions
     * @param string $act Action
     */
    
    function run($act) {
        $this->resultData["useLayout"] = true;
        $this->resultData["view"] = $act; //Default View
        $action=$act."Action";
        $this->$action();    
    }

    /**
     * Just to have an action.
     */
    
    function indexAction() {
    }
    
    /**
     * Handles the CRUD (Create Update Delete) for the specified Model
     * 
     * @param model $model
     * @param PDO $dataRep
     * @return string html of the crud result
     */
    
    function crudAction($model,$dataRep) {

        $crud = new crud($model);
        $lang=$this->config["base"]["language"];
        $baseAppTranslations = new base_language();
        $this->baseAppTranslation = $baseAppTranslations->lang;
        $showCrudList = true;
        
        if ($this->config["helpers"]['crud_encryptPrimaryKeys']) {
            session_start();
            $encryption = new encryption();
            if (key_exists($model->tables . '_' . $model->primaryKey, $_REQUEST)) {
                $_REQUEST[$model->tables . '_' . $model->primaryKey] = $encryption->decode($_REQUEST[$model->tables . '_' . $model->primaryKey], $this->config["appServerConfig"]['encryption']['hashText'] . $_SESSION["userInfo"]['lastLoginStamp']);
            }
            if (key_exists('keyValue', $_REQUEST)) {
                $_REQUEST['keyValue'] = $encryption->decode($_REQUEST['keyValue'], $this->config["appServerConfig"]['encryption']['hashText'] . $_SESSION["userInfo"]['lastLoginStamp']);
            }
        }


        if (key_exists("crud", $_REQUEST)) {
            
            
            if ($_REQUEST["crud"] == "createForm") {
                $this->resultData["output"]["label"] = $model->label[$lang]." - ".$this->baseAppTranslation['add'];
                $this->resultData["output"]["crud"] = $crud->getForm();
                $showCrudList = false;
            } elseif ($_REQUEST["crud"] == "editForm") {
                $register = $model->fetchRecord();
                $this->resultData["output"]["label"] = $model->label[$lang]." - ".$this->baseAppTranslation['edit'];
                $this->resultData["output"]["crud"] = $crud->getForm($register);
                $showCrudList = false;
            } elseif ($_REQUEST["crud"] == "editFkForm") {
                $fkModel=$model->foreignKeys[$_REQUEST['fkField']]['model'];
                $fkCrud = new crud($fkModel);
                $fkRegister = $model->fetchFkRecord();
                $this->resultData["output"]["label"] = $fkModel->label[$lang]." - ".$this->baseAppTranslation['edit'];
                $this->resultData["output"]["crud"] = $fkCrud->getForm($fkRegister);
                $showCrudList = false;
            } elseif ($_REQUEST["crud"] == "change") {
                $model->updateRecord();
            } elseif ($_REQUEST["crud"] == "fkChange") {
                $fkModel=$model->foreignKeys[$_REQUEST['fkField']]['model'];
                
                if ($this->config["helpers"]['crud_encryptPrimaryKeys']) {
                    $_REQUEST[$fkModel->tables.'_'.$fkModel->primaryKey] = $encryption->decode($_REQUEST[$fkModel->tables.'_'.$fkModel->primaryKey], $this->config["appServerConfig"]['encryption']['hashText'] . $_SESSION["userInfo"]['lastLoginStamp']);
                }

                $fkModel->updateRecord();
            } elseif ($_REQUEST["crud"] == "add") {
                $model->insertRecord();
            } elseif ($_REQUEST["crud"] == "remove") {
                $model->deleteRecord();
            }
        }

        if ($showCrudList) {
            if (key_exists("keyField", $_REQUEST)) {
                $options['where'][]=$_REQUEST['keyField']."='{$_REQUEST['keyValue']}'";
                $this->resultData["output"]["label"] = $model->label[$lang]." ".$this->baseAppTranslation['of']." ".$_REQUEST['keyLabel']." (".$_REQUEST['modelLabel'].")";
                $resultData = $model->fethLimited($options);
            }
            else {
                $resultData = $model->fethLimited();
                $this->resultData["output"]["label"] = $model->label[$lang];
            }
            $this->resultData["output"]["crud"] = $crud->listData($resultData, $resultData["totalRegisters"]);
        }
        
        return 'ok';
    }
    
    /**
     * Handles the INNER controller calls ( actually NOT used)
     */
    
    function dataCallAction() {
     
        $resultData=$this->model->dataCall();
        $this->resultData['output']=json_encode($resultData);
        
    }    
    
}

?>
