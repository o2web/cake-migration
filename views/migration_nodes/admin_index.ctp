<div class="migrationNodes index">
	<?php
		echo $this->Form->create('Migration Node', array('class' => 'search', 'url' => array('action' => 'index')));
		echo $this->Form->input('q', array('class' => 'keyword', 'label' => false, 'after' => $form->submit(__('Search', true), array('div' => false))));
		echo $this->Form->end();
	?>	
	<h2><?php __('Migration Nodes');?></h2>
	
	<table cellpadding="0" cellspacing="0">
		<tr>
			<th><?php echo $this->Paginator->sort('id');?></th>			
			<th><?php echo $this->Paginator->sort('model');?></th>			
			<th><?php echo $this->Paginator->sort('local_id');?></th>			
			<th><?php echo $this->Paginator->sort('tracked');?></th>			
			<th class="actions"><?php __('Actions');?></th>
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
						<td class="id"><?php echo $migrationNode['MigrationNode']['id']; ?>&nbsp;</td>
						<td class="model"><?php echo $migrationNode['MigrationNode']['model']; ?>&nbsp;</td>
						<td class="local_id"><?php echo $migrationNode['MigrationNode']['local_id']; ?>&nbsp;</td>
						<td class="tracked"><?php echo $bool[$migrationNode['MigrationNode']['tracked']]; ?>&nbsp;</td>
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
	</table>
	
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
	</ul>
</div>