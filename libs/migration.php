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
	
  function currentProcess($val = null){
    static $currentProcess = null;
    if(!is_null($val)){
      $currentProcess = $val;
    }
    return $currentProcess;
  }
  
	function msg($msg){
    if(Migration::currentProcess()){
      Migration::currentProcess()->msg($msg);
    }else{
      debug($msg);
    }
	}
	
	function migratingModels(){
		$migrated = Cache::read('migrating_models');
		
		if(empty($migrated)){
			App::import('Lib', 'Migration.MigrationConfig');
			$preset = MigrationConfig::load('preset');
			$full = MigrationConfig::load('full');
			
			$models = Migration::modelList();
			$migrated = array_values(array_intersect($models,array_keys($preset)));
			foreach(array_diff($models,$migrated) as $mname){
				if(!Migration::modelIsExcluded($mname) && Migration::testModelIntegrity($mname)){
					$Model = ClassRegistry::init($mname);
					if($Model->hasField('modified') && ($full || $Model->Behaviors->attached('Migration'))){
						$p = explode('.',$mname);
						$migrated[end($p)] = $mname;
					}
				}
			}
			
			Cache::write('migrating_models', $migrated);
		}
		
		return $migrated;
	}
  
  function modelIsExcluded($modelName){
		$exclude = MigrationConfig::load('exclude');
    foreach($exclude as $ex){
      if($ex == $modelName) return true;
      if(strpos($ex,'*') !== false && preg_match('/^'.str_replace('\*','.*',preg_quote($ex)).'$/i',$modelName)) return true;
    }
    return false;
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
      
      $up = $Model->migrationPendingCount();
      if(!empty($up)){
        $list[$mname]['update'] = $up;
      }
      
      $del = $Model->migrationDeletedCount();
      if(!empty($del)){
        $list[$mname]['delete'] = $del;
      }
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
	
	function getLocalModel($name){
		$Model = ClassRegistry::init($name);
		if(!$Model->Behaviors->attached('Migration')){
			App::import('Lib', 'Migration.MigrationConfig');
			$preset = MigrationConfig::load('preset');
      $options = array();
			if(!empty($preset[$name])){
				$options = $preset[$name];
			}
			$behaviorsConfig = MigrationConfig::load('behaviorsConfig');
      $behaviorsConfig = array_intersect_key($behaviorsConfig,array_flip($Model->Behaviors->attached()));
      foreach($behaviorsConfig as $conf){
        $options = Set::merge($options,$conf);
      }
      
      $Model->Behaviors->attach('Migration.Migration', $options);
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
		if(empty($ds)) return null;
		$obj = new Model(array('table' => $localModel->useTable,'alias'=>Inflector::classify($instance).$localModel->alias,'ds' =>$ds));
		
		$obj->primaryKey = $localModel->primaryKey;
		$remotesModels[$instance.'__'.$localModel->alias] = $obj;
			
		return $obj;
	}
	
	function getInstanceDS($instance){
		static $remoteDS;
		if(!empty($remoteDS[$instance])) return $remoteDS[$instance];
	
		$name = 'remote_'.$instance;
		
		App::import('Lib', 'Migration.MigrationConfig');
		$targets = MigrationConfig::load('target_instances');
		$default = ConnectionManager::getDataSource('default');
		$instanceOpt = $targets[$instance];
		if(empty($instanceOpt['database']) || is_string($instanceOpt['database'])){
			$instanceOpt['database'] = array_intersect_key($instanceOpt, array_flip(array('driver', 'persistent', 'host', 'login', 'password', 'database', 'prefix', 'encoding')));
		}
		$opt = array_merge($default->config,$instanceOpt['database']);
		//debug($opt);
		//exit();
		if($ds = ConnectionManager::create($name,$opt)){
			//debug($ds);
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
	
	function modelNameToUrl($modelName){
		return implode('-',array_map(array('Inflector','underscore'),explode('.',$modelName)));
	}
	function urlParamToModelName($param){
		return implode('.',array_map(array('Inflector','camelize'),explode('-',$param)));
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
	
	function extractPath($data, $path = null) {
		if (empty($path)) {
			return $data;
		}
		if (is_object($data)) {
			$data = get_object_vars($data);
		}

		if (!is_array($path)) {
			if (!class_exists('String')) {
				App::import('Core', 'String');
			}
			$parts = String::tokenize($path, '.', '{', '}');
		}else{
			$parts = $path;
		}
		
		if (!is_array($data)) {
			return array();
		}
		
		$tmp = array();

		if (empty($parts) || !is_array($parts)) {
			return array();
		}
		
		$key = reset($parts);
		
		$tmpPath = array_slice($parts, 1);
		if (is_numeric($key) && intval($key) > 0 || $key === '0') {
			if (isset($data[intval($key)])) {
				$tmp[intval($key)] = $data[intval($key)];
			} else {
				return array();
			}
		} elseif ($key === '{n}') {
			foreach ($data as $j => $val) {
				if (is_int($j)) {
					$tmp[$j] = $val;
				}
			}
		} elseif ($key === '{s}') {
			foreach ($data as $j => $val) {
				if (is_string($j)) {
					$tmp[$j] = $val;
				}
			}
		} elseif (false !== strpos($key,'{') && false !== strpos($key,'}')) {
			$pattern = substr($key, 1, -1);

			foreach ($data as $j => $val) {
				if (preg_match('/^'.$pattern.'/s', $j) !== 0) {
					$tmp[$j] = $val;
				}
			}
		} else {
			if (isset($data[$key])) {
				$tmp[$key] = $data[$key];
			} else {
				return array();
			}
		}
		
		$res = array();
		if (!empty($tmpPath)) {
			foreach($tmp as $key => $val){
				$res2 = Migration::extractPath($val,$tmpPath);
				foreach($res2 as $key2 => $val2){
					$res[$key.'.'.$key2] = $val2;
				}
			}
		}else{
			return $tmp;
		}
			
		return $res;
	}
	
	function updateAllTracking($data){
		//debug($data);
		foreach($data as $model => $mdata){
			$Model = Migration::getLocalModel($model);
			$Model->updateMigrationTracking($mdata);
		}
	}
	
	function getMultimediaAssoc($behavior,$Model,$assoc){
		if(!empty($Model->multimedia)){
			$add = array();
			$settings = $behavior->settings[$Model->alias];
			$exclude = array_merge(array($Model->primaryKey),$settings['excludeFields']);
			//debug($Model->multimedia);
			foreach($Model->multimedia as $field => $opt){
				if(!in_array($field,$exclude)){
					$add[Inflector::camelize($field)] = array(
						'className' => 'Multimedia.Multimedium',
						'path' => $field.'.{n}.id',
						'unsetLevel' => 2,
						'autoTrack' => true,
						'autoSelect' => true,
					);
				}
			}
			return $add;
		}
	}
	
	function solveURI($uri,$paths = null,$basepath=null,$ds = null,$defaultPath = 'app'){
		if(is_null($ds)) $ds = DS;
		if(is_null($paths)) $paths = array(
			'app' => APP,
			'webroot' => WWW_ROOT,
			'tmp' => TMP,
		);
		if(!is_array($paths)) {
			$paths = array($defaultPath=>$paths);
			if($defaultPath == 'app'){
				$paths['webroot'] = $paths['app'].$ds.'webroot';
				$paths['tmp'] = $paths['app'].$ds.'tmp';
			}
		}
		$parts = explode(':',$uri,2);
		if(count($parts)>1){
			$s = $parts[0];
			$p = $parts[1];
		}else{
			$s = $defaultPath;
			$p = $uri;
		}
		if(empty($s)){
			return null;
		}
		if(empty($paths[$s]) && strlen($s) == 1){ //if true we asume it's an absolute path ex: "c:/folder1/folder2"
			return $uri;
		}elseif(empty($paths[$s])){
			return null;
		}
		$l = rtrim($paths[$s],$ds).$ds.trim(str_replace('/',$ds,$p),$ds);
		if(!empty($basepath)){
			if(strpos($basepath,':') !== false) { 
				$basepath = solveURI($basepath,$paths,null,$ds,$defaultPath);
				if(empty($basepath)){
					return null;
				}
			}
			$basepath = rtrim($basepath,$ds).$ds;
			if(strpos($l,$basepath) !== 0){
				return null;
			}
			$l = substr($l,strlen($basepath));
		}
		return $l;
	}
	
	function sendFile($localURI,$instance){
		$localFile = Migration::solveURI($localURI);
		//debug($localFile);
		if(!empty($localFile) && file_exists($localFile)){
			App::import('Lib', 'Migration.MigrationConfig');
			$targets = MigrationConfig::load('target_instances');
			$instanceOpt = $targets[$instance];
			$instanceOpt['name'] = $instance;
			if(isset($instanceOpt['ftp'])) $instanceOpt['fileMode'] = 'ftp';
			if(isset($instanceOpt['path'])) $instanceOpt['fileMode'] = 'local';
			
			if(!empty($instanceOpt['fileMode'])){
				$f = $instanceOpt['fileMode'];
				if(strpos($f,'::') === false){
					$f = array('Migration','sendFile'.Inflector::camelize($f));
				}else{
					$f = explode('::',$f);
				}
				$res = call_user_func($f,$localFile,$localURI,$instanceOpt);
				return !empty($res);
			}
		}
		return false;
	}
	
	
	function sendFileFtp($localFile,$uri,$instanceOpt){
		$defOpt = array(
			'host' => null,
			'port' => 21,
			'username' => null,
			'password' => null,
			'timeout' => 90,
			'basepath' => null,
			'paths' => (!empty($instanceOpt['basepath']) && ($appPos = strpos($instanceOpt['basepath'],'app')) !== false)?substr($instanceOpt['basepath'],0,$appPos+3):'app',
			'mode' => FTP_BINARY,
      'ssl' => false,
		);
		$ftpOpt = array_merge($defOpt,$instanceOpt['ftp']);
		// debug($ftpOpt);
		
		$res = false;
		
    if($ftpOpt['ssl'] && function_exists('ftp_ssl_connect')){
      $conn_id = ftp_ssl_connect($ftpOpt['host'],$ftpOpt['port'],$ftpOpt['timeout']); 
    }else{
		$conn_id = ftp_connect($ftpOpt['host'],$ftpOpt['port'],$ftpOpt['timeout']); 
    }
		if(!$conn_id){
			Migration::msg('Connection failed');
			return false;
		}
		
		if (@ftp_login($conn_id, $ftpOpt['username'], $ftpOpt['password'])) {
			$remotefile = '/'.Migration::solveURI($uri,$ftpOpt['paths'],$ftpOpt['basepath'],'/');
			// debug($remotefile);
			if(!empty($remotefile)){
				if(Migration::ftpCreateFoldersFor($conn_id, $remotefile)){
					App::import('Lib', 'Migration.MigrationConfig');
					$dry = MigrationConfig::load('dryRun');
					if($dry){
						Migration::msg('Attempt copy file '.$localFile.' to '.$remotefile);
						$res = true;
					}else{
						$res = ftp_put($conn_id, $remotefile, $localFile, $ftpOpt['mode']);
					}
				}
			}
		}else{
			Migration::msg('Login failed');
		}
		
		ftp_close($conn_id);  
		
		return $res;
	}
	function ftpCreateFoldersFor($conn_id,$file){
		$folder = dirname($file);
		if(!@ftp_chdir($conn_id, $folder)){
			App::import('Lib', 'Migration.MigrationConfig');
			if(Migration::ftpCreateFoldersFor($conn_id,$folder)){
				$dry = MigrationConfig::load('dryRun');
				if($dry){
					Migration::msg('Create folder : '.$folder);
					return true;
				}else{
					$res = ftp_mkdir($conn_id, $folder);
					if(!$res){
						Migration::msg('Failed to create folder : '.$folder);
					}
					return $res;
				}
			}else{
				return false;
			}
		}
		return true;
	}
	function sendFileLocal($localFile,$uri,$instanceOpt){
		$remotefile = Migration::solveURI($uri,$instanceOpt['path']);
		if(!empty($remotefile)){
			Migration::createFoldersFor($remotefile);
			App::import('Lib', 'Migration.MigrationConfig');
			$dry = MigrationConfig::load('dryRun');
			if($dry){
				Migration::msg('Attempt copy file '.$localFile.' to '.$remotefile);
				return true;
			}else{
				return copy($localFile,$remotefile);
			}
		}
		return false;
	}
	function createFoldersFor($file){
		$folder = dirname($file);
		if(!file_exists($folder)){
			App::import('Lib', 'Migration.MigrationConfig');
			Migration::createFoldersFor($folder);
			$dry = MigrationConfig::load('dryRun');
			if($dry){
				Migration::msg('Create folder : '.$folder);
				return true;
			}else{
				return mkdir($folder);
			}
		}
	}
}
?>