<?php
class MigrationController extends MigrationAppController {

	var $name = 'Migration';
	var $uses = array('Migration.MigrationRemote','Migration.MigrationNode');

	function admin_index() {
		App::import('Lib', 'Migration.Migration');
		
		$posted = !empty($this->data);
		if($posted){
			foreach($this->data['Migration']['models'] as $modelName => $active){
				if($active){
					$modelName = str_replace('-','.',$modelName);
					foreach($this->data['Migration']['targets'] as $target => $active){
						if($active){
							Migration::processBatch($modelName,$target);
						}
					}
				}
			}
		}
		
		$models = Set::normalize(Migration::migratingModels());
		$pendings = Migration::pendingList();
		foreach($models as $mname => &$m){
			$m = array(
				'class' => Migration::classNameParts($mname,'class'),
				'name' => str_replace('.','-',$mname),
				'count' => 0,
			);
			if(!empty($pendings[$mname])){
				$m['count'] = $pendings[$mname];
				if(!$posted) $this->data['Migration']['models'][$m['name']] = 1;
			}
		}
		$targets = Migration::targetList();
		if(!$posted){
			foreach($targets as $key => $label){
				$this->data['Migration']['targets'][$key] = 1;
			}
		}
		$this->set('models',$models);
		$this->set('targets',$targets);
	}
	
	function admin_clear_cache($id = null) {
		App::import('Lib', 'Migration.Migration');
		Migration::clearCache();
		$this->Session->setFlash(sprintf(__('Cache cleared', true), 'Node'));
		$this->redirect(array('action' => 'index'));
	}
	
}
?>