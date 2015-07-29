<?php
	$this->Html->css('/migration/css/admin',null,array('inline'=>false));
	$this->Html->script('/migration/bower_components/jsdiff/diff.js',array('inline'=>false));
	$this->Html->script('/migration/js/diff.js',array('inline'=>false));
?>

<div class="migrationNodeDiff">
	<table class="diff" cellpadding="0" cellspacing="0">
		<tr>
			<th class="remote" width="50%"><?php __('Entry to be deleted on the remote instance'); ?></th>
		</tr>
		<?php 
			$i = 0;
			foreach ($remote[$modelAlias] as $field => $val) { 
				$rowClasses = array('fieldRow',$i % 2 == 0 ? 'even' : 'odd altrow');
			?>
			<tr class="<?php echo implode(' ', $rowClasses) ?>">
				<td class="remote">
					<h2 class="fieldTitle"><?php __(Inflector::humanize($field)); ?></h2>
					<div class="val"><?php echo $val ?></div>
				</th>
			</tr>
			<?php 
				$i++;
			} 
		?>
	</table>
</div>
<div class="actions">
	<ul>

		<li><?php echo $this->Html->link(sprintf(__('List %s', true), __('Pendings', true)), array('action' => 'pendings', $localModelAlias));?></li>
		<?php /*
    <li><?php echo $this->Html->link(__('Delete', true), array('action' => 'delete', $this->Form->value('MigrationNode.id')), null, sprintf(__('Are you sure you want to delete # %s?', true), $this->Form->value('MigrationNode.id'))); ?></li>
    <li><?php echo $this->Html->link(sprintf(__('List %s', true), __('Migration Nodes', true)), array('action' => 'index'));?></li>
    */ ?>
	</ul>
</div>