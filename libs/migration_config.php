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
				'mapFields'=>'title',
				'overridable'=>array('string_fre','string_eng','text_fre','text_eng','m_photo_fre','m_photo_eng','m_video_fre','m_video_eng','m_document_fre','m_document_eng','checkbox','select','number','options'),
				'listFields'=>array('id','title','desc'),
			),
			'Multimedia.Multimedium' => array(
				'mapFields'=>array('filename','path'),
				'manual'=>true,
				'files'=>array(
					'pathField' => 'path',
					'fileField' => 'filename',
					'basePath' => 'webroot:',
				)
			),
			'Translations.Translation' => array(
				'mapFields'=>'original',
				'listFields'=>array('id','original'),
      )
		),
		'full'=>false,
		'exclude' => array('History','Migration.MigrationNode','Migration.MigrationRemote','Migration.MigrationMissing'),
		'instance_name' => null,
		'target_instances' => array(),
		'harmfullBehaviors' => array('History'),
		'behaviorsConfig' => array('Tree'=>array('excludeFields'=>array('lft','rght'))),
		'assocParsers' => array(array('Migration','getMultimediaAssoc')),
		'dryRun' => false,
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