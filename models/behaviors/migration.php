<?php
class MigrationBehavior extends ModelBehavior {
	
	var $defaultSettings = array(
		'mode'=>'all',
		'plugin'=>null,
		'excludeFields'=>array(),
		'overridable'=>array(),
		'manual'=>false,
		'listFields'=>array(),
		'assoc'=>array(),
	);
	
	function setup(&$Model, $settings) {
		if (!isset($this->settings[$Model->alias])) {
			$this->settings[$Model->alias] = $this->defaultSettings;
		}
		$this->settings[$Model->alias] = array_merge($this->settings[$Model->alias], (array)$settings);
		
		App::import('Lib', 'Migration.Migration');
		$this->MigrationRemote = ClassRegistry::init('Migration.MigrationRemote');
		$this->MigrationNode = ClassRegistry::init('Migration.MigrationNode');
		$this->MigrationMissing = ClassRegistry::init('Migration.MigrationMissing');
	}
	
	function setPluginName($Model,$plugin){
		$this->settings[$Model->alias]['plugin'] = $plugin;
	}
	
	function getFullName($Model){
		$settings = $this->settings[$Model->alias];
		return (!empty($settings['plugin'])?$settings['plugin'].'.':'').$Model->name;
	}
	
	function migrationSettings($Model, $key=null){
		$settings = $this->settings[$Model->alias];
		if($key){
			return $settings[$key];
		}
		return $settings;
	}
	
	function migrationPaginateOpts($Model){
		$opts = array();
		$fullName = $this->getFullName($Model);
		$MN = $this->MigrationNode;
		$Model->bindModel(
			array('hasOne' => array(
				$MN->alias => array(
					'className' => $MN->name,
					'foreignKey' => 'local_id',
					'conditions' => array($MN->alias.'.model' => $fullName)
				)
			))
		,false);
		
		return $opts;
	}
	
	function migrationListFields($Model){
		$settings = $this->settings[$Model->alias];
		$fields = $settings['listFields'];
		if(empty($fields)){
			$fields = array($Model->primaryKey,$Model->displayField);
		}
		$fields = Set::normalize($fields);
		foreach ($fields as $fieldName => &$opt) {
			if(empty($opt['label'])) $opt['label'] = __(Inflector::humanize($fieldName),true);
		}
		return $fields;
	}
	
	function migrationPendingCount($Model){
		$settings = $this->settings[$Model->alias];
		$MR = $this->MigrationRemote;
		$MN = $this->MigrationNode;
		$fullName = $this->getFullName($Model);
		$findOpt = array(
			'fields'=>array('COUNT(DISTINCT '.$Model->alias.'.'.$Model->primaryKey.') as count'),
			'conditions'=>array('or'=>array(
				$MR->alias.'.updated < '.$Model->alias.'.modified',
				$MR->alias.'.invalidated' => 1,
				$MR->alias.'.id IS NULL'
			)),
			'joins' => array(
				array(
					'alias' => $MR->alias,
					'table'=> $MR->useTable,
					'type' => 'LEFT',
					'conditions' => array(
						$MR->alias.'.model' => $fullName,
						$MR->alias.'.local_id = '.$Model->alias.'.'.$Model->primaryKey,
					)
				),
				array(
					'alias' => $MN->alias,
					'table'=> $MN->useTable,
					'type' => 'LEFT',
					'conditions' => array(
						$MN->alias.'.model' => $fullName,
						$MN->alias.'.local_id = '.$Model->alias.'.'.$Model->primaryKey,
					)
				)
			),
			'recursive' => -1,
		);
		if($settings['manual']){
			$findOpt['conditions'][$MN->alias.'.tracked'] = 1;
		}else{
			$findOpt['conditions'][] = array('or'=>array(
				$MN->alias.'.tracked' => 0,
				$MN->alias.'.tracked IS NULL'
			));
		}
		$count = $Model->find('first',$findOpt);
		return $count;
	}
	
	function migrateBatch($Model,$targetInstance,$limit = 20){
		$settings = $this->settings[$Model->alias];
		$MR = $this->MigrationRemote;
		$MN = $this->MigrationNode;
		$fullName = $this->getFullName($Model);
		$findOpt = array(
			'fields' => array($Model->alias.'.*',$MR->alias.'.*'),
			'conditions'=>array('or'=>array(
				$MR->alias.'.updated < '.$Model->alias.'.modified',
				$MR->alias.'.invalidated' => 1,
				$MR->alias.'.id IS NULL'
			)),
			'joins' => array(
				array(
					'alias' => $MR->alias,
					'table'=> $MR->useTable,
					'type' => 'LEFT',
					'conditions' => array(
						$MR->alias.'.model' => $fullName,
						$MR->alias.'.instance' => $targetInstance,
						$MR->alias.'.local_id = '.$Model->alias.'.'.$Model->primaryKey,
					)
				),
				array(
					'alias' => $MN->alias,
					'table'=> $MN->useTable,
					'type' => 'LEFT',
					'conditions' => array(
						$MN->alias.'.model' => $fullName,
						$MN->alias.'.local_id = '.$Model->alias.'.'.$Model->primaryKey,
					)
				)
			),
			'limit' => $limit,
			'recursive' => -1,
		);
		if($settings['manual']){
			$findOpt['conditions'][$MN->alias.'.tracked'] = 1;
		}else{
			$findOpt['conditions'][] = array('or'=>array(
				$MN->alias.'.tracked' => 0,
				$MN->alias.'.tracked IS NULL'
			));
		}
		$entries = $Model->find('all',$findOpt);
		//debug($entries);
		foreach($entries as $entry){
			$res = $this->syncRemoteEntry($Model, $entry, $targetInstance);
			debug('ID : '.$entry[$Model->alias][$Model->primaryKey].' - '.($res?'ok':'error'));
		}
	}
	
	
	function syncRemoteEntry($Model, $entry, $targetInstance){
		$settings = $this->settings[$Model->alias];
		$MR = $this->MigrationRemote;
		$remoteModel = Migration::getRemoteModel($Model,$targetInstance);
		if(is_numeric($entry)){
			debug('TODO');
		}
		$remoteCond = array();
		if(!empty($entry[$MR->alias]['remote_id'])){
			$remoteCond[$remoteModel->alias.'.'.$remoteModel->primaryKey] = $entry[$MR->alias]['remote_id'];
		}elseif(!empty($settings['mapFields']) && count(array_filter(array_intersect_key($entry[$Model->alias],array_flip((array)$settings['mapFields']))))){
			foreach((array)$settings['mapFields'] as $field){
				$remoteCond[$remoteModel->alias.'.'.$field] = $entry[$Model->alias][$field];
			}
		}
		if(!empty($remoteCond)){
			$remoteEntry = $remoteModel->find('first',array('conditions'=>$remoteCond,'recursive'=>-1));
		}
		
		$entry = $this->prepDataForMigration($Model, $entry, $targetInstance);
		if(empty($entry)) return false;
		
		if(!empty($remoteEntry)){
			$remoteSign = $this->getMigrationSign($Model, $remoteEntry, $targetInstance);
			if($this->remoteEntryEquals($Model, $entry, $remoteEntry, $targetInstance)){
				//both entries are equals, skip.
				if($this->updateRemoteFiles($Model, $entry, $targetInstance)){
					return $this->updateRemoteTracking($Model, $targetInstance, $entry, $remoteEntry[$remoteModel->alias][$remoteModel->primaryKey]);
				}
			}elseif(!empty($entry[$MR->alias]['sign']) && $entry[$MR->alias]['sign'] == $remoteSign){
				//Remote entry was not modified since the last sync
				return $this->updateRemoteEntry($Model, $entry, $remoteEntry, $targetInstance);
			}elseif($this->getMigrationSign($Model, $entry) == $remoteSign){
				//Some opreation has been made that prevent normal traking (modifying settings or adding the same entry in both instance's bd),
				//but the entries seems compatible
				return $this->updateRemoteEntry($Model, $entry, $remoteEntry, $targetInstance);
			}else{
				//Both local and remote entries are modified
				return $this->resolveRemoteConflict($Model, $entry, $remoteEntry, $targetInstance);
			}
		}else{
			//Remote entry does not exists
			return $this->createRemoteEntry($Model, $entry, $targetInstance);
		}
		
		return false;
	}
	
	function resolveRemoteConflict($Model, $entry, $remoteEntry, $targetInstance){
		$remoteModel = Migration::getRemoteModel($Model,$targetInstance);
		debug('Cant resolve conflict');
		return false;
	}
	
	function remoteEntryEquals($Model, $entry, $remoteEntry, $targetInstance){
		$settings = $this->settings[$Model->alias];
		$remoteModel = Migration::getRemoteModel($Model,$targetInstance);
		$exclude = array_merge(array($Model->primaryKey,'created','modified'),$settings['excludeFields']);
		if(empty($entry['MigrationData'])) $entry = $this->prepDataForMigration($Model, $entry, $targetInstance);
		$a = array_diff_key($entry['MigrationData'],array_flip($exclude));
		$b = array_diff_key($remoteEntry[$remoteModel->alias],array_flip($exclude));
		$a = array_intersect_key($a,$b);
		$b = array_intersect_key($b,$a);
		return !empty($a) && $a == $b;
	}
	
	function updateRemoteEntry($Model, $entry, $remoteEntry, $targetInstance){
		$settings = $this->settings[$Model->alias];
		$remoteModel = Migration::getRemoteModel($Model,$targetInstance);
		
		$exclude = array();
		if(!empty($settings['overridable'])){
			$MR = $this->MigrationRemote;
			$overrides = array();
			if(!empty($entry[$MR->alias]['overridable'])) {
				$overrides = unserialize($entry[$MR->alias]['overridable']);
			}
			foreach ((array)$settings['overridable'] as $field) {
				if(!array_key_exists($field,$overrides) 
						|| !array_key_exists($field,$remoteEntry[$remoteModel->alias]) 
						|| $overrides[$field] != $remoteEntry[$remoteModel->alias][$field]
				){
					$exclude[] = $field;
				}
			}
		}
		
		$data = array_diff($entry['MigrationData'],array_flip($exclude));
		if(empty($data)) return false;

		$data = array_merge($remoteEntry[$remoteModel->alias],$data);
		
		$dry = MigrationConfig::load('dryRun');
		if($dry){
			$saved = true;
			debug('Save attempt on '.$remoteModel->alias);
		}else{
			$saved = $remoteModel->save($data);
		}
		if($saved){
			if($this->updateRemoteFiles($Model, $entry, $targetInstance)){
				return $this->updateRemoteTracking($Model, $targetInstance, $entry, $remoteEntry[$remoteModel->alias][$remoteModel->primaryKey]);
			}
		}
		return false;
	}
	function createRemoteEntry($Model, $entry, $targetInstance){
		$settings = $this->settings[$Model->alias];
		$remoteModel = Migration::getRemoteModel($Model,$targetInstance);
		
		$data = $entry['MigrationData'];
		if(empty($data)) return false;
		
		$remoteModel->create();
		$dry = MigrationConfig::load('dryRun');
		if($dry){
			$saved = true;
			debug('Save attempt on '.$remoteModel->alias);
		}else{
			$saved = $remoteModel->save($data);
		}
		if($saved){
			if($this->updateRemoteFiles($Model, $entry, $targetInstance)){
				return $this->updateRemoteTracking($Model, $targetInstance, $entry, $remoteModel->id);
			}
		}
		return false;
	}
	
	function prepDataForMigration($Model, $entry, $targetInstance, $exclude = array()){
		$settings = $this->settings[$Model->alias];
		$exclude = array_merge($exclude,array($Model->primaryKey),$settings['excludeFields']);
		$fullName = $this->getFullName($Model);
		
		
		$assoc = $this->getMigratedAssociations($Model);
		if(!empty($assoc)){
			foreach($assoc as $name => $opt){
				$paths = array();
				if(!empty($opt['path'])){
					$paths = Migration::extractPath($entry[$Model->alias],$opt['path']);
				}elseif(!empty($opt['foreignKey']) && array_key_exists($opt['foreignKey'],$entry[$Model->alias])){
					$paths = array($opt['foreignKey']=>$entry[$Model->alias][$opt['foreignKey']]);
				}
				//debug($paths);
				if(!empty($paths)){
					$AssocModel = Migration::getLocalModel($opt['className']);
					foreach($paths as $path => $local_id){
						$removed = false;
						$remote_id = $this->getRemoteId($AssocModel,$local_id,$targetInstance);
						if(is_null($remote_id)){
							if(isset($opt['unsetLevel'])){
								$entry[$Model->alias] = Set::remove($entry[$Model->alias],implode('.',array_slice(explode('.',$path),0,$opt['unsetLevel'])));
								$removed = true;
							}
							if(!empty($opt['autoTrack'])){
								$entry['MigrationTracking'][$opt['className']][$local_id] = 1;
							}
							$entry['MigrationMissing'][] = array(
								'model' => $opt['className'],
								'local_id' => $local_id,
							);
						}
						if(!$removed){
							$entry[$Model->alias] = Set::insert($entry[$Model->alias],$path,$remote_id);
						}
					}
				}
			}
			//debug($assoc);
		}
		//debug($entry[$Model->alias]);
		
		$raw = $this->getRawEntry($Model, $entry);
		$data = $raw[$Model->alias];
		$data = array_diff_key($data,array_flip($exclude));
		
		$entry['MigrationData'] = $data;
		
		return $entry;
	}
	
	function getRemoteId($Model,$local_id,$targetInstance){
		$MR = $this->MigrationRemote;
		$fullName = $this->getFullName($Model);
		
		$remoteNode = $MR->find('first',array('conditions'=>array(
			$MR->alias.'.model' => $fullName,
			$MR->alias.'.instance' => $targetInstance,
			$MR->alias.'.local_id' => $local_id,
		)));
		
		if($remoteNode){
			return $remoteNode[$MR->alias]['remote_id'];
		}
		return null;
	}
	
	function getMigratedAssociations($Model){
		$settings = $this->settings[$Model->alias];
		$assoc = Set::normalize($settings['assoc']);
		$belongsTo = array_diff($Model->getAssociated('belongsTo'),array_keys($assoc));
		if(!empty($belongsTo)){
			$exclude = array_merge(array($Model->primaryKey),$settings['excludeFields']);
			foreach($Model->getAssociated('belongsTo') as $alias){
				$opt = $Model->belongsTo[$alias];
				$migratingModels = Migration::migratingModels();
				if( !in_array($opt['foreignKey'],$exclude) ){
					if(in_array($opt['className'],$migratingModels)){
						$assoc[$alias] = $opt;
					}elseif(isset($migratingModels[$opt['className']])){
						$opt['className'] = $migratingModels[$opt['className']];
						$assoc[$alias] = $opt;
					}
				}
			}
		}
		App::import('Lib', 'Migration.MigrationConfig');
		$parsers = MigrationConfig::load('assocParsers');
		if(!empty($parsers)){
			foreach($parsers as $p){
				$res = call_user_func($p,$this,$Model,$assoc);
				if(!empty($res)){
					$assoc = array_merge($assoc,$res);
				}
			}
		}
		return $assoc;
	}
	
	function getRawEntry($Model, $entry){
		$Model->set($entry);
		
		$options = array();
		$Model->Behaviors->trigger($Model, 'beforeSave', array($options), array(
				'break' => true, 'breakOn' => false
			));
		$Model->beforeSave($options);
		
		return $Model->data;
	}
	
	function updateRemoteFiles($Model, $entry, $targetInstance){
		$ok = true;
		$settings = $this->settings[$Model->alias];
		if(!empty($settings['files'])){
			if(!Set::numeric(array_keys($settings['files']))){
				$settings['files'] = array($settings['files']);
			}
			//debug($settings['files']);
			/*$local_paths = array(
				'(webroot)' => WWW_ROOT,
				'(tmp)' => TMP,
			);*/
			foreach($settings['files'] as $opt){
				if(!empty($opt['fileField']) && !empty($entry[$Model->alias][$opt['fileField']])){
					
					$localPath = (!empty($opt['basePath'])?trim($opt['basePath'],'/').'/':'').(!empty($opt['pathField']) && !empty($entry[$Model->alias][$opt['pathField']])?trim($entry[$Model->alias][$opt['pathField']],'/').'/':'').$entry[$Model->alias][$opt['fileField']];
					$ok = $ok && Migration::sendFile($localPath,$targetInstance);
					
					//debug($localPath);
				}
			}
		}
		return $ok;
	}
	
	function updateRemoteTracking($Model, $instance, $entry, $remoteId = null){
		$settings = $this->settings[$Model->alias];
		$localId = $entry[$Model->alias][$Model->primaryKey];
		$MR = $this->MigrationRemote;
		$MR->id = null;
		$fullName = $this->getFullName($Model);
		$findOpt = array('conditions'=>array(
			$MR->alias.'.model' => $fullName,
			$MR->alias.'.instance' => $instance,
			$MR->alias.'.local_id' => $localId,
		));
		$data = $MR->find('first',$findOpt);
		$data[$MR->alias]['model'] = $fullName;
		$data[$MR->alias]['instance'] = $instance;
		$data[$MR->alias]['local_id'] = $localId;
		if(!is_null($remoteId)) $data[$MR->alias]['remote_id'] = $remoteId;
		$format = $MR->getDataSource()->columns['datetime']['format'];
		$data[$MR->alias]['updated'] = date($format);
		$data[$MR->alias]['sign'] = $this->getMigrationSign($Model,$entry);
		$data[$MR->alias]['invalidated'] = 0;
		
		if(!empty($settings['overridable'])){
			$raw = $this->getRawEntry($Model, $entry);
			$overridable = array_intersect_key($raw[$Model->alias],array_flip((array)$settings['overridable']));
			$data[$MR->alias]['overridable'] = serialize($overridable);
		}else{
			$data[$MR->alias]['overridable'] = null;
		}
		
		if(!empty($remoteId)){
			$this->checkMigrationMissing($Model,$localId,$instance);
		}
		
		$dry = MigrationConfig::load('dryRun');
		if($dry){
			$saved = true;
			debug('Save attempt on '.$MR->alias);
		}else{
			$saved = $MR->save($data);
		}
		if($saved){
			if(!empty($entry['MigrationTracking'])){
				Migration::updateAllTracking($entry['MigrationTracking']);
			}
			if(!empty($entry['MigrationMissing'])){
				return $this->setMigrationMissing($Model,$entry['MigrationMissing'],$MR->id);
			}
			return true;
		}else{
			return false;
		}
	}
	
	function checkMigrationMissing($Model,$localId,$instance){
		$fullName = $this->getFullName($Model);
		$MR = $this->MigrationRemote;
		$MMissing = $this->MigrationMissing;
		$toInvalidate = $MMissing->find('list',array(
			'fields'=>array(
				$MMissing->alias.'.id',
				$MMissing->alias.'.migration_remote_id'
			),
			'conditions'=>array(
				$MR->alias.'.instance' => $instance,
				$MMissing->alias.'.model' => $fullName,
				$MMissing->alias.'.local_id' => $localId,
			),
			'recursive'=>0
		));
		if(!empty($toInvalidate)){
			$dry = MigrationConfig::load('dryRun');
			if($dry){
				debug('Update attempt on '.$MR->alias);
			}else{
				$MR->updateAll(array('invalidated'=>1), array('id'=>array_values($toInvalidate)));
			}
		}
	}
	
	function setMigrationMissing($Model,$data,$migration_remote_id){
		if(!Set::numeric(array_keys($data))){
			$data = array($data);
		}
		$dry = MigrationConfig::load('dryRun');
		$MMissing = $this->MigrationMissing;
		$existings = $MMissing->find('all',array('conditions'=>array('migration_remote_id'=>$migration_remote_id),'recursive'=>-1));
		$existingsByCode = array();
		foreach($existings as $e){
			$existingsByCode[$e[$MMissing->alias]['model'].':'.$e[$MMissing->alias]['local_id']] = $e[$MMissing->alias]['id'];
		}
		foreach($data as $m){
			$code = $m['model'].':'.$m['local_id'];
			$m['migration_remote_id'] = $migration_remote_id;
			if(empty($existingsByCode[$code])){
				$MMissing->create();
				if($dry){
					debug('Save attempt on '.$MMissing->alias);
				}else{
					$MMissing->save($m);
				}
			}else{
				unset($existingsByCode[$code]);
			}
		}
		if(!empty($existingsByCode)){
			if($dry){
				debug('Delete attempt on '.$MMissing->alias);
			}else{
				$MMissing->deleteAll(array($MMissing->alias.'.id'=>array_values($existingsByCode)));
			}
		}
		return true;
	}
	
	function updateMigrationTracking($Model, $data){
		$settings = $this->settings[$Model->alias];
		$MN = $this->MigrationNode;
		$fullName = $this->getFullName($Model);
		$gdata = array();
		$dry = MigrationConfig::load('dryRun');
		foreach($data as $id=>$tracked){
			$gdata[(int)(bool)$tracked][] = $id;
		}
		//debug($gdata);
		$def = !$settings['manual'];
		if(!empty($gdata[(int)$def])){
			if($dry){
				debug('Update attempt on '.$MN->alias);
			}else{
				$MN->updateAll(array('tracked'=>null), array('model'=>$fullName,'local_id'=>$gdata[(int)$def]));
			}
		}
		if(!empty($gdata[(int)!$def])){
			if($dry){
				debug('Update attempt on '.$MN->alias);
			}else{
				$MN->updateAll(array('tracked'=>(int)!$def), array('model'=>$fullName,'local_id'=>$gdata[(int)!$def]));
			}
			$existent = $MN->find('list',array('fields'=>array('id','local_id'),'conditions'=>array('model'=>$fullName,'local_id'=>$gdata[(int)!$def])));
			foreach(array_diff($gdata[(int)!$def],$existent) as $id){
				$MN->create();
				$node = array(
					'model'=>$fullName,
					'local_id'=>$id,
					'tracked'=>(int)!$def,
				);
				if($dry){
					debug('Save attempt on '.$MN->alias);
				}else{
					$MN->save($node);
				}
			}
		}
	}
	
	function getMigrationSign($Model, $entry, $instance = false){
		$alias = $Model->alias;
		if(!$instance){
			$data = $entry['MigrationData'];
		}else{
			$remoteModel = Migration::getRemoteModel($Model,$instance);
			$alias = $remoteModel->alias;
			$data = $entry[$alias];
		}
		$settings = $this->settings[$Model->alias];
		$exclude = array_merge(array($Model->primaryKey,'created','modified'),$settings['excludeFields'],(array)$settings['overridable']);
		$last_data = array_diff_key($data,array_flip($exclude));
		return sha1(serialize($last_data));
	}
	
}
?>