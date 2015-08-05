<?php
class MigrationNodesController extends MigrationAppController {

	var $name = 'MigrationNodes';
	var $components = array('Migration.Migrated');

	function admin_index($modelName) {
		App::import('Lib', 'Migration.Migration');
		$modelName = Migration::urlParamToModelName($modelName);
		$Model = Migration::getLocalModel($modelName);
		$this->loadModel($modelName);
		
		$this->paginate = array_merge($this->paginate,$Model->migrationPaginateOpts());
		
		
		
		$fields = $Model->migrationListFields();
		
		$q = null;
		if(isset($this->params['named']['q']) && strlen(trim($this->params['named']['q'])) > 0) {
			$q = $this->params['named']['q'];
		} elseif(isset($this->data['MigrationNode']['q']) && strlen(trim($this->data['MigrationNode']['q'])) > 0) {
			$q = $this->data['MigrationNode']['q'];
			$this->params['named']['q'] = $q;
		}
		if($q !== null) {
			$this->passedArgs['q'] = $q;
			$this->paginate['conditions'] = $Model->migrationFindCond($q);
		}
		$entries = $this->paginate($Model->name);
		$manual = $Model->migrationSettings('manual');
		if( !empty($this->data['MigrationNode']['tracked']) ) {
			$Model->updateMigrationTracking($this->data['MigrationNode']['tracked']);
		}else{
			foreach ($entries as $entry) {
				$this->data['MigrationNode']['tracked'][$entry[$Model->alias][$Model->primaryKey]] = is_null($entry['MigrationNode']['tracked'])?!$manual:$entry['MigrationNode']['tracked'];
			}
		}

		$Model->recursive = 0;
		$this->set('modelAlias', $Model->alias);
		$this->set('modelUrlAlias', $Model->getUrlName());
		$this->set('modelPrimary', $Model->primaryKey);
		$this->set('fields', $fields);
		$this->set('migrationNodes', $entries);
	}
	
	function admin_pendings($modelName) {
		App::import('Lib', 'Migration.Migration');
		$modelName = Migration::urlParamToModelName($modelName);
		$Model = Migration::getLocalModel($modelName);
		$this->loadModel($modelName);
		
		$this->paginate[$Model->alias] = array_merge(empty($this->paginate[$Model->alias])?array():$this->paginate[$Model->alias],$Model->migrationPendingPaginateOpts());
    
    // $this->paginate[$Model->alias]['limit'] = 1;
		
		$fields = $Model->migrationListFields();
		
		$this->paginate[$Model->alias]['fields'] = array_keys($fields);
    
		$q = null;
		if(isset($this->params['named']['q']) && strlen(trim($this->params['named']['q'])) > 0) {
			$q = $this->params['named']['q'];
		} elseif(isset($this->data['MigrationNode']['q']) && strlen(trim($this->data['MigrationNode']['q'])) > 0) {
			$q = $this->data['MigrationNode']['q'];
			$this->params['named']['q'] = $q;
		}
		if($q !== null) {
			$this->passedArgs['q'] = $q;
			$this->paginate[$Model->alias]['conditions'] = $Model->migrationFindCond($q);
		}
		
		$entries = $this->paginate($Model);
		if( !empty($this->data['MigrationNode']['migrated']) ) {
			$this->Migrated->setMode($Model->alias,$this->data['MigrationNode']['includeMode']);
			$this->Migrated->updateStates($Model->alias,$this->data['MigrationNode']['migrated'][$Model->alias]);
      $redirect = array('plugin'=>'migration','controller'=>'migration','action' => 'index');
		}else{
			$this->data['MigrationNode']['includeMode'] = $this->Migrated->isIncludeMode($Model->alias);
			$this->data['MigrationNode']['migrated'][$Model->alias] = $this->Migrated->getEntriesStates($Model,$entries);
		}
		
    $deletedIds = $Model->migrationDeletedIds();
    if(!empty($deletedIds)){
      $deleted = array();
      foreach($deletedIds as $instance => $ids){
        $remoteModel = Migration::getRemoteModel($Model,$instance);
        // debug($Model->migrationPendingPaginateOpts());
        
        $this->paginate[$remoteModel->alias]['fields'] = array_keys($fields);
        $this->paginate[$remoteModel->alias]['conditions'][$remoteModel->primaryKey] = $ids;
        
        
        // $this->paginate[$remoteModel->alias]['limit'] = 1;
    
        $deleted[$instance] = $this->paginate($remoteModel);
        
        
        if( !empty($this->data['MigrationNode']['migrated'][$remoteModel->alias]) ) {
          $this->Migrated->updateStates($Model->alias,$this->data['MigrationNode']['migrated'][$remoteModel->alias],$instance);
        }else{
          $this->data['MigrationNode']['migrated'][$remoteModel->alias] = $this->Migrated->getEntriesStates($Model,$deleted[$instance],$instance);
        }
      }
      
      if(!empty($redirect)){
        // debug($this->Migrated->modelsOpt);
        $this->redirect($redirect);
      }
      
      $this->set('deletedModelAlias', $remoteModel->alias);
      $this->set('deleted', $deleted);
      $targets = Migration::targetList();
      $this->set('targets', $targets);
    }
    
    
		$this->set('modelAlias', $Model->alias);
		$this->set('modelUrlAlias', $Model->getUrlName());
		$this->set('modelPrimary', $Model->primaryKey);
		$this->set('fields', $fields);
		$this->set('migrationNodes', $entries);
		
	}
	
	function admin_diff($modelName,$id) {
		App::import('Lib', 'Migration.Migration');
		$modelName = Migration::urlParamToModelName($modelName);
		$Model = Migration::getLocalModel($modelName);
		$this->loadModel($modelName);
		
		$Model->joinMigrationRemote();
		$local = $Model->read(null,$id);
		$targets = Migration::targetList();
		$remotes = array();
		foreach($targets as $key => $label){
			$remoteModel = Migration::getRemoteModel($Model,$key);
			$remotes[$key] = $Model->getRemoteEntry($local,$key);
			$remotes[$key]['alias'] = $remoteModel->alias;
		}
		$local = $Model->getRawEntry($local);
		
		$this->set('local', $local);
		$this->set('modelAlias', $Model->alias);
		$this->set('modelUrlAlias', $Model->getUrlName());
		$this->set('remotes', $remotes);
		$this->set('targets', $targets);
	}

	function admin_deleted($instance,$modelName,$id) {
		App::import('Lib', 'Migration.Migration');
    $Model = Migration::getLocalModel($modelName);
    $remoteModel = Migration::getRemoteModel($Model,$instance);
    $remote = $remoteModel->read(null,$id);
		$this->set('modelUrlAlias', $Model->getUrlName());
		$this->set('modelAlias', $remoteModel->alias);
		$this->set('remote', $remote);
  }
  
	function admin_edit($id = null) {
		if (!$id && empty($this->data)) {
			$this->Session->setFlash(sprintf(__('Invalid %s', true), 'migration node'));
			$this->redirect(array('action' => 'index'));
		}
		if (!empty($this->data)) {
			if ($this->MigrationNode->save($this->data)) {
				$this->Session->setFlash(sprintf(__('The %s has been saved', true), 'migration node'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(sprintf(__('The %s could not be saved. Please, try again.', true), 'migration node'));
			}
		}
		if (empty($this->data)) {
			$this->data = $this->MigrationNode->read(null, $id);
		}
	}
	
	function admin_delete($id = null) {
		if (!$id) {
			$this->Session->setFlash(sprintf(__('Invalid id for %s', true), 'migration node'));
			$this->redirect(array('action'=>'index'));
		}
		if ($this->MigrationNode->delete($id)) {
			$this->Session->setFlash(sprintf(__('%s deleted', true), 'Migration node'));
			$this->redirect(array('action'=>'index'));
		}
		$this->Session->setFlash(sprintf(__('%s was not deleted', true), 'Migration node'));
		$this->redirect(array('action' => 'index'));
	}
	
}
?>