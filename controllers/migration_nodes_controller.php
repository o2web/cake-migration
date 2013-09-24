<?php
class MigrationNodesController extends MigrationAppController {

	var $name = 'MigrationNodes';

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
			foreach ($fields as $fieldName=>$field) {
				$this->paginate['conditions']['OR'][$Model->alias.'.'.$fieldName.' LIKE'] = '%'.$q.'%';
			}
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