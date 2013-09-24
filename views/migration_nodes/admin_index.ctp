<?php
	$this->Html->css('/migration/css/admin',null,array('inline'=>false));
	$this->Paginator->options(array('url' => $this->passedArgs));
?>
<div class="migrationNodes index">
	<?php
		echo $this->Form->create('MigrationNode', array('class' => 'search', 'url' => array('action' => 'index',$this->params['pass'][0])));
		echo $this->Form->input('q', array('class' => 'keyword', 'label' => false, 'after' => $form->submit(__('Search', true), array('div' => false))));
		echo $this->Form->end(); 
	?>	
	<h2><?php __('Migration Nodes');?></h2>
	
	<?php echo $this->Form->create('MigrationNode',array('url' => $this->passedArgs));?>
	<table cellpadding="0" cellspacing="0">
		<tr>
			<?php $j=0; ?>
			<?php foreach ($fields as $fieldName=>$field) { ?>
			<th class="<?php echo $fieldName.($j==0?' firstCol':'') ?>"><?php echo $this->Paginator->sort($field['label'],$fieldName);?></th>	
			<?php $j++; ?>			
			<?php } ?>
			<th><?php echo $this->Paginator->sort('tracked');?></th>			
			<?php /*
				<th class="actions"><?php __('Actions');?></th>
			*/ ?>
		</tr>
		<?php
			$i = 0;
			$bool = array(__('No', true), __('Yes', true), null => __('No', true));
			foreach ($migrationNodes as $migrationNode) {
				$class = null;
				if ($i++ % 2 == 0) {
					$class = ' class="altrow"';
				}
				?>
					<tr<?php echo $class;?>>
						<?php $j=0; ?>
						<?php foreach ($fields as $fieldName=>$field) { ?>
						<td class="<?php echo $fieldName.($j==0?' firstCol':'') ?>"><?php echo $migrationNode[$modelAlias][$fieldName]; ?>&nbsp;</td>
						<?php $j++; ?>
						<?php } ?>
						<td class="tracked"><?php
							echo $this->Form->input('MigrationNode.tracked.'.$migrationNode[$modelAlias][$modelPrimary],array('type'=>'checkbox','label'=>false));
						?></td>
						<?php /*
						<td class="actions">
							<?php echo $this->Html->link(__('Edit', true), array('action' => 'edit', $migrationNode['MigrationNode']['id']), array('class' => 'edit')); ?>
							<?php echo $this->Html->link(__('Delete', true), array('action' => 'delete', $migrationNode['MigrationNode']['id']), array('class' => 'delete'), sprintf(__('Are you sure you want to delete # %s?', true), $migrationNode['MigrationNode']['id'])); ?>
						</td>
						*/?>
					</tr>
				<?php
			}
		?>
		<tr>
			<th class="invisible" colspan="<?php echo count($fields); ?>">&nbsp;</th>
			<th class="tracked"><?php echo $form->submit(__('Submit', true)); ?></th>			
			<?php /*
				<th class="actions"><?php __('Actions');?></th>
			*/ ?>
		</tr>
	</table>
	<?php echo $this->Form->end();?>
	
	<p class="paging">
		<?php
			echo $this->Paginator->counter(array(
				'format' => __('Page %page% of %pages%, showing %current% records out of %count% total, starting on record %start%, ending on %end%', true)
			));
		?>	</p>

	<div class="paging">
		<?php echo $this->Paginator->prev('« '.__('previous', true), array(), null, array('class'=>'disabled'));?>
 |
		<?php echo $this->Paginator->numbers();?>
 |
		<?php echo $this->Paginator->next(__('next', true).' »', array(), null, array('class' => 'disabled'));?>
	</div>
</div>
<div class="actions">
	<ul>
		<li><?php echo $this->Html->link(__('Synchronization', true), array('plugin'=>'migration','controller'=>'migration','action' => 'index')); ?></li>
	</ul>
</div>