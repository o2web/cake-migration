<?php
class MigrationConfig extends Object {
	/*
		App::import('Lib', 'Migration.MigrationConfig');
		MigrationConfig::load();
	*/
	
	var $loaded = false;
	var $defaultConfig = array(
		'preset' => array(
			'Settings.Setting' => array(
				'remapFields'=>'title',
				'overridable'=>array('string_fre','string_eng','text_fre','text_eng','m_photo_fre','m_photo_eng','m_video_fre','m_video_eng','m_document_fre','m_document_eng','checkbox','select','number','options')
			),
		),
		'instance_name' => null,
		'target_instances' => array(),
		'harmfullBehaviors' => array('History'),
	);
	
	//$_this =& MigrationConfig::getInstance();
	function &getInstance() {
		static $instance = array();
		if (!$instance) {
			$instance[0] =& new MigrationConfig();
		}
		return $instance[0];
	}
	
	function load($path = true){
		$_this =& MigrationConfig::getInstance();
		if(!$_this->loaded){
			config('plugins/migration');
			$config = Configure::read('Migration');
			$config = Set::merge($_this->defaultConfig,$config);
			Configure::write('Migration',$config);
			$_this->loaded = true;
		}
		if(!empty($path)){
			return Configure::read('Migration'.($path!==true?'.'.$path:''));
		}
	}
	
	
}
?>