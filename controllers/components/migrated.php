<?php
class MigratedComponent extends Object {
	
	var $components = array('Session');
	var $modelsOpt = array();
	var $modes = array('exclude' , 'included');
	
	function initialize(&$controller) {
		
	}
	
	function &getModelOpt($alias,$key=null){
    $alias = end(explode('.',$alias));
		if(!empty($this->modelsOpt[$alias])){
			$opt = &$this->modelsOpt[$alias];
		}else{
      $opt = $this->Session->read('Migration.migrated.'.$alias);
      if(empty($opt)){
        $opt = array(
          'mode'=>'exclude',
          'execeptions'=>array(),
          'instances'=>array(),
          'force'=>array(),
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
  
	function &getModelInstanceOpt($alias,$instance,$key=null){
    $opt = &$this->getModelOpt($alias);
    if(empty($opt['instances'][$instance])){
      $opt['instances'][$instance] = array(
				'execeptions'=>array(),
			);
    }
    if(is_null($key)){
      return $opt['instances'][$instance];
    }else{
      return $opt['instances'][$instance][$key];
    }
  }
  
	
	function clear(){
		$this->modelsOpt = array();
		$this->Session->write('Migration.migrated',array());
	}
	
	function saveModelOpt($alias){
    $alias = end(explode('.',$alias));
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
	function updateStates($alias,$assoc, $instance = null){
		foreach ($assoc as $id => $val) {
			$this->updateState($alias,$assoc,$id,$val,false, $instance);
		}
		$this->saveModelOpt($alias);
	}
	
	function updateState($alias,$assoc,$id,$val,$save = true, $instance = null){
    if($instance){
      $opt = &$this->getModelInstanceOpt($alias,$instance);
    }else{
      $opt = &$this->getModelOpt($alias);
    }
		if(($this->getModelOpt($alias,'mode') == 'exclude') == !$val){ 
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
	
	function getEntriesStates($Model,$entries, $instance = null){
		$ids = array();
    if(empty($instance)){
      $alias = $Model->alias;
    }else{
      $remoteModel = Migration::getRemoteModel($Model,$instance);
      $alias = $remoteModel->alias;
    }
		foreach ($entries as $entry) {
			$ids[] = $entry[$alias][$Model->primaryKey];
		}
		return $this->getIdsStates($Model->alias,$ids);
	}
	function getIdsStates($alias,$ids, $instance = null){
		$data = array();
    if($instance){
      $opt = &$this->getModelInstanceOpt($alias,$instance);
    }else{
      $opt = &$this->getModelOpt($alias);
    }
		foreach ($ids as $id) {
			$key = array_search($id, $opt['execeptions']);
			$data[$id] = ($this->getModelOpt($alias,'mode') == 'exclude') == ($key === false);
		}
		return $data;
	}
	
	function alterCount($alias,$count){
		if(!$count) return $count;
		$opt = &$this->getModelOpt($alias);
		if($opt['mode'] == 'exclude'){
			return $count - count($opt['execeptions']) - $this->countInstancesExeceptions($alias);
		}else{
			return count($opt['execeptions']) + $this->countInstancesExeceptions($alias);
		}
	}
  
  function countInstancesExeceptions($alias){
		$opt = &$this->getModelOpt($alias);
    $count = 0;
    if(!empty($opt['instances'])){
      foreach($opt['instances'] as $instOpt){
        $count += count($instOpt['execeptions']);
      }
    }
    return $count;
  }
	
}