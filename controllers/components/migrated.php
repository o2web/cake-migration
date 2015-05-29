<?php
class MigratedComponent extends Object {
	
	var $components = array('Session');
	var $modelsOpt = array();
	var $modes = array('exclude' , 'included');
	
	function initialize(&$controller) {
		
	}
	
	function &getModelOpt($alias,$key=null){
		if(!empty($this->modelsOpt[$alias])){
			$opt =$this->modelsOpt[$alias];
		}else{
		$opt = $this->Session->read('Migration.migrated.'.$alias);
		if(empty($opt)){
			$opt = array(
				'mode'=>'exclude',
				'execeptions'=>array()
			);
		}
		$this->modelsOpt[$alias] = &$opt;
    }
    if(is_null($key)){
		return $opt;
    }else{
      return $opt[$key];
    }
	}
	
	function clear(){
		$this->modelsOpt = array();
		$this->Session->write('Migration.migrated',array());
	}
	
	function saveModelOpt($alias){
		$opt = $this->getModelOpt($alias);
		$this->Session->write('Migration.migrated.'.$alias,$opt);
		// debug($opt);
	}
	
	function getMode($alias){
		return $this->getModelOpt($alias,'mode');
	}
	function setMode($alias,$val){
		if(!in_array($val,$this->modes)) $val = $this->modes[$val?1:0];
		$opt = &$this->getModelOpt($alias);
		$opt['mode'] = $val;
		$this->saveModelOpt($alias);
	}
	function isIncludeMode($alias){
		return $this->getModelOpt($alias,'mode') != 'exclude';
	}
	function updateStates($alias,$assoc){
		foreach ($assoc as $id => $val) {
			$this->updateState($alias,$assoc,$id,$val,false);
		}
		$this->saveModelOpt($alias);
	}
	
	function updateState($alias,$assoc,$id,$val,$save = true){
		$opt = &$this->getModelOpt($alias);
		if(($opt['mode'] == 'exclude') == !$val){ 
			// is an exception
			$key = array_search($id, $opt['execeptions']);
			if($key === false){
				$opt['execeptions'][] = $id;
			}
		}else{
			$opt['execeptions'] = array_diff($opt['execeptions'], array($id));
		}
		if($save) $this->saveModelOpt($alias);
	}
	
	function getEntriesStates($Model,$entries){
		$ids = array();
		foreach ($entries as $entry) {
			$ids[] = $entry[$Model->alias][$Model->primaryKey];
		}
		return $this->getIdsStates($Model->alias,$ids);
	}
	function getIdsStates($alias,$ids){
		$data = array();
		$opt = &$this->getModelOpt($alias);
		foreach ($ids as $id) {
			$key = array_search($id, $opt['execeptions']);
			$data[$id] = ($opt['mode'] == 'exclude') == ($key === false);
		}
		return $data;
	}
	
	function alterCount($alias,$count){
		if(!$count) return $count;
		$opt = &$this->getModelOpt($alias);
		if($opt['mode'] == 'exclude'){
			return $count - count($opt['execeptions']);
		}else{
			return count($opt['execeptions']);
		}
	}
	
	function getCond($Model){
		if(is_string($Model)){
			App::import('Lib', 'Migration.Migration');
			$Model = Migration::getLocalModel($Model);
		}
		$opt = &$this->getModelOpt($Model->alias);
		if(empty($opt['execeptions'])){
			return array();
		}
		$key = '`'.$Model->alias.'`.`'.$Model->primaryKey.'`';
		if($opt['mode'] == 'exclude'){
			return array('not'=>array($key=>$opt['execeptions']));
		}else{
			return array($key=>$opt['execeptions']);
		}
	}
	function findOpt($Model){
		$cond = $this->getCond($Model);
		if(!empty($cond)){
			return array('conditions'=>$cond);
		}else{
			return array();
		}
	}
}