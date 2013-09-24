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
		
		$this->MigrationRemote = ClassRegistry::init('Migration.MigrationRemote');
		$this->MigrationNode = ClassRegistry::init('Migration.MigrationNode');
	}
	
	function setPluginName($Model,$plugin){
		$this->settings[$Model->alias]['plugin'] = $plugin;
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
		
		$settings = $this->settings[$Model->alias];
		$fullName = $settings['plugin'].'.'.$Model->name;
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
		$fullName = $settings['plugin'].'.'.$Model->name;
		$findOpt = array(
			'fields'=>array('COUNT(DISTINCT '.$Model->alias.'.'.$Model->primaryKey.') as count'),
			'conditions'=>array('or'=>array(
				$MR->alias.'.updated < '.$Model->alias.'.modified',
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
		$fullName = $settings['plugin'].'.'.$Model->name;
		$findOpt = array(
			'fields' => array($Model->alias.'.*',$MR->alias.'.*'),
			'conditions'=>array('or'=>array(
				$MR->alias.'.updated < '.$Model->alias.'.modified',
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
		}elseif(!empty($settings['remapFields']) && count(array_filter(array_intersect_key($entry[$Model->alias],array_flip((array)$settings['remapFields']))))){
			foreach((array)$settings['remapFields'] as $field){
				$remoteCond[$remoteModel->alias.'.'.$field] = $entry[$Model->alias][$field];
			}
		}
		if(!empty($remoteCond)){
			$remoteEntry = $remoteModel->find('first',array('conditions'=>$remoteCond,'recursive'=>-1));
		}
		if(!empty($remoteEntry)){
			if($this->remoteEntryEquals($Model, $entry, $remoteEntry, $targetInstance)){
				//both entries are equals, skip.
				return $this->updateRemoteTracking($Model, $targetInstance, $entry, $remoteEntry[$remoteModel->alias][$remoteModel->primaryKey]);
			}elseif(!empty($entry[$MR->alias]['sign']) && $entry[$MR->alias]['sign'] == $this->getMigrationSign($Model, $remoteEntry, $targetInstance)){
				//Remote entry was not modified since the last sync
				return $this->updateRemoteEntry($Model, $entry, $remoteEntry, $targetInstance);
			}elseif($this->getMigrationSign($Model, $entry) == $this->getMigrationSign($Model, $remoteEntry, $targetInstance)){
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
	}
	
	function resolveRemoteConflict($Model, $entry, $remoteEntry, $targetInstance){
		$remoteModel = Migration::getRemoteModel($Model,$targetInstance);
		
		return false;
	}
	
	function remoteEntryEquals($Model, $entry, $remoteEntry, $targetInstance){
		$settings = $this->settings[$Model->alias];
		$remoteModel = Migration::getRemoteModel($Model,$targetInstance);
		$exclude = array_merge(array($Model->primaryKey,'created','modified'),$settings['excludeFields']);
		$raw = $this->getRawEntry($Model, $entry);
		$a = array_diff_key($raw[$Model->alias],array_flip($exclude));
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
			debug($overrides);
			debug($remoteEntry[$remoteModel->alias]);
			foreach ((array)$settings['overridable'] as $field) {
				if(!array_key_exists($field,$overrides) 
						|| !array_key_exists($field,$remoteEntry[$remoteModel->alias]) 
						|| $overrides[$field] != $remoteEntry[$remoteModel->alias][$field]
				){
					$exclude[] = $field;
				}
			}
			debug($exclude);
		}
		
		$data = $this->prepDataForMigration($Model, $entry, $targetInstance, $exclude);
		if(empty($data)) return false;

		
		$data = array_merge($remoteEntry[$remoteModel->alias],$data);
		if($remoteModel->save($data)){
			return $this->updateRemoteTracking($Model, $targetInstance, $entry, $remoteEntry[$remoteModel->alias][$remoteModel->primaryKey]);
		}
		return false;
	}
	function createRemoteEntry($Model, $entry, $targetInstance){
		$settings = $this->settings[$Model->alias];
		$remoteModel = Migration::getRemoteModel($Model,$targetInstance);
		
		$data = $this->prepDataForMigration($Model, $entry, $targetInstance);
		if(empty($data)) return false;
		
		$remoteModel->create();
		$remoteModel->save($data);
		if($remoteModel->save($data)){
			return $this->updateRemoteTracking($Model, $targetInstance, $entry, $remoteModel->id);
		}
		return false;
	}
	
	function prepDataForMigration($Model, $entry, $targetInstance, $exclude = array()){
		$settings = $this->settings[$Model->alias];
		$exclude = array_merge($exclude,array($Model->primaryKey),$settings['excludeFields']);
		
		
		$assoc = $this->getMigratedAssociations($Model);
		debug($assoc);
		
		
		$raw = $this->getRawEntry($Model, $entry);
		$data = $raw[$Model->alias];
		$data = array_diff_key($data,array_flip($exclude));
		
		
		return false;
		
		return $data;
	}
	
	function getMigratedAssociations($Model){
		$settings = $this->settings[$Model->alias];
		$assoc = Set::normalize($settings['assoc']);
		$belongsTo = array_diff($Model->getAssociated('belongsTo'),array_keys($assoc));
		if(!empty($belongsTo)){
			$exclude = array_merge(array($Model->primaryKey),$settings['excludeFields']);
			foreach($Model->getAssociated('belongsTo') as $alias){
				$opt = $Model->belongsTo[$alias];
				if( 
					(in_array($opt['className'],Migration::migratingModels()) 
						|| in_array($opt['className'],Migration::migratingModels(false)) )
					&& !in_array($opt['foreignKey'],$exclude)
				){
					$assoc[$alias] = $opt;
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
	
	function updateRemoteTracking($Model, $instance, $entry, $remoteId = null){
		$settings = $this->settings[$Model->alias];
		$localId = $entry[$Model->alias][$Model->primaryKey];
		$MR = $this->MigrationRemote;
		$MR->id = null;
		$fullName = $settings['plugin'].'.'.$Model->name;
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
		
		if(!empty($settings['overridable'])){
			$raw = $this->getRawEntry($Model, $entry);
			$overridable = array_intersect_key($raw[$Model->alias],array_flip((array)$settings['overridable']));
			$data[$MR->alias]['overridable'] = serialize($overridable);
		}else{
			$data[$MR->alias]['overridable'] = null;
		}
		
		return !!($MR->save($data));
	}
	
	function updateMigrationTracking($Model, $data){
		$settings = $this->settings[$Model->alias];
		$MN = $this->MigrationNode;
		$fullName = $settings['plugin'].'.'.$Model->name;
		$gdata = array();
		foreach($data as $id=>$tracked){
			$gdata[(int)(bool)$tracked][] = $id;
		}
		$def = !$settings['manual'];
		if(!empty($gdata[(int)$def])){
			$MN->updateAll(array('tracked'=>null), array('model'=>$fullName,'local_id'=>$gdata[(int)$def]));
		}
		if(!empty($gdata[(int)!$def])){
			$MN->updateAll(array('tracked'=>(int)!$def), array('model'=>$fullName,'local_id'=>$gdata[(int)!$def]));
			$existent = $MN->find('list',array('fields'=>array('id','local_id'),'conditions'=>array('model'=>$fullName,'local_id'=>$gdata[(int)!$def])));
			foreach(array_diff($gdata[(int)!$def],$existent) as $id){
				$MN->create();
				$node = array(
					'model'=>$fullName,
					'local_id'=>$id,
					'tracked'=>(int)!$def,
				);
				$MN->save($node);
			}
		}
	}
	
	function getMigrationSign($Model, $entry, $instance = false){
		$alias = $Model->alias;
		if(!$instance){
			$raw = $this->getRawEntry($Model, $entry);
		}else{
			$remoteModel = Migration::getRemoteModel($Model,$instance);
			$alias = $remoteModel->alias;
		}
		$settings = $this->settings[$Model->alias];
		$exclude = array_merge(array($Model->primaryKey,'created','modified'),$settings['excludeFields'],(array)$settings['overridable']);
		$last_data = array_diff_key($entry[$alias],array_flip($exclude));
		return sha1(serialize($last_data));
	}
	
}
?>