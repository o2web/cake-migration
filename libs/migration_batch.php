<?php
	class MigrationBatch extends Object {
		var $LocalModel;
		var $Process;
		function __construct ($Process,$LocalModel,$options){
			App::import('Lib', 'Migration.Migration');
			App::import('Lib', 'Migration.EntrySync');
			$this->Process = $Process;
			$this->LocalModel = $LocalModel;
			
			$this->options = $options;
      
			$defOpt = array(
				'limit' => 200
			);
			$this->findOpt = array_merge($defOpt,$this->findOpt());
			
			$this->MigrationRemote = ClassRegistry::init('Migration.MigrationRemote');
			$this->MigrationNode = ClassRegistry::init('Migration.MigrationNode');
			$this->MigrationMissing = ClassRegistry::init('Migration.MigrationMissing');
			
		}
    
    function getCond(){
      $opt = $this->options;
      if(empty($opt['execeptions'])){
        return array();
      }
      $key = '`'.$this->LocalModel->alias.'`.`'.$this->LocalModel->primaryKey.'`';
      if($opt['mode'] == 'exclude'){
        return array('not'=>array($key=>$opt['execeptions']));
      }else{
        return array($key=>$opt['execeptions']);
      }
    }
    function findOpt(){
      $cond = $this->getCond();
      if(!empty($cond)){
        return array('conditions'=>$cond);
      }else{
        return array();
      }
    }
		
		function msg($msg){
			$this->Process->msg($msg);
		}
		
		function process(){
			
			$MR = $this->MigrationRemote;
			
			$this->findOpt['fields'] = array($this->LocalModel->alias.'.*',$MR->alias.'.*');
			$findOpt = $this->LocalModel->pendingFindOpt($this->findOpt,$this->Process->targetInstance);
			
			$entries = $this->LocalModel->find('all',$findOpt);
			//debug($entries);
			
			foreach($entries as $entry){
				$res = $this->syncRemoteEntry($entry);
				$this->msg('ID : '.$entry[$this->LocalModel->alias][$this->LocalModel->primaryKey].' - '.($res?'ok':'error'));
			}
		}
    
    function processDeletions(){
      // debug($this->options);
      $ids = $this->LocalModel->migrationDeletedIds($this->Process->targetInstance);
      if(!empty($ids)){
        if(!empty($this->options['instance']['execeptions'])){
          if($this->options['mode'] == 'exclude'){
            $ids = array_diff($ids,$this->options['instance']['execeptions']);
          }else{
            $ids = array_intersect($ids,$this->options['instance']['execeptions']);
          }
        }
        $remoteModel = Migration::getRemoteModel($this->LocalModel,$this->Process->targetInstance);
        $dry = MigrationConfig::load('dryRun');
        if($dry){
          $this->msg('Delete attempt on '.$remoteModel->alias.' for Ids : '.implode(', ',$ids));
        }else{
          $remoteModel->deleteAll(array($remoteModel->alias.'.'.$remoteModel->primaryKey => $ids));
        }
      }
    }
		
		function reprocess($ids){
			$tmp_opt = $this->findOpt;
			$this->findOpt['conditions'][] = array($this->LocalModel->alias.'.'.$this->LocalModel->primaryKey=>$ids);
			$this->process();
			$this->findOpt = $tmp_opt;
		}
		
		function syncRemoteEntry($entry){
			
			$this->syncs[] = $sync = new EntrySync($this,$entry);
			return $sync->sync();
			
		}
	}
?>