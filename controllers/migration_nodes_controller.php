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
		$this->set('modelPrimary', $Model->primaryKey);
		$this->set('fields', $fields);
		$this->set('migrationNodes', $entries);
	}
	
	function admin_pendings($modelName) {
		App::import('Lib', 'Migration.Migration');
		$modelName = Migration::urlParamToModelName($modelName);
		$Model = Migration::getLocalModel($modelName);
		$this->loadModel($modelName);
		
		$this->paginate = array_merge($this->paginate,$Model->migrationPendingPaginateOpts());
		
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
		if( !empty($this->data['MigrationNode']['migrated']) ) {
			$this->Migrated->setMode($Model->alias,$this->data['MigrationNode']['includeMode']);
			$this->Migrated->updateStates($Model->alias,$this->data['MigrationNode']['migrated']);
			$this->redirect(array('plugin'=>'migration','controller'=>'migration','action' => 'index'));
		}else{
			$this->data['MigrationNode']['includeMode'] = $this->Migrated->isIncludeMode($Model->alias);
			$this->data['MigrationNode']['migrated'] = $this->Migrated->getEntriesStates($Model,$entries);
		}
		
		$this->set('modelAlias', $Model->alias);
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
		$this->set('remotes', $remotes);
		$this->set('targets', $targets);
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