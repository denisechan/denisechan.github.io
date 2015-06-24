<?php
$app = App::$instance;
$layout = $app->currentLayout;
$inner = $layout->getInner(); 
?>
<div class="test">
	<p>File: [<?php echo $layout->filename; ?>]</p>
	<p>Url: [<?php echo $layout->getTarget(); ?>]</p>
	<?php if ($inner !== null) { ?>
	<p>INNER:</p>
	<div style="margin-left: 40px;"><?php echo $inner; ?></div>
	<?php } ?>
</div>