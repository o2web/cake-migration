<?php
	$this->Html->css('/migration/css/admin',null,array('inline'=>false));
	$this->Html->scriptBlock('
		(function( $ ) {
			function updateSelected(){
				if(window.console){
					console.log($(".input.checkbox input:not(:checked)").parent());
				}
				$(".input.checkbox input").parent().removeClass("selected");
				$(".input.checkbox input:checked").parent().addClass("selected");
			}
			$(function(){
				updateSelected();
				$(".input.checkbox input").change(updateSelected);
			})
		})( jQuery );
	',array('inline'=>false));
?>
<div class="migration index">
	<?php
		echo $this->Form->create('Migration',array('url'=>array('controller'=>'migration')));
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
					'after'=>$after
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