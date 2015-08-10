<?php
	$this->Html->css('/migration/css/admin',null,array('inline'=>false));
	$this->Paginator->options(array('url' => $this->passedArgs));
	$this->Html->script('/migration/js/pending',array('inline'=>false));
?>
<div class="migrationNodes index">
	<?php
		echo $this->Form->create('MigrationNode', array('class' => 'search', 'url' => array('action' => 'pendings',$this->params['pass'][0])));
		echo $this->Form->input('q', array('class' => 'keyword', 'label' => false, 'after' => $form->submit(__('Search', true), array('div' => false))));
		echo $this->Form->end(); 
    $pModel = $modelAlias;
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
			<th><?php __('Migrate');?></th>	
			<th class="actions"><?php __('Actions');?></th>
		</tr>
		<?php
			$i = 0;
			$bool = array(__('No', true), __('Yes', true), null => __('No', true));
			foreach ($migrationNodes as $migrationNode) {
				$class = array();
				if ($i++ % 2 == 0) {
					$class[] = 'altrow';
				}
        if(!empty($options['force']) && in_array($migrationNode[$modelAlias][$modelPrimary],$options['force'])){
					$class[] = 'forced';
        }
        $class = count($class)?' class="'.implode(' ',$class).'"':'';
				?>
					<tr<?php echo $class;?>>
						<?php $j=0; ?>
						<?php foreach ($fields as $fieldName=>$field) { ?>
						<td class="<?php echo $fieldName.($j==0?' firstCol':'') ?>"><?php echo $migrationNode[$modelAlias][$fieldName]; ?>&nbsp;</td>
						<?php $j++; ?>
						<?php } ?>
						<td class="migrated">
						<?php
							echo $this->Form->input('MigrationNode.migrated.'.$modelAlias.'.'.$migrationNode[$modelAlias][$modelPrimary],array('type'=>'checkbox','label'=>false));
						?>
						</td>
						<td class="actions">
							<?php echo $this->Html->link(__('Diff', true), array('plugin' => 'migration','action' => 'diff', $modelUrlAlias,$migrationNode[$modelAlias][$modelPrimary]), array('class' => 'diff')); ?>
							<?php //echo $this->Html->link(__('Delete', true), array('action' => 'delete', $migrationNode['MigrationNode']['id']), array('class' => 'delete'), sprintf(__('Are you sure you want to delete # %s?', true), $migrationNode['MigrationNode']['id'])); ?>
						</td>
					</tr>
				<?php
			}
      // debug($deleted);
		?>
    <?php if(!empty($deleted)){ ?>
      <tr>
          <td colspan="<?php echo count($fields) + 2; ?>" class="pagin_cell" >
            <p class="paging">
              <?php
                echo $this->Paginator->counter(array(
                  'format' => __('Page %page% of %pages%, showing %current% records out of %count% total, starting on record %start%, ending on %end%', true),
                  'model'=>$pModel
                ));
              ?>	</p>

            <div class="paging">
              <?php echo $this->Paginator->prev('« '.__('previous', true), array('model'=>$pModel), null, array('class'=>'disabled'));?>
           |
              <?php echo $this->Paginator->numbers(array('model'=>$pModel));?>
           |
              <?php echo $this->Paginator->next(__('next', true).' »', array('model'=>$pModel), null, array('class' => 'disabled'));?>
            </div>
          </td>
      </tr>
      <?php foreach($deleted as $instance => $deletedEntries){ ?>
        <tr>
          <th colspan="<?php echo count($fields) + 2; ?>"><?php echo str_replace('%instance%',$targets[$instance],__('To delete in %instance%',true)); ?></th>
        </tr>
        <?php
        $i = 0;
        foreach ($deletedEntries as $migrationNode) {
          $class = null;
          if ($i++ % 2 == 0) {
            $class = ' class="altrow"';
          }
          ?>
            <tr<?php echo $class;?>>
              <?php $j=0; ?>
              <?php foreach ($fields as $fieldName=>$field) { ?>
              <td class="<?php echo $fieldName.($j==0?' firstCol':'') ?>"><?php echo $migrationNode[$deletedModelAlias][$fieldName]; ?>&nbsp;</td>
              <?php $j++; ?>
              <?php } ?>
              <td class="migrated">
              <?php
                echo $this->Form->input('MigrationNode.migrated.'.$deletedModelAlias.'.'.$migrationNode[$deletedModelAlias][$modelPrimary],array('type'=>'checkbox','label'=>false));
              ?>
              </td>
              <td class="actions">
                <?php echo $this->Html->link(__('View', true), array('plugin' => 'migration','action' => 'deleted', $instance, $modelUrlAlias,$migrationNode[$deletedModelAlias][$modelPrimary]), array('class' => 'view')); ?>
                <?php //echo $this->Html->link(__('Delete', true), array('action' => 'delete', $migrationNode['MigrationNode']['id']), array('class' => 'delete'), sprintf(__('Are you sure you want to delete # %s?', true), $migrationNode['MigrationNode']['id'])); ?>
              </td>
            </tr>
          <?php } ?>
          <?php
            $this->Paginator->options(array('model'=>$deletedModelAlias));
            $pModel = $deletedModelAlias;
          ?>
          <tr>
            <td colspan="<?php echo count($fields) + 2; ?>" class="pagin_cell" >
              <p class="paging">
                <?php
                  echo $this->Paginator->counter(array(
                    'format' => __('Page %page% of %pages%, showing %current% records out of %count% total, starting on record %start%, ending on %end%', true),
                    'model'=>$pModel
                  ));
                ?>	</p>

              <div class="paging">
                <?php echo $this->Paginator->prev('« '.__('previous', true), array('model'=>$pModel), null, array('class'=>'disabled'));?>
             |
                <?php echo $this->Paginator->numbers(array('model'=>$pModel));?>
             |
                <?php echo $this->Paginator->next(__('next', true).' »', array('model'=>$pModel), null, array('class' => 'disabled'));?>
              </div>
            </td>
        </tr>
      <?php } ?>
    <?php } ?>
		<tr>
			<th class="invisible" colspan="<?php echo count($fields); ?>">&nbsp;</th>
			<th class="migrated">
				<?php echo $form->submit(__('Submit', true)); ?>
				<?php echo $form->input('includeMode',array('type'=>'checkbox','label'=>__('Inverse selections',true),'div'=>array('class'=>'includeModeInput'))); ?>
			</th>			
			<?php /*
				<th class="actions"><?php __('Actions');?></th>
			*/ ?>
			<th class="invisible">&nbsp;</th>
		</tr>
	</table>
	<?php echo $this->Form->end();?>
	
  <?php if(empty($deleted)){ ?>
  <p class="paging">
    <?php 
      echo $this->Paginator->counter(array(
        'format' => __('Page %page% of %pages%, showing %current% records out of %count% total, starting on record %start%, ending on %end%', true),
        'model'=>$pModel
      ));
    ?>	</p>

  <div class="paging">
    <?php echo $this->Paginator->prev('« '.__('previous', true), array('model'=>$pModel), null, array('class'=>'disabled'));?>
 |
    <?php echo $this->Paginator->numbers(array('model'=>$pModel));?>
 |
    <?php echo $this->Paginator->next(__('next', true).' »', array('model'=>$pModel), null, array('class' => 'disabled'));?>
  </div>
  <?php } ?>
</div>
<div class="actions">
	<ul>
		<li><?php echo $this->Html->link(__('Synchronization', true), array('plugin'=>'migration','controller'=>'migration','action' => 'index')); ?></li>
	</ul>
</div>