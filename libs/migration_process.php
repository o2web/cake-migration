<?php
	class MigrationProcess extends Object {
		var $targetInstance;
		var $batches = array();
		var $autoSelect = array();
		var $invalidated = array();
    var $models = array();
    
		function __construct ($targetInstance){
			App::import('Lib', 'Migration.Migration');
			App::import('Lib', 'Migration.MigrationBatch');
			$this->targetInstance = $targetInstance;
      Migration::currentProcess($this);
		}
		
		var $msgs = array();
		function msg($msg){
			$this->msgs[] = $msg;
		}
		
    function setModelOpt($modelName,$opt){
      if(!empty($opt['instances'][$this->targetInstance])){
        $opt['instance'] = $opt['instances'][$this->targetInstance];
      }
      $this->models[$modelName] = $opt;
    }
    
    function run(){
      // debug($this->models);
      foreach($this->models as $modelName => $opt){
        $this->processBatch($modelName,$opt);
      }
      $this->autoSolve();
    }
    
		function processBatch($modelName,$options = array()){
			$Model = Migration::getLocalModel($modelName);
			$this->batches[] = $batch = new MigrationBatch($this,$Model,$options);
			$batch->process();
      $batch->processDeletions();
			return $batch;
		}
		
		function getBatchFor($modelName){
			foreach ($this->batches as $batch) {
				if($batch->LocalModel->getFullName() == $modelName){
					return $batch;
				}
			}
		}
		
		function autoSolve(){
			while(($batch = $this->nextAutoSelectBatch()) || ($invalidated = $this->nextInvalidatedGroup())){
				if($batch){
					$batch->process();
				}else{
					foreach($invalidated as $modelName => $ids){
						$batch = $this->getBatchFor($modelName);
						$batch->reprocess($ids);
					}
					
					$dry = MigrationConfig::load('dryRun');
					if($dry) break;
				}
			}
		}
		
		function nextAutoSelectBatch(){
			if(!empty($this->autoSelect)){
				$modelName = $this->autoSelect[0]['model'];
				$ids = array();
				foreach($this->autoSelect as $i => $sel){
					$ids[] = $sel['local_id'];
					unset($this->autoSelect[$i]);
				}
				$this->autoSelect = array_values($this->autoSelect);
				$Model = Migration::getLocalModel($modelName);
				$this->batches[] = $batch = new MigrationBatch($this,$Model,array('conditions'=>array($Model->alias.'.'.$Model->primaryKey => $ids)));
				return $batch;
			}
		}
		
		function nextInvalidatedGroup(){
			if(!empty($this->invalidated)){
				$modelName = $this->invalidated[0]['model'];
				$ids = array();
				foreach($this->invalidated as $i => $sel){
					$ids[] = $sel['local_id'];
					unset($this->invalidated[$i]);
				}
				$this->invalidated = array_values($this->invalidated);
				return array($modelName=>$ids);
			}
		}
		
	}
?>