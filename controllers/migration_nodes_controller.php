<?php
class MigrationNodesController extends MigrationAppController {

	var $name = 'MigrationNodes';

	function admin_index($model) {
		$q = null;
		if(isset($this->params['named']['q']) && strlen(trim($this->params['named']['q'])) > 0) {
			$q = $this->params['named']['q'];
		} elseif(isset($this->data['MigrationNode']['q']) && strlen(trim($this->data['MigrationNode']['q'])) > 0) {
			$q = $this->data['MigrationNode']['q'];
			$this->params['named']['q'] = $q;
		}
					
		if($q !== null) {
			$this->paginate['conditions']['OR'] = array('MigrationNode.model LIKE' => '%'.$q.'%');
		}

		$this->MigrationNode->recursive = 0;
		$this->set('migrationNodes', $this->paginate());
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