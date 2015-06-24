<?php
$app = App::$instance;
$inner = $LAYOUT->getInner();
?>
<div class="page manage">
	<div class="content">
		<?php if (empty($inner)) { ?>
		
		<div class="links">
			<div class="link">
				<a href="manage/view">View your lineups</a>
			</div>
			<div class="link">
				<a href="manage/new">Create new lineup</a>
			</div>
		</div>
		
		<?php } else { echo $inner; } ?>
		
		<a href="<?php echo getUrl('home'); ?>">Back</a>
	</div>
</div>