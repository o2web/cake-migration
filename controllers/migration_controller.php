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
		
		//$Model = Migration::getLocalModel('Slider');
		//debug($Model->migrationDeletedCount());
		// $Model = Migration::getLocalModel('Page');
		// debug($Model->migrationDeletedCount());
		
		$posted = !empty($this->data);
		if($posted){
			foreach($this->data['Migration']['targets'] as $target => $active){
				if($active){
					$process = new MigrationProcess($target);
					foreach($this->data['Migration']['models'] as $modelName => $active){
						if($active){
							$modelName = str_replace('-','.',$modelName);
              //$process->models[$modelName] = $this->Migrated->findOpt($modelName);
              $process->setModelOpt($modelName,$this->Migrated->getModelOpt($modelName));
						}
					}
          // debug($process->models);
					$process->run();
					
					$this->Session->setFlash(implode("<br>\n",$process->msgs));
				}
			}
			
			$dry = MigrationConfig::load('dryRun');
			if($dry){
				$this->data = array();
				$posted = false;
			}else{
				$this->Migrated->clear();
				$this->redirect(array('action' => 'index'));
			}
		}
		
		$modelsNames = Migration::migratingModels();
    $models = array();
		$pendings = Migration::pendingList();
		foreach($modelsNames as $mname){
			$m = array(
				'class' => Migration::classNameParts($mname,'class'),
				'name' => str_replace('.','-',$mname),
				'count' => 0,
				'deleted_count' => 0,
				'migrated_count' => 0,
				'param' => Migration::modelNameToUrl($mname),
			);
			if(!empty($pendings[$mname])){
				$m['count'] = array_sum($pendings[$mname]);
				$m['deleted_count'] = empty($pendings[$mname]['delete'])?0:$pendings[$mname]['delete'];
				$m['migrated_count'] = $this->Migrated->alterCount($mname,$m['count']);
				if(!$posted) $this->data['Migration']['models'][$m['name']] = 1;
			}
      $models[] = $m;
		}
		// debug($models);
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