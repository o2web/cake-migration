<div class="migration index">
	<?php
		echo $this->Form->create('Migration',array('url'=>array('controller'=>'migration')));
		?>
			<fieldset><legend><?php __('Models'); ?></legend>
		<?php
			foreach($models as $model){
				echo $this->Form->input('Migration.models.'.$model['name'],array('type'=>'checkbox','label'=>__($model['class'],true).' ('.sprintf(__('%s Pendings', true), $model['count']).')','options'=>false));
			}
		?>
			</fieldset>
			<fieldset><legend><?php __('Targets'); ?></legend>
		<?php
			foreach($targets as $name => $label){
				echo $this->Form->input('Migration.targets.'.$name,array('type'=>'checkbox','label'=>$label,'options'=>false));
			}
		?>
			</fieldset>
		<?php
		
		echo $this->Form->end(__('Synchronize',true));
	?>
</div>
<div class="actions">
	<ul>
		<li><?php echo $this->Html->link(__('Clear Cache', true), array('action' => 'clear_cache')); ?></li>
	</ul>
</div>