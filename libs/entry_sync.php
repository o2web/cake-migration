<?php
	class EntrySync extends Object {
		var $Batch;
		function __construct ($Batch,$entry){
			App::import('Lib', 'Migration.Migration');
			$this->Batch = $Batch;
			$this->entry = $entry;
			
			$this->LocalModel = $this->Batch->LocalModel;
			$this->MigrationRemote = $this->Batch->MigrationRemote;
			$this->MigrationNode = $this->Batch->MigrationNode;
			$this->MigrationMissing = $this->Batch->MigrationMissing;
			$this->targetInstance = $this->Batch->Process->targetInstance;
		}
		
		function msg($msg){
			$this->Batch->Process->msg($msg);
		}
		
		function sync(){
			// return $this->LocalModel->syncRemoteEntry($this->entry, $this->targetInstance);
			// function syncRemoteEntry($Model, $entry, $targetInstance){
			$settings = $this->LocalModel->migrationSettings();
			$MR = $this->MigrationRemote;
			$remoteModel = $this->getRemoteModel();
			
			$remoteEntry = $this->getRemoteEntry();
			$lastSync = $this->LocalModel->getLastSync($this->targetInstance);
			
			$entry = $this->getPrepedEntry();
			if(empty($entry)) return false;
			
			if(!empty($remoteEntry)){
				$remoteSign = $this->getRemoteSign();
				if($this->remoteIsIdentical()){
					//both entries are equals, skip.
					if($this->LocalModel->updateRemoteFiles($entry, $this->targetInstance)){
						return $this->updateRemoteTracking( $remoteEntry[$remoteModel->alias][$remoteModel->primaryKey]);
					}
				}elseif(!empty($entry[$MR->alias]['sign']) && $entry[$MR->alias]['sign'] == $remoteSign){
					//Remote entry was not modified since the last sync
					return $this->updateRemoteEntry();
				}elseif(!empty($lastSync) && !empty($remoteEntry[$remoteModel->alias]['modified']) && strtotime($remoteEntry[$remoteModel->alias]['modified']) < $lastSync){
					//The remote does not seems to have been modified since the last complete database copy
					return $this->updateRemoteEntry();
				}elseif($this->getLocalSign() == $remoteSign){
					//Some opreation has been made that prevent normal traking (modifying settings or adding the same entry in both instance's bd),
					//but the entries seems compatible
					return $this->updateRemoteEntry();
				}else{
					//Both local and remote entries are modified
					return $this->LocalModel->resolveRemoteConflict($entry, $remoteEntry, $this->targetInstance);
				}
			}else{
				//Remote entry does not exists
				return $this->createRemoteEntry();
			}
			
			return false;
		}
		
		
		function getLocalId(){
			return $this->entry[$this->LocalModel->alias][$this->LocalModel->primaryKey];
		}
		
		
		var $_remoteEntry;
		function getRemoteEntry(){
			if(isset($this->_remoteEntry)) return $this->_remoteEntry;
			return $this->_remoteEntry = $this->LocalModel->getRemoteEntry($this->entry, $this->targetInstance);
		}
		
		var $_prepedEntry;
		function getPrepedEntry(){
			if(isset($this->_prepedEntry)) return $this->_prepedEntry;
			return $this->_prepedEntry = $this->prepDataForMigration();
		}
		
		var $_remoteModel;
		function getRemoteModel(){
			if(isset($this->_remoteModel)) return $this->_remoteModel;
			return $this->_remoteModel = Migration::getRemoteModel($this->LocalModel,$this->targetInstance);
		}
			
		function prepDataForMigration($exclude = array()){
			// $this->LocalModel, $this->entry, $this->targetInstance
			// $Model, $entry, $targetInstance, 
			$settings = $this->LocalModel->migrationSettings();
			$exclude = array_merge($exclude,array($this->LocalModel->primaryKey),$settings['excludeFields']);
			$fullName = $this->LocalModel->getFullName();
			$alias = $this->LocalModel->alias;
			$entry = $this->entry;
			
			$assoc = $this->LocalModel->getMigratedAssociations();
			if(!empty($assoc)){
				foreach($assoc as $name => $opt){
					$paths = array();
					if(!empty($opt['path'])){
						$paths = Migration::extractPath($entry[$alias],$opt['path']);
					}elseif(!empty($opt['foreignKey']) && array_key_exists($opt['foreignKey'],$entry[$alias])){
						$paths = array($opt['foreignKey']=>$entry[$alias][$opt['foreignKey']]);
					}
					//debug($paths);
					if(!empty($paths)){
						$AssocModel = Migration::getLocalModel($opt['className']);
						foreach($paths as $path => $local_id){
							$removed = false;
							$remote_id = $AssocModel->getRemoteId($local_id,$this->targetInstance);
							if(is_null($remote_id)){
								if(isset($opt['unsetLevel'])){
									$entry[$alias] = Set::remove($entry[$alias],implode('.',array_slice(explode('.',$path),0,$opt['unsetLevel'])));
									$removed = true;
								}
								if(!empty($opt['autoTrack'])){
									$entry['MigrationTracking'][$opt['className']][$local_id] = 1;
								}
								if(!empty($opt['autoSelect'])){
									$this->Batch->Process->autoSelect[] = array(
										'model' => $opt['className'],
										'local_id' => $local_id,
									);
								}
								$entry['MigrationMissing'][] = array(
									'model' => $opt['className'],
									'local_id' => $local_id,
								);
							}
							if(!$removed){
								$entry[$alias] = Set::insert($entry[$alias],$path,$remote_id);
							}
						}
					}
				}
				//debug($assoc);
			}
			//debug($entry[$alias]);
			
			$raw = $this->LocalModel->getRawEntry($entry);
			$data = $raw[$alias];
			$data = array_diff_key($data,array_flip($exclude));
			
			$entry['MigrationData'] = $data;
			
			return $entry;
		}
		
		function remoteIsIdentical(){
			$remoteEntry = $this->getRemoteEntry();
			$settings = $this->LocalModel->migrationSettings();
			$remoteModel = $this->getRemoteModel();
			$exclude = array_merge(array($this->LocalModel->primaryKey,'created','modified'),$settings['excludeFields']);
			$entry = $this->getPrepedEntry();
			$a = array_diff_key($entry['MigrationData'],array_flip($exclude));
			$b = array_diff_key($remoteEntry[$remoteModel->alias],array_flip($exclude));
			$a = array_intersect_key($a,$b);
			$b = array_intersect_key($b,$a);
			return !empty($a) && $a == $b;
		}
		
		function updateRemoteEntry(){

			$entry = $this->getPrepedEntry();
			$remoteEntry = $this->getRemoteEntry();
			$settings = $this->LocalModel->migrationSettings();
			$remoteModel = $this->getRemoteModel();
			
			$exclude = array('created','modified');
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
			
			$data = array_diff_key($entry['MigrationData'],array_flip($exclude));
			if(empty($data)) return false;

			$data = array_merge($remoteEntry[$remoteModel->alias],$data);
			
			$dry = MigrationConfig::load('dryRun');
			if($dry){
				$saved = true;
				$this->msg('Save attempt on '.$remoteModel->alias);
			}else{
				$saved = $remoteModel->save($data);
			}
			if($saved){
				if($this->LocalModel->updateRemoteFiles($entry, $this->targetInstance)){
					return $this->updateRemoteTracking($remoteEntry[$remoteModel->alias][$remoteModel->primaryKey]);
				}
			}
			return false;
		}
		
		function createRemoteEntry(){
			$entry = $this->getPrepedEntry();
			$settings = $this->LocalModel->migrationSettings();
			$remoteModel = $this->getRemoteModel();
      
			$exclude = array('created','modified');
			
			$data = array_diff_key($entry['MigrationData'],array_flip($exclude));
			if(empty($data)) return false;
      
			$remoteModel->create();
			$dry = MigrationConfig::load('dryRun');
			if($dry){
				$saved = true;
				$this->msg('Insert attempt on '.$remoteModel->alias);
			}else{
				$saved = $remoteModel->save($data);
			}
			if($saved){
				if($this->LocalModel->updateRemoteFiles($entry, $this->targetInstance)){
					return $this->updateRemoteTracking($dry?true:$remoteModel->id);
				}
			}
			return false;
		}
		
		
		function updateRemoteTracking($remoteId = null){
			$entry = $this->getPrepedEntry();
			$settings = $this->LocalModel->migrationSettings();
			$localId = $this->getLocalId();
			$MR = $this->MigrationRemote;
			$MR->id = null;
			$fullName = $this->LocalModel->getFullName();
			$findOpt = array('conditions'=>array(
				$MR->alias.'.model' => $fullName,
				$MR->alias.'.instance' => $this->targetInstance,
				$MR->alias.'.local_id' => $localId,
			));
			$data = $MR->find('first',$findOpt);
			$data[$MR->alias]['model'] = $fullName;
			$data[$MR->alias]['instance'] = $this->targetInstance;
			$data[$MR->alias]['local_id'] = $localId;
			if(!is_null($remoteId)) $data[$MR->alias]['remote_id'] = $remoteId;
			$format = $MR->getDataSource()->columns['datetime']['format'];
			$data[$MR->alias]['updated'] = date($format);
			$data[$MR->alias]['sign'] = $this->getLocalSign(); 
			$data[$MR->alias]['invalidated'] = 0;
			
			if(!empty($settings['overridable'])){
				$raw = $this->LocalModel->getRawEntry($entry);
				$overridable = array_intersect_key($raw[$this->LocalModel->alias],array_flip((array)$settings['overridable']));
				$data[$MR->alias]['overridable'] = serialize($overridable);
			}else{
				$data[$MR->alias]['overridable'] = null;
			}
			
			if(!empty($remoteId)){
				$this->checkWasMissing();
			}
			
			$dry = MigrationConfig::load('dryRun');
			if($dry){
				$saved = true;
				$this->msg('Save attempt on '.$MR->alias);
			}else{
				$saved = $MR->save($data);
			}
			if($saved){
				if(!empty($entry['MigrationTracking'])){
					Migration::updateAllTracking($entry['MigrationTracking']);
				}
				if(!empty($entry['MigrationMissing'])){
					return $this->LocalModel->setMigrationMissing($entry['MigrationMissing'],$MR->id);
				}
				return true;
			}else{
				return false;
			}
		}
		
		function checkWasMissing(){
			$localId = $this->getLocalId();
			$fullName = $this->LocalModel->getFullName();
			$MR = $this->MigrationRemote;
			$MMissing = $this->MigrationMissing;
			$toInvalidate = $MMissing->find('all',array(
				'conditions'=>array(
					$MR->alias.'.instance' => $this->targetInstance,
					$MMissing->alias.'.model' => $fullName,
					$MMissing->alias.'.local_id' => $localId,
				),
				'recursive'=>0
			));
			$idsToInvalidate = array();
			foreach ($toInvalidate as $inv) {
				$idsToInvalidate[] = $inv[$MMissing->alias]['migration_remote_id'];
				
				$this->Batch->Process->invalidated[] = array(
					'model' => $inv[$MR->alias]['model'],
					'local_id' => $inv[$MR->alias]['local_id'],
				);
			}
			if(!empty($toInvalidate)){
				$dry = MigrationConfig::load('dryRun');
				if($dry){
					$this->msg('Update attempt on '.$MR->alias);
				}else{
					$MR->updateAll(array('invalidated'=>1), array('id'=>array_values($idsToInvalidate)));
				}
			}
		}
		
		
		var $_localSign;
		function getLocalSign(){
			if(isset($this->_localSign)) return $this->_localSign;
			
			$entry = $this->getPrepedEntry();
			$data = $entry['MigrationData'];
			return $this->_localSign = $this->getDataSign($data);
		}
		
		var $_remoteSign;
		function getRemoteSign(){
			if(isset($this->_remoteSign)) return $this->_remoteSign;

			$remoteEntry = $this->getRemoteEntry();
			$remoteModel = $this->getRemoteModel();
			$data = $remoteEntry[$remoteModel->alias];
			return $this->_remoteSign = $this->getDataSign($data);
		}
		
		function getDataSign($data){
			$settings = $this->LocalModel->migrationSettings();
			$exclude = array_merge(array($this->LocalModel->primaryKey,'created','modified'),$settings['excludeFields'],(array)$settings['overridable']);
			$last_data = array_diff_key($data,array_flip($exclude));
			return sha1(serialize($last_data));
		}
	}
?>