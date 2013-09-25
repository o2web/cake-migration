<?php
class MigrationMissing extends MigrationAppModel {
	var $name = 'MigrationMissing';
	
	
	var $belongsTo = array(
		'MigrationRemote' => array(
			'className' => 'Migration.MigrationRemote',
			'foreignKey' => 'migration_remote_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);
}
?>