<div class="migrationNodes form">
	<?php echo $this->Form->create('MigrationNode');?>
		<fieldset>
			<legend><?php printf(__('Edit %s', true), __('Migration Node', true)); ?></legend>
			<?php
				echo $this->Form->input('active');
				echo $this->Form->input('id');
				echo $this->Form->input('model');
				echo $this->Form->input('local_id');
				echo $this->Form->input('tracked');
			?>
		</fieldset>
	<?php echo $this->Form->end(__('Submit', true));?>
</div>
<div class="actions">
	<ul>

		<li><?php echo $this->Html->link(__('Delete', true), array('action' => 'delete', $this->Form->value('MigrationNode.id')), null, sprintf(__('Are you sure you want to delete # %s?', true), $this->Form->value('MigrationNode.id'))); ?></li>
		<li><?php echo $this->Html->link(sprintf(__('List %s', true), __('Migration Nodes', true)), array('action' => 'index'));?></li>
	</ul>
</div>