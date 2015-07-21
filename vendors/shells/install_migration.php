<?php
class InstallMigrationShell extends Shell {
		function main() {
      $this->addMenu();
      
      $locations = array('setLocalInstance'=>"On the same server",'setFtpInstance'=>"On a remote server accessible by FTP");
      $res = $this->choiceList(__('Where is situated the target instance ?',true),$locations);
      
      if(!empty($res) && method_exists($this,$res)){
        $this->{$res}(); 
      }else{
        $this->error('Invalid Input');
      }
      
    }
    
    function addMenu(){
      $admin_layout_path = VIEWS.'layouts'.DS.'admin.ctp';
      $this->insert("<?php\n  echo \$this->element('menu_item',array('plugin' => 'migration'));\n?>",$admin_layout_path,array(
        'q' => __('Do you want to create an admin menu ?',true),
        'after' => array(
          '/array\(\'plugin\'\s*\=\>\s*\'settings\',\s*\'controller\' \=\>\s*\'settings\',\s*\'action\'\s*\=\>\s*\'index\'.*\?\>.*\n/',
          '/\<div\s*id\="menu"\>\s*\r?\n\s*\<ul\>\s*\r?\n/'
        ),
        'match' => '/echo\s*\$this\-\>element\(("|\')menu_item("|\'),array\(.*("|\')plugin("|\')\s*\=\>\s*("|\')migration("|\').*\)\);/',
      ));
      
    }
    
    function setLocalInstance(){
      $data = array(
        'mode'=>'local',
        'target_name'=>$this->in(__('Target Instance Name',true),null,'Migration Target')
      );
      $valid = false;
      while(!$valid){
        $res = $this->in(__('Path to the target instance app folder',true).' ('.__('"q" to quit',true).')',null,'q');
        if($res === 'q') exit();
        $data['path'] = realpath($res);
        if(!file_exists($data['path'])){
          $this->out(__('Folder not found',true));
        }else{
          if(file_exists($data['path'].DS.'app')) $data['path'] = $data['path'].DS.'app';
          if(!file_exists($data['path'].DS.'config'.DS.'database.php')){
            $this->out(__('Target instance config file not found',true));
          }else{
            require $data['path'].DS.'config'.DS.'database.php';
            $conf = new DATABASE_CONFIG();
            $data['db'] = $conf->default;
            
            $this->config_file($data);
            $valid = true;
          }
        }
      }
    }
    
    function config_file($data){
      ob_start();
      extract($data);
      include $this->_pluginPath('migration').'vendors'.DS."config_template.php";
      $content = ob_get_clean();
      
      file_put_contents(CONFIGS.'plugins'.DS.'migration.php',$content);
    }
    
    function setFtpInstance(){
      $this->error('Not implemented');
    }
    
    function indent($text,$indent){
      return preg_replace('/\n/', '$0'.$indent,$text);
    }
    
		function choiceList($q,$values){
      $this->out();
			foreach(array_values($values) as $i => $val){
				$this->out(($i+1).'. '.$val);
			}
      $this->out();
      $res = null;
      while(empty(array_keys($values)[$res-1])){
        $res = $this->in($q.' ('.__('Enter a number from the list above or "q" to quit',true).')',null,'q');
        if($res === 'q') exit();
      }
      return array_keys($values)[$res-1];
		}
    
    function insert($content,$file,$options = array()){
      $def = array(
        'q' => null,
        'after' => null,
        'match' => $content,
      );
      $opt = array_merge($def,$options);
      if(file_exists($file)){
        $file_content = file_get_contents($file);
        
        if(!empty($opt['match']) && $this->_match($opt['match'],$file_content)){
          return null;
        }
        
        if(!empty($opt['q'])){
          $res = $this->in($opt['q'].' ('.__('(Y/n)',true).')',null,'Y');
          if(strtolower($res) != 'y'){
            return null;
          }
        }
        
        if(!empty($opt['after'])){
          if(!is_array($opt['after'])) $opt['after'] = array($opt['after']);
          foreach ($opt['after'] as $after) {
            if($this->_match($after,$file_content,$pos)){
              file_put_contents($file,substr($file_content,0,$pos['end']).$content."\n".substr($file_content,$pos['end']));
              return true;
            }
          }
          $this->error('Insert position not found');
        }else{
          file_put_contents($file,$file_content."\n".$content);
          return true;
        }
        
      }
    }
    
    function _match($match,$content,&$pos = null){
      if($this->_isPreg($match)){
        if(preg_match($match,$content,$match, PREG_OFFSET_CAPTURE)){
          $pos = array('start'=>$match[0][1],'end'=>$match[0][1] + strlen($match[0][0]));
          return true;
        }
      }else{
        $p = strpos($content,$match);
        if($p !== false){
          $pos = array('start'=>$p,'end'=>$p + strlen($match));
          return true;
        }
      }
      return false;
    }
    
    
    function _isPreg($string){
      return preg_match('#^/.*/[a-z]*$#i',$string);
    }
    
}
?>