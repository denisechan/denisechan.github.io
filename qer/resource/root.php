<?php
$app = App::$instance;
$app->addTitle('Q\'er');
$app->addLook('look.css');
$app->addFeel('//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js');
$app->addFeel('feel.js');

$inner = $LAYOUT->getInner(); 
?>
<!DOCTYPE html>
<html>
	<head>
		<?php echo $app->getJavascriptValuesElement(); ?>
		<?php 
		$looks = $app->getLooks();
		$feels = $app->getFeels();
		foreach ($looks as $look) { ?><link rel="stylesheet" href="<?php echo $look; ?>"/><?php }
		foreach ($feels as $feel) { ?><script type="text/javascript" src="<?php echo $feel; ?>"></script><?php } ?>
		<title><?php echo $app->getTitle(); ?></title>
	</head>
	<body>
		<div id="header" class="global">
			<div class="controls left">
				<a href="<?php wUrl(''); ?>" class="control home">
					<img src="<?php wResource('_img/home.png'); ?>"/>
				</a>
			</div>
			<div class="controls right">
				<a href="<?php wUrl('settings'); ?>" class="control settings">
					<img src="<?php wResource('_img/settings.png'); ?>"/>
				</a>
			</div>
			<?php if ($app->hasParam('notify')) { ?>
			<div class="notification<?php if ($app->hasParam('err')) echo ' error'; ?> stripes">
				<p><?php echo $app->getParam('notify'); ?></p>
			</div>
			<?php } ?>
		</div>
		<div id="world" class="global">
			<?php echo $inner; ?>
		</div>
		<div id="footer" class="global">
			
		</div>
	</body>
</html>