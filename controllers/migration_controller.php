<?php
class MigrationController extends MigrationAppController {

	var $name = 'Migration';
	var $uses = array('Migration.MigrationRemote','Migration.MigrationNode');
	var $components = array('Migration.Migrated');

	function admin_index() {
		App::import('Lib', 'Migration.Migration');
		App::import('Lib', 'Migration.MigrationProcess');
		
		$targets = Migration::targetList();
		if(empty($targets)){
			$this->render('admin_missing_target');
			return;
		}
		
		$posted = !empty($this->data);
		if($posted){
			foreach($this->data['Migration']['targets'] as $target => $active){
				if($active){
					$process = new MigrationProcess($target);
					foreach($this->data['Migration']['models'] as $modelName => $active){
						if($active){
							$modelName = str_replace('-','.',$modelName);
							$process->processBatch($modelName,$this->Migrated->findOpt($modelName));
						}
					}
					$process->autoSolve();
				}
			}
			$this->Migrated->clear();
			// $this->redirect(array('action' => 'index'));
			$this->data = array();
			$posted = false;
		}
		
		$models = Set::normalize(Migration::migratingModels());
		$pendings = Migration::pendingList();
		foreach($models as $mname => &$m){
			$m = array(
				'class' => Migration::classNameParts($mname,'class'),
				'name' => str_replace('.','-',$mname),
				'count' => 0,
				'param' => Migration::modelNameToUrl($mname),
			);
			if(!empty($pendings[$mname])){
				$m['count'] = $pendings[$mname];
				$m['migrated_count'] = $this->Migrated->alterCount($mname,$pendings[$mname]);
				if(!$posted) $this->data['Migration']['models'][$m['name']] = 1;
			}
		}
		
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