<?php if(file_exists(CONFIGS.DS.'plugins'.DS.'migration.php')){ ?>
	<li><a href="<?php echo $this->Html->url(array('plugin' => 'migration', 'controller' => 'migration', 'action' => 'index')); ?>"><?php __('Mise en ligne'); ?></a></li>
<?php } ?>