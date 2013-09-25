<div class="migration index">
	<?php
		echo $this->Form->create('Migration',array('url'=>array('controller'=>'migration')));
		?>
			<fieldset><legend><?php __('Models'); ?></legend>
		<?php
			$inputs = array();
			foreach($models as $model){
				$after = $this->Html->link(__('Setting', true), array('plugin'=>'migration','controller'=>'migration_nodes','action' => 'index',$model['param']),array('class'=>'btSettings'));
				$inputs[__($model['class'],true)] = $this->Form->input('Migration.models.'.$model['name'],array('type'=>'checkbox','label'=>__($model['class'],true).' ('.sprintf(__('%s Pendings', true), $model['count']).')','options'=>false,'after'=>$after));
			}
			ksort($inputs);
			echo implode("\n",$inputs);
		?>
			</fieldset>
			<fieldset><legend><?php __('Targets'); ?></legend>
		<?php
			$inputs = array();
			foreach($targets as $name => $label){
				$inputs[$label] = $this->Form->input('Migration.targets.'.$name,array('type'=>'checkbox','label'=>$label,'options'=>false));
			}
			ksort($inputs);
			echo implode("\n",$inputs);
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