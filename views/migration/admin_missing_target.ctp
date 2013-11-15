<h1>No target defined</h1>
<p>please set a publication target in your configuration file(app/config/plugins/migration.php).</p>
<p>Example :</p>
<pre>
	Configure::write('Migration.target_instances', array(
		'mywebsite' => array(
			'label' => 'mywebsite.com',
			'database' => array(
				'host' => 'mywebsite.com',
				'login' => 'user_name',
				'password' => 'pass',
				'database' => 'database_name',
			),
			'ftp'=>array(
				'host' => 'mywebsite.com',
				'username' => 'user_name',
				'password' => 'pass',
				'basepath' => 'app/webroot/files',
			)
		)
	));
</pre>