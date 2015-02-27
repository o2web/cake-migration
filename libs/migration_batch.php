<?php
	class MigrationBatch extends Object {
		var $LocalModel;
		var $Process;
		function __construct ($Process,$LocalModel,$options){
			App::import('Lib', 'Migration.Migration');
			App::import('Lib', 'Migration.EntrySync');
			$this->Process = $Process;
			$this->LocalModel = $LocalModel;
			
			$defOpt = array(
				'limit' => 20
			);
			$this->options = array_merge($defOpt,$options);
			
			$this->MigrationRemote = ClassRegistry::init('Migration.MigrationRemote');
			$this->MigrationNode = ClassRegistry::init('Migration.MigrationNode');
			$this->MigrationMissing = ClassRegistry::init('Migration.MigrationMissing');
			
		}
		
		function msg($msg){
			$this->Process->msg($msg);
		}
		
		function process(){
			
			$MR = $this->MigrationRemote;
			
			$this->options['fields'] = array($this->LocalModel->alias.'.*',$MR->alias.'.*');
			$findOpt = $this->LocalModel->pendingFindOpt($this->options,$this->Process->targetInstance);
			
			$entries = $this->LocalModel->find('all',$findOpt);
			//debug($entries);
			
			foreach($entries as $entry){
				$res = $this->syncRemoteEntry($entry);
				$this->msg('ID : '.$entry[$this->LocalModel->alias][$this->LocalModel->primaryKey].' - '.($res?'ok':'error'));
			}
		}
		
		function reprocess($ids){
			$tmp_opt = $this->options;
			$this->options['conditions'][] = array($this->LocalModel->alias.'.'.$this->LocalModel->primaryKey=>$ids);
			$this->process();
			$this->options = $tmp_opt;
		}
		
		function syncRemoteEntry($entry){
			
			$this->syncs[] = $sync = new EntrySync($this,$entry);
			return $sync->sync();
			
		}
	}
?>