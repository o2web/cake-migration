<?php
class MigrationBehavior extends ModelBehavior {
	
	var $defaultSettings = array(
		'mode'=>'all',
		'plugin'=>null,
		'excludeFields'=>array(),
		'overridable'=>false,
	);
	
	function setup(&$Model, $settings) {
		if (!isset($this->settings[$Model->alias])) {
			$this->settings[$Model->alias] = $this->defaultSettings;
		}
		$this->settings[$Model->alias] = array_merge($this->settings[$Model->alias], (array)$settings);
		
		$this->MigrationRemote = ClassRegistry::init('Migration.MigrationRemote');
	}
	
	function setPluginName($Model,$plugin){
		$this->settings[$Model->alias]['plugin'] = $plugin;
	}
	
	function migrateBatch($Model,$targetInstance,$limit = 20){
		$fullName = $this->settings[$Model->alias]['plugin'].'.'.$Model->name;
		$MR = $this->MigrationRemote;
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
				)
			),
			'limit' => $limit,
			'recursive' => -1,
		);
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
		$remoteEntry = $remoteModel->find('first',array('conditions'=>$remoteCond,'recursive'=>-1));
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
		$raw = $this->getRawEntry($Model, $entry);
		$data = $raw[$Model->alias];
		$exclude = array_merge(array($Model->primaryKey),$settings['excludeFields']);
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
		$data = array_diff_key($data,array_flip($exclude));
		$data = array_merge($remoteEntry[$remoteModel->alias],$data);
		if($remoteModel->save($data)){
			return $this->updateRemoteTracking($Model, $targetInstance, $entry, $remoteEntry[$remoteModel->alias][$remoteModel->primaryKey]);
		}
		return false;
	}
	function createRemoteEntry($Model, $entry, $targetInstance){
		$settings = $this->settings[$Model->alias];
		$remoteModel = Migration::getRemoteModel($Model,$targetInstance);
		$raw = $this->getRawEntry($Model, $entry);
		$data = $raw[$Model->alias];
		$exclude = array_merge(array($Model->primaryKey),$settings['excludeFields']);
		$data = array_diff_key($data,array_flip($exclude));
		$remoteModel->create();
		$remoteModel->save($data);
		if($remoteModel->save($data)){
			return $this->updateRemoteTracking($Model, $targetInstance, $entry, $remoteModel->id);
		}
		return false;
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