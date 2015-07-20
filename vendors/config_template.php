<?php echo '<?php' ?>
	Configure::write('Migration.target_instances', array(
		'<?php echo Inflector::slug($target_name) ?>' => array(
			'label' => '<?php echo $target_name ?>',
			'database' => <?php echo $this->indent(var_export( $db, true ),'			') ?>,
<?php if( $mode = 'local' ) { ?>
      'path' => '<?php echo $path ?>'
<?php } else if( $mode = 'ftp' ) { ?>
			'ftp'=> <?php echo $this->indent(var_export( $ftp, true ),'			') ?>
<?php } ?>
		),
	));
	// Configure::write('Migration.dryRun', true);
	// Configure::write('Migration.exclude', array('Auth.User'));
	Configure::write('Migration.full', true);
	Configure::write('Migration.last_sync', '<?php echo date("Y-m-d H:i:s"); ?>');