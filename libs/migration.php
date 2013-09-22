<?php
class Migration extends Object {
	/*
		App::import('Lib', 'Migration.Migration');
	*/
	
	function modelList(){
		$exclude = array('AppModel');
		$models = App::objects('model',null,false);
		$plugins = App::objects('plugin');
		foreach($plugins as $pname){
			$path = App::pluginPath($pname);
			$pmodels = App::objects('model',$path.DS.'models',false);
			foreach($pmodels as $mname){
				$models[] = $pname.'.'.$mname;
			}
		}
		return array_values(array_diff($models,$exclude));
	}
	
	function migratingModels(){
		if(($cached = Cache::read('migrating_models'))!==false){
			return $cached;
		}
		
		App::import('Lib', 'Migration.MigrationConfig');
		$preset = MigrationConfig::load('preset');
		
		$models = Migration::modelList();
		$migrated = array_values(array_intersect($models,array_keys($preset)));
		foreach(array_diff($models,$migrated) as $mname){
			if(Migration::testModelIntegrity($mname)){
				$Model = ClassRegistry::init($mname);
				if($Model->Behaviors->attached('Migration')){
					$migrated[] = $mname;
				}
			}
		}
		
		Cache::write('migrating_models', $migrated);
		return $migrated;
	}
	
	function clearCache(){
		Cache::delete('migrating_models');
	}
	
	function pendingList(){
		$MigrationRemote = ClassRegistry::init('Migration.MigrationRemote');
		$models = Migration::migratingModels();
		$list = array();
		foreach($models as $mname){
			$Model = Migration::getLocalModel($mname);
			$count = $Model->migrationPendingCount();
			$list[$mname] = $count[0]['count'];
		}
		return $list;
	}
	
	function targetList(){
		App::import('Lib', 'Migration.MigrationConfig');
		$targets = MigrationConfig::load('target_instances');
		$list = array();
		foreach($targets as $key => $opt){
			$list[$key] = !empty($opt['label'])?$opt['label']:__(inflector::humanize($key),true);
		}
		return $list;
	}
	
	function processBatch($modelName,$targetInstance,$limit = 20){
		$Model = Migration::getLocalModel($modelName);
		$Model->migrateBatch($targetInstance,$limit);
	}
	
	function getLocalModel($name){
		$Model = ClassRegistry::init($name);
		if(!$Model->Behaviors->attached('Migration')){
			App::import('Lib', 'Migration.MigrationConfig');
			$preset = MigrationConfig::load('preset');
			if(!empty($preset[$name])){
				$Model->Behaviors->attach('Migration.Migration', $preset[$name]);
			}
		}
		App::import('Lib', 'Migration.MigrationConfig');
		$harmfullBehaviors = MigrationConfig::load('harmfullBehaviors');
		foreach($harmfullBehaviors as $b){
			$Model->Behaviors->detach($b);
		}
		$Model->setPluginName(Migration::classNameParts($name,'plugin'));
		
		return $Model;
	}
	
	function getRemoteModel($localModel,$instance){
		static $remotesModels;
		if(!empty($remotesModels[$instance.'__'.$localModel->alias])) return $remotesModels[$instance.'__'.$localModel->alias];
		
		$ds = Migration::getInstanceDS($instance);
		$obj = new Model(array('table' => $localModel->useTable,'alias'=>Inflector::classify($instance).$localModel->alias,'ds' =>$ds));
		$obj->primaryKey = $localModel->primaryKey;
		$remotesModels[$instance.'__'.$localModel->alias] = $obj;
		
		return $obj;
	}
	
	function getInstanceDS($instance){
		static $remoteDS;
		if(!empty($remoteDS[$instance])) $remoteDS[$instance];
	
		$name = 'remote_'.$instance;
		$targets = MigrationConfig::load('target_instances');
		$default = ConnectionManager::getDataSource('default');
		$opt = array_merge($default->config,$targets[$instance]);
		if(ConnectionManager::create($name,$opt)){
			$remoteDS[$instance] = $name;
			return $name;
		}else{
			$remoteDS[$instance] = null;
			return null;
		}
	}
	
	function testModelIntegrity($name){
		if(App::import('Model', $name)){
			$cname = Migration::classNameParts($name,'class');
			$vars = get_class_vars($cname);
			if($vars['useTable'] !== false){
				$table = $vars['useTable']?$vars['useTable']:Inflector::tableize($cname);
				$db = ConnectionManager::getDataSource($vars['useDbConfig']);
				if ($db->isInterfaceSupported('listSources')) {
					$sources = $db->listSources();
					if (is_array($sources) && !in_array(strtolower($table), array_map('strtolower', $sources))) {
						return false;
					}
				}
			}else{
				return false;
			}
		}else{
			return false;
		}
		return true;
	}
	
	
	function classNameParts($name,$key=null){
		$part = explode('.',$name,2);
		if(count($part)>1){
			$part = array('plugin'=>$part[0],'class'=>$part[1]);
		}else{
			$part = array('plugin'=>null,'class'=>$part[0]);
		}
		if($key){
			if(isset($part[$key])){
				return $part[$key];
			}
			return null;
		}
		return $part;
	}
}