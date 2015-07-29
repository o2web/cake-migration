<?php
	$this->Html->css('/migration/css/admin',null,array('inline'=>false));
  $this->Html->script('/migration/js/list_models.js',array('inline'=>false));
?>
<div class="migration index">
	<?php
		echo $this->Form->create('Migration',array('url'=>array('controller'=>'migration'),'deletion_confim'=>__("Some of the pendings modifications are Deletions. Those Deletions can't be undone. Do you want to continue ?",true)));
		?>
			<fieldset class="modelList checkboxList"><legend><?php __('Models'); ?></legend>
		<?php
			$inputs = array();
			foreach($models as $model){
				$after = '';
				if($model['count'] > 0){
					$after .= $this->Html->link(__('List', true), array('plugin'=>'migration','controller'=>'migration_nodes','action' => 'pendings',$model['param']),array('class'=>'btList'));
					$after .= ' - ';
				}
				$after .= $this->Html->link(__('Setting', true), array('plugin'=>'migration','controller'=>'migration_nodes','action' => 'index',$model['param']),array('class'=>'btSettings'));
				
				$count = $model['count'];
				if(isset($model['migrated_count']) && $model['migrated_count'] != $count) {
					$count = $model['migrated_count'] .'/'.$count;
				}
				$inputs[__($model['class'],true)] = $this->Form->input('Migration.models.'.$model['name'],array(
					'type'=>'checkbox',
					'label'=>__(Inflector::humanize(Inflector::underscore($model['class'])),true).' ('.sprintf(__('%s Pendings', true), $count).')',
					'options'=>false,
					'after'=>$after,
          'div'=>array(
            'deleted_count' => $model['deleted_count']
          )
				));
			}
			ksort($inputs);
			echo implode("\n",$inputs);
		?>
			</fieldset>
			<fieldset class="targetList checkboxList"><legend><?php __('Targets'); ?></legend>
		<?php
			$inputs = array();
			foreach($targets as $name => $label){
				$inputs[$label] = $this->Form->input('Migration.targets.'.$name,array(
					'type'=>'checkbox',
					'label'=>$label,
					'options'=>false
				));
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
		<li><?php echo $this->Html->link(__('Clear Cache', true), array('plugin'=>'migration','controller'=>'migration','action' => 'clear_cache')); ?></li>
	</ul>
</div>