<?php
class MigrationBehavior extends ModelBehavior {
	
	var $defaultSettings = array(
		'mode'=>'all',
		'plugin'=>null, // The model should be setted Here
		'excludeFields'=>array(), // Fields that will not be used to compair with the remote version of the entry
		'overridable'=>array(), // Overridable Fields will be synchorized until and only until it is modified on a remote instance
		'manual'=>false, // Entries will not be tracked unless configured otherwise in the admin
		'listFields'=>array(), // Fields shown in the entries list
		'assoc'=>array(), // Additional assiciations that cant be autometicaly detected
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
	
	function msg($msg){
		Migration::msg($msg);
	}
	
	function setPluginName($Model,$plugin){
		$this->settings[$Model->alias]['plugin'] = $plugin;
	}
	
	function getFullName($Model){
		$settings = $this->settings[$Model->alias];
		return (!empty($settings['plugin'])?$settings['plugin'].'.':'').$Model->name;
	}
	function getUrlName($Model){
		return Migration::modelNameToUrl($Model->getFullName());
	}
	
	function migrationSettings($Model, $key=null){
		$settings = $this->settings[$Model->alias];
		if($key){
			return $settings[$key];
		}
		return $settings;
	}
	
	function joinMigrationNode($Model,$reset=true){
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
		,$reset);
	}
	function joinMigrationRemote($Model,$reset=true){
		$fullName = $this->getFullName($Model);
		$MR = $this->MigrationRemote;
		$Model->bindModel(
			array('hasOne' => array(
				$MR->alias => array(
					'className' => $MR->name,
					'foreignKey' => 'local_id',
					'conditions' => array($MR->alias.'.model' => $fullName)
				)
			))
		,$reset);
	}
		
	function migrationPaginateOpts($Model){
		$opts = array();
		$Model->joinMigrationNode(false);
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
	
	
	function migrationFindCond($Model,$q){
		$cond = array('OR'=>array());
		$fields = $Model->migrationListFields();
		foreach ($fields as $fieldName=>$field) {
			$cond['OR'][$Model->alias.'.'.$fieldName.' LIKE'] = '%'.$q.'%';
		}
		return $cond;
	}
	function getLastSync($Model,$targetInstance=null){
		$settings = $this->settings[$Model->alias];
		$lastSync = strtotime(MigrationConfig::load('last_sync'));
		if(!empty($settings['last_sync'])){
			$lastSync = max($lastSync,strtotime($settings['last_sync']));
		}
		return $lastSync;
	}
	function pendingFindOpt($Model,$findOpt=array(),$targetInstance=null){
		$settings = $this->settings[$Model->alias];
		$MR = $this->MigrationRemote;
		$MN = $this->MigrationNode;
		$fullName = $this->getFullName($Model);
		$findOpt = Set::merge(array(
			'conditions'=>array('or'=>array(
				$MR->alias.'.updated < '.$Model->alias.'.modified',
				$MR->alias.'.invalidated' => 1,
				$MR->alias.'.id IS NULL'
			)),
			'joins' => array(
				'MR' => array(
					'alias' => $MR->alias,
					'table'=> $MR->useTable,
					'type' => 'LEFT',
					'conditions' => array(
						$MR->alias.'.model' => $fullName,
						
						$MR->alias.'.local_id = '.$Model->alias.'.'.$Model->primaryKey,
					)
				),
				'MN' => array(
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
		),$findOpt);
		if(!empty($targetInstance)){
			$findOpt['joins']['MR']['conditions'][$MR->alias.'.instance'] = $targetInstance;
		}
		if(!empty($Model->hasOne['MigrationNode']) && $findOpt['recursive'] > -1){
			unset($findOpt['joins']['MN']);
		}
		if($settings['manual']){
			$findOpt['conditions'][$MN->alias.'.tracked'] = 1;
		}else{
			$findOpt['conditions'][] = array('or'=>array(
				$MN->alias.'.tracked' => 0,
				$MN->alias.'.tracked IS NULL'
			));
		}
		
		$lastSync = $this->getLastSync($Model,$targetInstance);
		if(!empty($lastSync)){
			$format = $Model->getDataSource()->columns['datetime']['format'];
			$findOpt['conditions'][] =	array('or'=>array(
				$Model->alias.'.modified >' => date($format,$lastSync),
				$Model->alias.'.modified IS NULL'
			));
		}
		
		$findOpt['joins'] = array_values($findOpt['joins']);
		return $findOpt;
	}
	
	function migrationPendingPaginateOpts($Model){
		$Model->joinMigrationNode(false);
		// return array();
		return $this->pendingFindOpt($Model,array('recursive'=>0));
	}
	
	function migrationPendingCount($Model){
		
		$findOpt = $this->pendingFindOpt($Model,array(
			'fields'=>array('COUNT(DISTINCT '.$Model->alias.'.'.$Model->primaryKey.') as count')
		));
		
		$count = $Model->find('first',$findOpt);
		return $count[0]['count'];
	}
	
  function migrationDeletedCount($Model,$targetInstance=null){
    $deletedIds = array();
    foreach($this->migrationDeletedIds($Model,$targetInstance) as $instance => $ids){
      $deletedIds = array_merge($deletedIds,$ids);
    }
    return count($deletedIds);
  }
  function migrationDeletedIds($Model,$targetInstance=null){
    $MR = $this->MigrationRemote;
    $MN = $this->MigrationNode;
    
    $fullName = $this->getFullName($Model);
    $deletedIds = $this->MigrationRemote->find('list',array(
      'fields'=>array($MR->alias.'.remote_id', $MR->alias.'.remote_id', $MR->alias.'.instance'),
      'conditions'=> array(
        $MR->alias.'.model' => $fullName,
        $Model->alias.'.'.$Model->primaryKey => null
      ),
      'joins' => array(
        array(
          'alias' => $Model->alias,
          'table'=> $Model->useTable,
          'type' => 'LEFT',
          'conditions' => array(
            $MR->alias.'.local_id = '.$Model->alias.'.'.$Model->primaryKey,
          )
        ),
      )
    ));
    
    
    if($targetInstance){
      $targets = array($targetInstance=>$targetInstance);
    }else{
      $targets = Migration::targetList();
    }
    $format = $Model->getDataSource()->columns['datetime']['format'];
    foreach($targets as $instance => $name){
      $lastSync = $this->getLastSync($Model,$instance);
      $existingLocalCond = $this->FindPresyncConds($Model,array('conditions'=>array('created <= '=>date($format,$lastSync))));
      $remoteModel = Migration::getRemoteModel($Model,$instance);
      $ids = $remoteModel->find('list',array('fields'=>array($Model->primaryKey,$Model->primaryKey),'conditions'=>array('created <= '=>date($format,$lastSync),'not'=>array($existingLocalCond))));
      if(!empty($ids)){
        $deletedIds[$instance] = array_merge( empty($deletedIds[$instance])?array():$deletedIds[$instance], $ids);
      }
      // debug($remoteOnly);
    }
    if(!empty($targetInstance) && !is_array($targetInstance)){
      $deletedIds = empty($deletedIds[$targetInstance])?array():$deletedIds[$targetInstance];
    }
    
    return $deletedIds;
  }
  
  function FindPresyncConds($Model, $findOpt = array()){
    $settings = $this->settings[$Model->alias];
    if(empty($findOpt['recursive'])){
      $findOpt['recursive'] = -1;
    }
    if(!empty($settings['mapFields'])){
      foreach((array)$settings['mapFields'] as $field){
        // $findOpt['fields'][] = $Model->alias.'.'.$field;
        $findOpt['fields'][] = $field;
      }
    }else{
      $findOpt['fields'] = array($Model->primaryKey);
    }
    if(count($findOpt['fields']) < 2){
      $findOpt['fields'] = array($findOpt['fields'][0],$findOpt['fields'][0]);
      $existingLocal = $Model->find('list',$findOpt);
      return array($findOpt['fields'][0] => array_keys($existingLocal));
    }else{
      $cond = array('or'=>array());
      $existingLocal = $Model->find('all',$findOpt);
      foreach($existingLocal as $row){
        $cond['or'][] = $row[$Model->alias];
      }
      return $cond;
    }
	}
	
	function getRemoteEntry($Model, $entry, $targetInstance){
		$remoteEntry = null;
		$settings = $this->settings[$Model->alias];
		$MR = $this->MigrationRemote;
		$remoteModel = Migration::getRemoteModel($Model,$targetInstance);
		if(is_numeric($entry)){
			$Model->joinMigrationRemote();
			$entry = $Model->read(null,$entry);
		}
		$remoteCond = array();
		$lastSync = $this->getLastSync($Model,$targetInstance);
		
		if(!empty($entry[$MR->alias]['remote_id'])){
			$remoteCond[$remoteModel->alias.'.'.$remoteModel->primaryKey] = $entry[$MR->alias]['remote_id'];
		}elseif($MapFieldsConds = $this->_remoteEntryMapFieldsConds($Model,$entry,$remoteModel)){
			$remoteCond = $MapFieldsConds;
		}elseif(!empty($lastSync) && strtotime($entry[$Model->alias]['created']) <= $lastSync){
			$remoteCond[$remoteModel->alias.'.'.$remoteModel->primaryKey] = $entry[$Model->alias][$Model->primaryKey];
    }
		
		if(!empty($remoteCond)){
			$remoteEntry = $remoteModel->find('first',array('conditions'=>$remoteCond,'recursive'=>-1));
		}
		return $remoteEntry;
	}
  
  function _remoteEntryMapFieldsConds($Model,$entry,$remoteModel){
    $settings = $this->settings[$Model->alias];
		if(is_numeric($entry)){
			$entry = $Model->read($settings['mapFields'],$entry);
		}
    if(!empty($settings['mapFields']) && count(array_filter(array_intersect_key($entry[$Model->alias],array_flip((array)$settings['mapFields']))))){
      $remoteCond = array();
      foreach((array)$settings['mapFields'] as $field){
        $remoteCond[$remoteModel->alias.'.'.$field] = $entry[$Model->alias][$field];
      }
      return $remoteCond;
    }
  }
	
	function resolveRemoteConflict($Model, $entry, $remoteEntry, $targetInstance){
		$remoteModel = Migration::getRemoteModel($Model,$targetInstance);
		$this->msg('Conflict : This item was edited on the target environement and can\'t be migrated automaticaly (<a href="'.Router::url(array('plugin'=>'migration','controller'=>'migration_nodes','action'=>'diff',$Model->getUrlName(),$entry[$Model->alias][$Model->primaryKey],'admin'=>true)).'">Diff</a>)');
		return false;
	}
	
	function getRemoteId($Model,$localEntry,$targetInstance){
		$MR = $this->MigrationRemote;
		$fullName = $this->getFullName($Model);
    $settings = $this->settings[$Model->alias];
		if(is_numeric($localEntry)){
			$local_id = $localEntry;
		}else{
			$local_id = $localEntry[$Model->alias][$Model->primaryKey];
		}
		
		$remoteNode = $MR->find('first',array('conditions'=>array(
			$MR->alias.'.model' => $fullName,
			$MR->alias.'.instance' => $targetInstance,
			$MR->alias.'.local_id' => $local_id,
		)));
		
		if($remoteNode){
			return $remoteNode[$MR->alias]['remote_id'];
		}
    
    if(!empty($settings['mapFields'])){
      $remoteModel = Migration::getRemoteModel($Model,$targetInstance);
      $MapFieldsConds = $this->_remoteEntryMapFieldsConds($Model,$localEntry,$remoteModel);
      if($MapFieldsConds){
        $remote = $remoteModel->find('first',array('fields'=>array($remoteModel->primaryKey),'conditions'=>$MapFieldsConds,'recursive'=>-1));
        return $remote[$remoteModel->alias][$remoteModel->primaryKey];
      }
    }
		
		$lastSync = $this->getLastSync($Model,$targetInstance);
		if(!empty($lastSync)){
			if(is_numeric($localEntry)){
				$localEntry = $Model->find('first',array('conditions'=>array($Model->alias.'.'.$Model->primaryKey=>$localEntry)));
			}
			if(strtotime($localEntry[$Model->alias]['modified']) <= $lastSync){
				return $local_id;
			}
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
					$this->msg('Save attempt on '.$MMissing->alias);
				}else{
					$MMissing->save($m);
				}
			}else{
				unset($existingsByCode[$code]);
			}
		}
		if(!empty($existingsByCode)){
			if($dry){
				$this->msg('Delete attempt on '.$MMissing->alias);
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
				$this->msg('Update attempt on '.$MN->alias);
			}else{
				$MN->updateAll(array('tracked'=>null), array('model'=>$fullName,'local_id'=>$gdata[(int)$def]));
			}
		}
		if(!empty($gdata[(int)!$def])){
			if($dry){
				$this->msg('Update attempt on '.$MN->alias);
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
					$this->msg('Save attempt on '.$MN->alias);
				}else{
					$MN->save($node);
				}
			}
		}
	}
	
	
}
?>