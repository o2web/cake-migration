<?php
class MigrationConfig extends Object {
	/*
		App::import('Lib', 'Migration.MigrationConfig');
		MigrationConfig::load();
	*/
	
	var $loaded = false;
	var $defaultConfig = array(
		'preset' => array( // Models can be configured with global settings. Usefull for plugins that cant be altered. See models/behaviors/migration.php
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
		'full'=>false, // Allows all Models to be migrated
		'exclude' => array('History','Migration.MigrationNode','Migration.MigrationRemote','Migration.MigrationMissing'), // Exceptions to the "Full" config
		'instance_name' => null, // Optional : Name of the current instance
		'target_instances' => array( // Instances where the bd content can be sent
      // 'TargetInstanceName' => array(
        // 'label' => 'Name of the target intance',
        // 'database' => array ( // Same format as config/database.php
          // 'driver' => 'mysql',
          // 'persistent' => false,
          // 'host' => 'localhost',
          // 'login' => 'username',
          // 'password' => 'pass',
          // 'database' => 'database',
          // 'prefix' => '',
          // 'encoding' => 'utf8',
        // ),
        // 'path' => '\absolute\path\to\the\instance' // Transfers will be done via the file system. Only if the instance is on the same server
        // 'ftp'=>array( // Transfers will be done via the FTP
          // 'host' => 'hostname.com',
          // 'username' => 'username',
          // 'password' => 'pass',
          // 'basepath' => 'app/webroot/files', // Where is the root of the ftp relative to the root of the project. For optimal security the ftp should only acces the file folder
        // )
      // ),
    ), 
		'harmfullBehaviors' => array('History'), // If a Behavior can cause un problem it can be desactive befor the migration is Run
		'behaviorsConfig' => array( // Config can be added to the Model if a behavior is present
      'Tree'=>array('excludeFields'=>array('lft','rght'))
    ),
		'assocParsers' => array( // Plugins allowing special assiciations to be parsed
      array('Migration','getMultimediaAssoc')
    ),
		'dryRun' => false, // Display a log of all the operations to be done without actually performing them
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