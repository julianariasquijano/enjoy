<?php

require_once 'lib/enjoyClassBase/validatorBase.php';

class modelBase {

    var $dataRep;
    var $tables;
    var $foreignKeys=array();
    var $dependents=array();
    var $subModels=array();
    var $config;
    var $fieldsConfig;
    var $primaryKey;
    var $result = array();
    var $label=array();
    var $incomingModels=array();
    
    var $bpmModel=null;
    
    /**
     * Base class for the Models
     * @param PDO $dataRep
     * @param array $config with the General Config
     * @param array $incomingModels with allready instanced Models
     */
    
    function __construct($dataRep, &$config,&$incomingModels=array()) {
        $this->dataRep = $dataRep;
        $this->config = &$config;
        $this->incomingModels = &$incomingModels;
        $this->dataRep->getInstance();

        if ($this->bpmModel==null) {
            require_once 'lib/commonModules/bpm/models/model_bpm.php';
            $this->bpmModel = new bpmModel($this->dataRep,$this->config,$this->tables);
        }        
    }

    /**
     * Fetchs registers limiting the results for pagination purpouses
     * @param array $options for the fetch method
     * @return array with the results
     */
    function fethLimited($options=array()) {
        
        $options["additional"][]="LIMIT {$this->config["helpers"]["crud_listMaxLines"]} OFFSET 0 ";
        return $this->fetch($options);
    
    }
    
    function insertRecord() {
        
        $validator= new validatorBase($this->config, $this->fieldsConfig,$this->primaryKey);
        $register=array();
        
        foreach ($this->fieldsConfig as $field => $configSection) {
            $addField=true;
            if ($field != $this->primaryKey and substr($field, 0,5)!="enjoy" ) {
                if (key_exists($this->tables.'_'.$field, $_REQUEST)) {
                    if ($_REQUEST[$this->tables.'_'.$field]=="") {
                        if (isset($configSection["definition"]["options"])) {
                            $fieldsOptions = $configSection["definition"]["options"];
                            if (!in_array("required", $fieldsOptions) and $_REQUEST[$this->tables.'_'.$field]=="") {
                                $addField=false; //avoid inserting data if not required and empty
                            }                    
                        }
                        else{
                            $addField=false; //avoid inserting data if not required and empty
                        }
                        
                    }
                    
                    if ($addField) {
                        $register[$field]=$_REQUEST[$this->tables.'_'.$field];
                        $options["fields"][]=$field;
                        if (is_array($_REQUEST[$this->tables.'_'.$field])) {
                            $options["values"][0][]="'".serialize($_REQUEST[$this->tables.'_'.$field])."'";
                        }
                        else {

                            if ($this->dataRep->utf8) {
                                $options["values"][0][]="'".utf8_encode($_REQUEST[$this->tables.'_'.$field])."'";
                            }
                            else $options["values"][0][]="'".$_REQUEST[$this->tables.'_'.$field]."'";
                        }

                    }
                        
                }
            }
        }
        
        $validationResult=$validator->validateFields($register);
        
        if ($validationResult!== true) {
            throw new Exception($validationResult);
//            exit($validationResult);
        }
        
        $validationResult=$validator->validateRegister($register);
        
        if ($validationResult!== true) {
            throw new Exception($validationResult);
//            exit($validationResult);
        }
        
        foreach ($this->subModels as $subModelEnry => $subModelConfig) {
            $subModel=&$subModelConfig['model'];
            $subModelValidator= new validatorBase($subModel->config, $subModel->fieldsConfig,$subModel->primaryKey);
            $freshFields=array();
            
            foreach ($subModel->fieldsConfig as $field => $configSection) {
                $freshField=true;#"roles_applications_id_app"
                if (isset($subModel->fieldsConfig[$field]["definition"]["options"])) {
                    $subModelFieldsOptions = $subModel->fieldsConfig[$field]["definition"]["options"];
                    if (in_array("required", $subModelFieldsOptions) ) {
                        $freshField=false;
                    }            
                }
                if ($freshField) {
                    $freshFields[]=$field;
                }                
                if (key_exists($subModel->tables.'_'.$field, $_REQUEST)) {
                    $subModelRegister[$field]=$_REQUEST[$subModel->tables.'_'.$field];
                }
            }        

            $subModelValidationResult=$subModelValidator->validateFields($subModelRegister,$freshFields);

            if ($subModelValidationResult!== true) {
                throw new Exception($subModelValidationResult);
            }
        }
        
        $okOperation=$this->insert($options);
        
        if ($okOperation) {
            $newPrimaryKey=$this->dataRep->getLastInsertId($this->tables."_".$this->primaryKey);

            foreach ($this->subModels as $subModelEnry => $subModelConfig) {

                $subModel=&$subModelConfig['model'];
                $linkerField=$subModelConfig['linkerField'];
                $linkedField=$subModelConfig['linkedField'];
                $linkedDataField=$subModelConfig['linkedDataField'];
                $type=$subModelConfig['type'];

                if (!isset($_REQUEST[$this->tables.'_'.$linkerField])) {
                    $_REQUEST[$subModel->tables.'_'.$linkedField]=$newPrimaryKey;
                }
                else{
                    $_REQUEST[$subModel->tables.'_'.$linkedField]=$_REQUEST[$this->tables.'_'.$linkerField];
                }


                $linkedDataFieldVar=$_REQUEST[$subModel->tables.'_'.$linkedDataField];

                if (is_array($linkedDataFieldVar)) {
                    foreach ($linkedDataFieldVar as $linkedDataFieldValue) {
                        $_REQUEST[$subModel->tables.'_'.$linkedDataField]=$linkedDataFieldValue;
                        $okOperation=$subModel->insertRecord();
                        
                        if (!$okOperation) {
                            break 2;
                        }
                        
                    }
                }elseif($linkedDataFieldVar!=''){
                    $okOperation=$subModel->insertRecord();
                    
                    if (!$okOperation) {
                        break;
                    }                    
                    
                }

            }
            
            if (count($this->config['bpmFlow']) and is_object($this->bpmModel)){ //checks if is object to avoid the nested bpmModel to insert 

                if ($this->config['bpmFlow']['bpmModel']==$this->tables) {
                    $_REQUEST[$this->bpmModel->tables.'_user']=$_SESSION["user"];
                    $_REQUEST[$this->bpmModel->tables.'_id_process']=$newPrimaryKey;
                    $_REQUEST[$this->bpmModel->tables.'_state']=$this->config['bpmFlow']['initialState'];
                    $_REQUEST[$this->bpmModel->tables.'_date']=date('Y-m-d H:i:s');
                    $_REQUEST[$this->bpmModel->tables.'_info']=$_REQUEST['bpm_info'];                
                    $okOperation=$this->bpmModel->insertRecord();
                    if (!$okOperation) {
                        throw new Exception("BPM: Insert Error");
                    }
                    
                }
                
            }
                 
        }
        
        return $okOperation;
        
    }
    
    /**
     * Generic insert tool
     * @param array $options with the fields and values
     */
    function insert($options) {

        list($fieldsSql, $valuesSql) = $this->getInsertConf($options);
        
        $sql = "INSERT INTO $this->tables $fieldsSql VALUES $valuesSql";

        try {
            $query = $this->dataRep->pdo->prepare($sql);
            $query->execute();
            $okOperation=true;
        } catch (PDOException $exc) {
            $error= new errorTool($this->config);
            $errorMessage=$exc->getMessage();
            $errorCode=$exc->errorInfo[1];
            
            $baseAppTranslations = new base_language();
            $baseAppTranslation = $baseAppTranslations->lang;                     

            if ($errorCode==$this->dataRep->uniqueErrorCode) {
                $duplicatedData=$this->dataRep->getFieldFromErrorMessage($errorCode,$errorMessage);
                if (isset($this->fieldsConfig[$duplicatedData]["definition"]["label"][$this->config["base"]["language"]])) {
                    $duplicatedDataLabel=$this->fieldsConfig[$duplicatedData]["definition"]["label"][$this->config["base"]["language"]];
                }
                elseif (isset($this->indexConfig[$duplicatedData]["definition"]["label"][$this->config["base"]["language"]])) {
                    $duplicatedDataLabel=$this->indexConfig[$duplicatedData]["definition"]["label"][$this->config["base"]["language"]];
                }
                throw new Exception($baseAppTranslation["uniqueError"].$duplicatedDataLabel);
            }
            else{
                $error->log($errorMessage);
                $okOperation=false;
            }

        }

        return $okOperation;

    }

    /**
     * Fetchs a record according to the model
     * @return array with the results
     */
    
    function fetchRecord($options=array()) {

        $primaryKeyValue = $_REQUEST[$this->tables.'_'.$this->primaryKey];

        $options["where"][] = $this->dataRep->dbname.'.'."$this->tables.$this->primaryKey='$primaryKeyValue'";
        $resultArray = $this->fetch($options);
        $record = $resultArray["results"][0];
        return $record;
    }
    
     /**
     * Fetchs a record according to one of the foreign keys of the model
     * @return array with the results
     */
    
    function fetchFkRecord() {

        $primaryKeyValue = $_REQUEST[$this->tables.'_'.$this->primaryKey];
        $fkField = $_REQUEST['fkField'];
        
        $options['config']['dataFieldConversion']=false;
        $options["fields"][] = $fkField;
        $options["where"][] = $this->dataRep->dbname.'.'."$this->tables.$this->primaryKey='$primaryKeyValue'";
        
        $resultArray = $this->fetch($options);
        $register = $resultArray["results"][0];        
        $fkValue=$register[$fkField];
        
        $fkModel=$this->foreignKeys[$fkField]['model'];

//        $options["fields"][] = '*';
        unset($options);
        $options["where"][] = $fkModel->dataRep->dbname.'.'."$fkModel->tables.$fkModel->primaryKey='$fkValue'";
        $resultArray = $fkModel->fetch($options);
        $register = $resultArray["results"][0];
        return $register;
    }

    function deleteRecord() {
        
        $okOperation=true;
        foreach ($this->subModels as $subModelEnry => $subModelConfig) {

            $subModel=&$subModelConfig['model'];
            $linkerField=$subModelConfig['linkerField'];
            $linkedField=$subModelConfig['linkedField'];

            $options["where"][] = $subModel->dataRep->dbname.'.'."{$subModel->tables}.$linkedField='{$_REQUEST[$this->tables.'_'.$linkerField]}'";
            $okOperation=$subModel->delete($options);
            unset($options);
            if (!$okOperation) {
                break;
            }

        }
        
        if ($okOperation) {
            $options["where"][] = $this->dataRep->dbname.'.'.$this->tables.'.'."$this->primaryKey='".$_REQUEST[$this->tables.'_'.$this->primaryKey]."'";
            $okOperation=$this->delete($options);
            unset($options);
        }
        
        
        return $okOperation;
        
    }

    function updateRecord() {
        
        $options = array();
        $options['config']['dataFieldConversion'] = false;
        $oldRegisterData = $this->fetchRecord($options);
        unset($options);  
        
        $validator= new validatorBase($this->config, $this->fieldsConfig,$this->primaryKey);
        $register=array();        

        foreach ($this->fieldsConfig as $field => $configSection) {
            $addField=true;
            if ($field != $this->primaryKey and substr($field, 0,5)!="enjoy") {
                
                if (key_exists($this->tables.'_'.$field, $_REQUEST)) {
                    $value = $_REQUEST[$this->tables.'_'.$field];
                    
                    if (isset($this->fieldsConfig[$field]["definition"]["options"])) {
                        $fieldsOptions = $this->fieldsConfig[$field]["definition"]["options"];
                    }
                    else{
                        $fieldsOptions = array();
                    }
                    
                    if ($value == "" and ($configSection["definition"]["type"]=='date' or $configSection["definition"]["type"]=="dateTime")) {
                        if (!in_array("required", $fieldsOptions) and $_REQUEST[$this->tables . '_' . $field] == "") {
                            $addField = false; //avoid inserting data if not required and empty
                        }
                    }
                    elseif ($value == "" and is_null($oldRegisterData[$field]) and !in_array("required", $fieldsOptions)) {
                        $addField = false; //avoid generating sql data if not required and empty to let null values be kept
                    }

                    if ($addField) {                    
                        $register[$field] = $value;
                        if ($this->dataRep->utf8) {
                            $options["set"][] = "$field='".utf8_encode($value)."'";
                        }
                        else $options["set"][] = "$field='$value'";
                    }
                    
                }
            }
        }
        
        $validationResult=$validator->validateFields($register);
        
        if ($validationResult!== true) {
//            exit($validationResult);
            throw new Exception($validationResult);
        }
        
        $validationResult=$validator->validateRegister($register);
        
        if ($validationResult!== true) {
//            exit($validationResult);
            throw new Exception($validationResult);
        }

        
        foreach ($this->subModels as $subModelEnry => $subModelConfig) {
            $subModel=&$subModelConfig['model'];
            $subModelValidator= new validatorBase($subModel->config, $subModel->fieldsConfig,$subModel->primaryKey);
            $freshFields=array();
            
            foreach ($subModel->fieldsConfig as $field => $configSection) {
                $freshField=true;# Fields that should not be validated.   "roles_applications_id_app"
                if (isset($subModel->fieldsConfig[$field]["definition"]["options"])) {
                    $subModelFieldsOptions = $subModel->fieldsConfig[$field]["definition"]["options"];
                    if (in_array("required", $subModelFieldsOptions) ) {
                        $freshField=false;
                    }            
                }
                if ($freshField) {
                    $freshFields[]=$field;
                }                
                if (key_exists($subModel->tables.'_'.$field, $_REQUEST)) {
                    $subModelRegister[$field]=$_REQUEST[$subModel->tables.'_'.$field];
                }
            }        

            $subModelValidationResult=$subModelValidator->validateFields($subModelRegister,$freshFields);

            if ($subModelValidationResult!== true) {
                throw new Exception($subModelValidationResult);
            }
        }        
        
        if ($this->config['bpmData']!=null and $this->config['flow']['act'] != 'index'){
            
            $newStateError=True;
            if (!isset($this->config['bpmData']['newState'])) {
                if (is_array($this->config['bpmData']['stateConfig']['actions'][$this->config['flow']['act']]['results'])) {
                    if (count($this->config['bpmData']['stateConfig']['actions'][$this->config['flow']['act']]['results'])==1) {
                        $this->config['bpmData']['newState']=$this->config['bpmData']['stateConfig']['actions'][$this->config['flow']['act']]['results'][0];
                        $newStateError=False;
                    };
                }
            }
            else{
                if (in_array($this->config['bpmData']['newState'], $this->config['bpmData']['stateConfig']['actions'][$this->config['flow']['act']]['results'])) {
                    $newStateError=False;
                }
            }
            
            if ($newStateError) {
                throw new Exception('BPM: new state error');
            }            
            
            $_REQUEST[$this->bpmModel->tables.'_user']=$_SESSION["user"];
            $_REQUEST[$this->bpmModel->tables.'_id_process']=$_REQUEST[$this->tables.'_'.$this->primaryKey];
            $_REQUEST[$this->bpmModel->tables.'_state']=$this->config['bpmData']['newState'];
            $_REQUEST[$this->bpmModel->tables.'_date']=date('Y-m-d H:i:s');
            $_REQUEST[$this->bpmModel->tables.'_info']=$_REQUEST['bpm_info'];                
            $okOperation=$this->bpmModel->insertRecord();
            if (!$okOperation) {
                throw new Exception("BPM: Insert Error");
            }
        }
        
        
        $options["where"][] = $this->dataRep->dbname.'.'.$this->tables.'.'."$this->primaryKey='".$_REQUEST[$this->tables.'_'.$this->primaryKey]."'";
        $okOperation=$this->update($options);
        
        if ($okOperation) {

            unset($options);
            foreach ($this->subModels as $subModelEnry => $subModelConfig) {

                $subModel=&$subModelConfig['model'];
                $linkerField=$subModelConfig['linkerField'];
                $linkedField=$subModelConfig['linkedField'];
                $linkedDataField=$subModelConfig['linkedDataField'];
                $type=$subModelConfig['type'];

                $_REQUEST[$subModel->tables.'_'.$linkedField]=$_REQUEST[$this->tables.'_'.$linkerField];

                $linkedDataFieldVar=$_REQUEST[$subModel->tables.'_'.$linkedDataField];

                $options['config']['dataFieldConversion']=false;
                $options["where"][] = $subModel->dataRep->dbname.'.'."{$subModel->tables}.$linkedField='{$_REQUEST[$this->tables.'_'.$linkerField]}'";
                //Get actual relations
                $subModelData=$subModel->fetch($options);
                $subModelDataRelationsArray=array();
                foreach ($subModelData['results'] AS $relation){
                    $subModelDataRelationsArray[]=$relation[$linkedDataField];
                }
                unset($options['config']);
                //Delete relations that are not going to be kept preserving those that where selected
                if (is_array($linkedDataFieldVar)) {
                    $options["where"][] = $subModel->dataRep->dbname.'.'."{$subModel->tables}.$linkedDataField NOT IN (".  implode(',', $linkedDataFieldVar).")";
                }
                $okOperation=$subModel->delete($options);
                
                
                //Remove from the actual selected relations the previously selected relations avoiding insert those that allready exists
                if (is_array($linkedDataFieldVar)) {
                    $linkedDataFieldVar=array_diff( $linkedDataFieldVar,$subModelDataRelationsArray );
                }
                unset($options);
                if ($okOperation) {
                    
                    if (is_array($linkedDataFieldVar)) {
                        
                        foreach ($linkedDataFieldVar as $linkedDataFieldValue) {
                            $_REQUEST[$subModel->tables.'_'.$linkedDataField]=$linkedDataFieldValue;
                            $okOperation=$subModel->insertRecord();
                            if (!$okOperation) {
                                break;
                            }
                        }
                    }elseif($linkedDataFieldVar!=''){
                        $okOperation=$subModel->insertRecord();
                        if (!$okOperation) {
                            break;
                        }
                    }
                }
                else break;

            }        
            
        }
        
        return $okOperation;
        
    }

    function delete($options) {
        $whereSql = $this->getWhereConf($options);

        if ($whereSql != "") {
            $sql = "DELETE FROM $this->tables $whereSql";
//            echo $sql;
            try {
                $query = $this->dataRep->pdo->prepare($sql);
                $query->execute();
                $okOperation=true;
            } catch (Exception $exc) {
                $error= new errorTool($this->config);
                $error->log($exc->getMessage());
                $okOperation=false;
            }            
            
        }
        return $okOperation;
    }
    
    /**
     * Generic update tool
     * @param array $options to construct the sentence
     */
    
    function update($options) {

        $whereSql = "";
        if ($options != null) {
            list($whereSql,$setSql) = $this->getSqlUpdateConf($options);
        }

        if ($setSql != "") {
            $sql = "UPDATE $this->tables SET $setSql $whereSql";

            try {
                $query = $this->dataRep->pdo->prepare($sql);
                $query->execute();
                $okOperation=true;
            } catch (Exception $exc) {
                $error= new errorTool($this->config);
                
                $errorMessage=$exc->getMessage();
                $errorCode=$exc->errorInfo[1];
                
                $baseAppTranslations = new base_language();
                $baseAppTranslation = $baseAppTranslations->lang;   
                
                if ($errorCode==$this->dataRep->uniqueErrorCode) {
                    $duplicatedData=$this->dataRep->getFieldFromErrorMessage($errorCode,$errorMessage);
                    if (isset($this->fieldsConfig[$duplicatedData]["definition"]["label"][$this->config["base"]["language"]])) {
                        $duplicatedDataLabel=$this->fieldsConfig[$duplicatedData]["definition"]["label"][$this->config["base"]["language"]];
                    }
                    elseif (isset($this->indexConfig[$duplicatedData]["definition"]["label"][$this->config["base"]["language"]])) {
                        $duplicatedDataLabel=$this->indexConfig[$duplicatedData]["definition"]["label"][$this->config["base"]["language"]];
                    }
                    throw new Exception($baseAppTranslation["uniqueError"].$duplicatedDataLabel);
                }
                else{
                    $error->log($errorMessage);
                    $okOperation=false;
                }
            }
        }
        
        return $okOperation;
    }

    function quickFetch($sql="",$options=array()) {
        if ($sql=="" and count($options)) {
            list($whereSql, $additionalSql, $fields) = $this->getSqlConf($options);
            $sql = "SELECT $fields FROM {$this->tables} $whereSql $additionalSql";
        }

        try {
            if (key_exists('set', $options['config'])) {
                $this->dataRep->pdo->exec("SET CHARACTER SET {$options['config']['set']}");
            }
            
            $query = $this->dataRep->pdo->prepare($sql);
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $exc) {
            $error= new errorTool($this->config);
            $error->show("SQL Error : " . $sql, $exc);
        }

        return $results;        
    }
    
    
    /**
     * Fetchs info according with the model
     * @param array $options to build the sentence
     * @return array with the result
     */
    
    function fetch($options = array()) {
        
        if (!key_exists('config', $options)) {
            $options['config']=array();
        }
        if (!key_exists('dataFieldConversion', $options['config'])) {
            $options['config']['dataFieldConversion']=true;
        }
       
        $whereSql = "";
        $additionalSql = "";
        
//        $relatedTables=array();
        $relatedFields=array();
        $relatedConditions=array();
        $relatedConditions[]=$this->dataRep->dbname.'.'.$this->tables;
        
        
        foreach ($this->fieldsConfig as $field => $configSection) {

            $addField=false;
            
            if (substr($field, 0,5)!="enjoy") {
                
                if (key_exists('fields', $options)) {
                    if (in_array($field, $options['fields'])) {
                        $addField=true;
                    }
                }
                else{
                        $addField=true;
                }
                
                //Quede solo para referencia de uso de eval
//                if (key_exists('modelRelationConfig', $this->fieldsConfig[$field]["definition"])) {
//
//                        $modelRelationConfig = $this->fieldsConfig[$field]["definition"]['modelRelationConfig'];
//                        if (substr($modelRelationConfig,0,5)=='model'){
//                            eval("\$modelRelationConfig=\$this->".$modelRelationConfig);
//
//                            require_once "applications/{$this->config['flow']['app']}/modules/{$modelRelationConfig['module']}/models/model_{$modelRelationConfig['model']}.php";
//
//                            eval("\$relatedModel_{$modelRelationConfig['model']}= new {$modelRelationConfig['model']}Model(\$this->dataRep,\$this->config);");
//                            eval("\${$modelRelationConfig['model']}_tables=\$relatedModel_{$modelRelationConfig['model']}->tables ;");
//                            eval("\$relatedTables[]=\${$modelRelationConfig['model']}_tables;");
//
//                            eval("\$relatedOptions['where'][] =\"{$this->tables}.$field=\${$modelRelationConfig['model']}_tables.{\$relatedModel_{$modelRelationConfig['model']}->primaryKey}\";");
//                            eval("\$relatedFields[]=\"\${$modelRelationConfig['model']}_tables.{\$modelRelationConfig['field']} AS $field\";");
//                            $addField=false;
//                        }
//                }
                
                if (key_exists($field, $this->foreignKeys)) {
                    
                    $fkKeyField = $this->foreignKeys[$field]['keyField'];
                    $fkDataField = $this->foreignKeys[$field]['dataField'];
                    $fkModel = &$this->foreignKeys[$field]['model'];

//                    $relatedTablesOptions=array();
//                    if (isset($this->foreignKeys[$field]['excludedTables'])) {
//                        $relatedTablesOptions=array('excludedTables'=>$this->foreignKeys[$field]['excludedTables']);
//                    }
//                    
//                    $fkRelatedTables=$this->getRelatedTables($fkModel,$relatedTablesOptions);
//                    
//                    foreach ($fkRelatedTables as $fkRelatedTable) {
//                        
//                        if (!in_array($fkRelatedTable, $relatedTables)) {
//                            $relatedTables[]=$fkRelatedTable;
//                        }
//                        
//                    }
                    
                    $relatedConditionsOptions=array();
                    if (isset($this->foreignKeys[$field]['excludedTables'])) {
                        $relatedConditionsOptions["excludedTables"]=$this->foreignKeys[$field]['excludedTables'];
                    }                    
                    if (isset($this->foreignKeys[$field]['joinMode'])) {
                        $relatedConditionsOptions["joinMode"]=$this->foreignKeys[$field]['joinMode'];
                    }                    

                    $fkRelatedConditions=$this->getRelatedConditions($this->tables.'.'.$field,$fkModel,$fkKeyField,$relatedConditionsOptions);
                    
                    foreach ($fkRelatedConditions as $fkRelatedCondition) {
                        $relatedConditions[]=$fkRelatedCondition;
                    }                    
                    
                    
                    if (substr($fkDataField,0,1)=='_') { //It is a computed field
                        
                        if ($options['config']['dataFieldConversion']) {
                            
                            $fkDataField=substr($fkDataField, 1);
                            $relatedFields[]="$fkDataField AS $field";
                        }
                        else
                            $relatedFields[]=$this->tables.'.'.$field;
                        
                    }
                    else{
                        if ($addField and $options['config']['dataFieldConversion'])
                            $relatedFields[]="{$fkModel->tables}.$fkDataField AS $field";
                        else
                            $relatedFields[]=$this->tables.'.'.$field;
                    }
                    
                    $addField=false;
                    
                }
            }
            
            if ($addField and $this->config['flow']['act']!='dataCall') {
                $relatedFields[]=$this->tables.'.'.$field;
            }
            
        }
        
         if (key_exists('fields', $options)) {
             
             foreach ($options['fields'] as $optionField) {
               if (substr($optionField, 0, 1) == '_') { //It is a computed field
                   $optionField = substr($optionField, 1);
                   $relatedFields[] = "$optionField";
               }
               elseif (!key_exists ($optionField,$this->fieldsConfig)) {//It is a subquery or something
                   $relatedFields[] = "$optionField";
               }
    
            }
             
        }
        
        if (count($options)) {
            $getTotal = false;
            if (key_exists("additional", $options)) {
                foreach ($options["additional"] as $additionalComponent) {
                    if (substr(strtoupper($additionalComponent), 0, 5) == "LIMIT") {
                        $getTotal = true;
                        break;
                    }
                }
            }

            if ($getTotal and key_exists('where', $options )) {
                
                $makeSubModelRelations=true;
                if (isset($options['config']['subModelRelations'])) {
                    if ($options['config']['subModelRelations']==false) {
                        $makeSubModelRelations=false;
                    }
                }
                
                if ($makeSubModelRelations) {
                    foreach ($this->subModels as $subModelEnry => $subModelConfig) {

                        $subModel=&$subModelConfig['model'];
                        $linkerField=$subModelConfig['linkerField'];
                        $linkedField=$subModelConfig['linkedField'];

//                        $relatedTables[]=$subModel->dataRep->dbname.'.'.$subModel->tables;
                        $relatedConditions[]=" INNER JOIN ".$subModel->dataRep->dbname.'.'.$subModel->tables." ON ".$this->dataRep->dbname.'.'.$this->tables.'.'.$linkerField.'='.$subModel->dataRep->dbname.'.'.$subModel->tables.'.'.$linkedField;

                    }   
                }
            }
        }

        $options['fields']=$relatedFields;
        $tables=implode(' ', $relatedConditions);
        
        list($whereSql, $additionalSql, $fields) = $this->getSqlConf($options);
        
        if ($getTotal) {

            $sql = "SELECT count(*) AS total FROM $tables $whereSql $additionalSql";

            try {
                $query = $this->dataRep->pdo->prepare($sql);
                $query->execute();
                $results = $query->fetchAll(PDO::FETCH_ASSOC);
                $resultArray["totalRegisters"] = $results[0]["total"];
            } catch (Exception $exc) {
                $error = new errorTool($this->config);
                $error->show("SQL Error : " . $sql, $exc);
            }

        }        
        
        if (isset($options['config']['getBpmState'])) {
            if ($options['config']['getBpmState']===true) {
                $options['fields'][]="(SELECT {$this->tables}_bpm.state FROM {$this->tables}_bpm WHERE {$this->tables}_bpm.id_process={$this->tables}.{$this->primaryKey} ORDER BY {$this->tables}_bpm.id DESC LIMIT 1) AS bpm_state";
                list($whereSql, $additionalSql, $fields) = $this->getSqlConf($options);
            }
        }        
        
        $sql = "SELECT $fields FROM $tables $whereSql $additionalSql";
        //echo $this->tables."--><br>".$sql."<hr>";

        try {
            if (key_exists('set', $options['config'])) {
                $this->dataRep->pdo->exec("SET CHARACTER SET {$options['config']['set']}");
            }
            
            $query = $this->dataRep->pdo->prepare($sql);
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $exc) {
            $error= new errorTool($this->config);
            $error->show("SQL Error : " . $sql, $exc);
        }

        $resultArray["results"] = $results;

        return $resultArray;
    }

    /**
     * Parses the $options array to build the WHERE
     * @param type $options
     * @return string
     */
    
    protected function getWhereConf($options) {

        if (key_exists("where", $options)) {
            $newWhereConditionsArray=array();
            foreach ($options["where"] as $whereCondition) {
                $newWhereConditionsArray[]="(".$whereCondition.")";
            }
            $whereSql = " WHERE ";
            $whereSql.= implode(" AND ", $newWhereConditionsArray);
        } else {
            $whereSql = "";
        }

        return $whereSql;
    }

     /**
     * Parses the $options array to build the SQL sentence
     * @param type $options
     * @return array with $whereSql, $additionalSql, $fields
     */
    protected function getSqlConf($options) {

        $whereSql = $this->getWhereConf($options);
        
        if (key_exists("additional", $options)) {
            $additionalSql = implode(" ", $options["additional"]);
        } else {
            $additionalSql = "";
        }
        if (key_exists("fields", $options)) {
            $fields = implode(",", $options["fields"]);
        } else {
            $fields = "*";
        }

        return array($whereSql, $additionalSql, $fields);
    }
    
    /**
     * Parses the $options to build an update SQL
     * @param type $options
     * @return array with $whereSql, $setSql
     */
    
    protected function getSqlUpdateConf($options) {

        if (key_exists("where", $options)) {
            $whereSql = " WHERE ";
            $whereSql.= implode(" AND ", $options["where"]);
        } else {
            $whereSql = "";
        }
        if (key_exists("set", $options)) {
            $setSql = implode(",", $options["set"]);
        } else {
            $setSql = "";
        }

        return array($whereSql, $setSql);
    }

    /**
     * Parses the $options to build an insert SQL
     * @param type $options
     * @return array with $fieldsSql, $valuesSql
     */    
    
    protected function getInsertConf($options) {

        if (key_exists("fields", $options)) {
            $fieldsSql = "(";
            $fieldsSql.= implode(",", $options["fields"]);
            $fieldsSql.=")";
        } else {
            $fieldsSql = "";
        }
        if (key_exists("values", $options)) {

            $rowCounter=1;
            foreach ($options["values"] as $valuesRow) {
                
                if ($rowCounter > 1) {
                    $valuesSql.=",";
                }
                
                $valuesSql .= "(";
                $valuesSql .= implode(",", $valuesRow);
                $valuesSql .=")";
                
                $rowCounter ++;
            }

        } else {
            $valuesSql = "";
        }

        return array($fieldsSql, $valuesSql);
    }
    
    /**
     * Recursive Method to get all the tables asociated with a model throug the foreign keys
     * @param type $fkModel
     * @return array with the tables
     */

//    function getRelatedTables(&$fkModel,$options=array()) {
//        
//        if (!isset($options['excludedTables'])) {
//            $options['excludedTables']=array();
//        }
//
//        foreach ($fkModel->foreignKeys as $foreignKey) {
//            
//            $fkExcludedTables=array();
//            if (isset($foreignKey['excludedTables'])) {
//                foreach ($foreignKey['excludedTables'] as $fkExcludedTable) {
//                    $fkExcludedTables[]=$fkExcludedTable;
//                }
//            }
//            $options["excludedTables"]=array_unique(array_merge($options["excludedTables"],$fkExcludedTables));
//            
//            if (!in_array($foreignKey['model']->tables,$options["excludedTables"])) {
//                foreach ($fkModel->getRelatedTables($foreignKey['model'],$options) as $fkRelatedTable) {
//                    $relatedTables[]=$fkRelatedTable;
//                }
//            }
//            
//        }     
//        
//        return $relatedTables;
//    }
    
    /**
     * Recursive Method to get all the conditions asociated with a model throug the foreign keys
     * @param type $field foreign key Field
     * @param type $fkModel
     * @param type $fkKeyField field referenced by the foreign key Field
     * @return array with the conditions
     */
    
    function getRelatedConditions($field,&$fkModel,$fkKeyField,$options=array()) {

        if (!isset($options['excludedTables'])) {
            $options['excludedTables']=array();
        }
        if (!isset($options['joinMode'])) {
            $options['joinMode']="INNER";
        }        
        
        $relatedConditions=array();
        $relatedConditions[]=$options['joinMode'] ." JOIN ".$fkModel->dataRep->dbname.'.'.$fkModel->tables." ON $field=$fkKeyField ";
   
        foreach ($fkModel->foreignKeys as $foreignKey => $foreignKeyConfig ) {
            
            $fkExcludedTables=array();
            if (isset($foreignKeyConfig['excludedTables'])) {
                foreach ($foreignKeyConfig['excludedTables'] as $fkExcludedTable) {
                    $fkExcludedTables[]=$fkExcludedTable;
                }
            }
            $options["excludedTables"]=array_unique(array_merge($options["excludedTables"],$fkExcludedTables));
//            if (isset($foreignKeyConfig['joinMode'])) {
//                $options['joinMode']=$foreignKeyConfig['joinMode'];
//            }
//            else{
//                unset($options['joinMode']);
//            }
            if (!in_array($foreignKeyConfig['model']->tables,$options["excludedTables"])) {
                foreach ($fkModel->getRelatedConditions($fkModel->tables.'.'.$foreignKey,$foreignKeyConfig['model'],$foreignKeyConfig['keyField'],$options) as $relatedCondition) {
                    $relatedConditions[]=$relatedCondition;
                }
            }
            
        }         

        return $relatedConditions;
        
    }
    
    /**
     * Recursive Method to get the data field registers asociated with a model throug the foreign keys
     * @param type $keyField for example the id of the register
     * @param type $dataField for example other field of the register
     * @return array with the label or caption data field
     */
    
    function getFieldData($keyField,$dataField,$options=array()) {

        if (key_exists($dataField, $this->foreignKeys)) {
            
            $fkKeyField = $this->foreignKeys[$dataField]['keyField'];
            $fkDataField = $this->foreignKeys[$dataField]['dataField'];
            
            $fkModel = &$this->foreignKeys[$dataField]['model'];
            $results=$fkModel->getFieldData($fkKeyField,$fkDataField,$options);
            return $results;
            
            
        } else {

            if (substr($dataField, 0, 1) == '_') { //It is a computed field
                $dataField = substr($dataField, 1);
                $options["fields"][] = "_$dataField AS relation_field";
            }
            else {
                $options["fields"][] = "_{$this->tables}.$dataField AS relation_field";
            }
            
            $options["fields"][] = "_$keyField AS relation_id";

            $options['config']['dataFieldConversion']=false;
            $resultData=$this->fetch($options);
            $resultData['label']=$this->fieldsConfig[$dataField]["definition"]["label"][$this->config["base"]["language"]];
            
            return $resultData;
        }
    }
    
    /**
     * Returns the instance of a model helping width:
     * -requiring the apropiate model file
     * -avoiding infinite loops whith models that instances each other
     * @param String $calledModelName is the model than wants to be instanciated
     * @param String $callingModelName is the model that calls this method; if is the same than $this->tables, can be ommited
     */
    
    function getModuleModelInstance($calledModelName,$callingModelName="") {
      
        if ($callingModelName=="") {
            $callingModelName=$this->tables;
        }
        
        if (key_exists($calledModelName, $this->incomingModels)) { // Technique to avoid circular calls
            $instancedModel=&$this->incomingModels[$calledModelName];
        }
        else{
            $outgoingModels = array(
                $callingModelName => $this,
            );
            require_once "applications/{$this->config['flow']['app']}/modules/{$calledModelName}/models/model_{$calledModelName}.php";
            eval("\$instancedModel= new {$calledModelName}Model(\$this->dataRep,\$this->config,\$outgoingModels);");
        }          
        return $instancedModel;
    }
    
}

?>