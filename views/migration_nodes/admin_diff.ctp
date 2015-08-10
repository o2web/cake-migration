<?php
	$this->Html->css('/migration/css/admin',null,array('inline'=>false));
	$this->Html->script('/migration/bower_components/jsdiff/diff.js',array('inline'=>false));
	$this->Html->script('/migration/js/diff.js',array('inline'=>false));
?>

<div class="migrationNodeDiff">
	<table class="diff" cellpadding="0" cellspacing="0">
		<tr>
			<th class="local" width="50%"><?php __('Local'); ?></th>
			<th class="remote" width="50%"><?php echo $this->Form->input('remote',array('options'=>$targets,'label'=>false)); ?></th>
		</tr>
		<?php 
			$i = 0;
			foreach ($local[$modelAlias] as $field => $val) { 
				$rowClasses = array('fieldRow',$i % 2 == 0 ? 'even' : 'odd altrow');
				/*$rowClasses[] = 'different';*/
				$j = 0;
				$remotesRows = array();
				foreach ($remotes as $key => $remote) { 
					$content = '';
					$classes = array('remote');
					if(!empty($remote[$remote['alias']])){
						$rval = $remote[$remote['alias']][$field];
						if ($rval !== $val) {
							if ($j == 0) $rowClasses[] = 'different';
							$classes[] = 'different';
						}
						$content = '<div class="val">'.$rval.'</div>';
					}else{
						$classes[] = 'missing';
						if ($j == 0) $rowClasses[] = 'missing';
						if($i == 0){
							$content = '<span class="missingMsg">'.__('This entry does not exists on this instance, it will be created on the next synchronisation',true).'</span>';
						}
					}
					if ($j == 0) $classes[] = 'active';
					$remotesRows[] = '<div class="'.implode(' ', $classes).'">'.$content.'</div>';
					$j++;
				} 
			?>
			<tr class="<?php echo implode(' ', $rowClasses) ?>">
				<td class="local">
					<h2 class="fieldTitle"><?php __(Inflector::humanize($field)); ?></h2>
					<div class="val"><?php echo $val ?></div>
				</th>
				<td class="remotes">
					<h2 class="fieldTitle"><?php __(Inflector::humanize($field)); ?></h2>
					<?php echo implode('\n',$remotesRows)?>
				</td>
			</tr>
			<?php 
				$i++;
			} 
		?>
	</table>
</div>
<div class="actions">
	<ul>

		<li><?php echo $this->Html->link(sprintf(__('List %s', true), __('Pendings', true)), array('action' => 'pendings', $modelUrlAlias));?></li>
		<li><?php echo $this->Html->link(__('Force update', true), array('action' => 'force', $modelUrlAlias, $localId));?></li>
		<?php /*
    <li><?php echo $this->Html->link(__('Delete', true), array('action' => 'delete', $this->Form->value('MigrationNode.id')), null, sprintf(__('Are you sure you want to delete # %s?', true), $this->Form->value('MigrationNode.id'))); ?></li>
    <li><?php echo $this->Html->link(sprintf(__('List %s', true), __('Migration Nodes', true)), array('action' => 'index'));?></li>
    */ ?>
	</ul>
</div>