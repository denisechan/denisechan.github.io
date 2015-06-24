<?php
$app = App::$instance;
$db = $app->getDb();

$lineId = $app->currentLayout->getTarget();

if (!is_numeric($lineId)) $app->redirect('home'); //Silent redirect

$line = $db->fetch('SELECT * FROM `Lineup` WHERE `ID`=:lineId LIMIT 1', array(
	':lineId' => $lineId
));

if ($line === null) $app->redirect('home'); //Silent redirect

$app->addLook('home/inspect-lineup-look.css');
$app->addDomFeel('home/inspect-lineup-feel.js');
?>
<div class="page inspect">
	<div class="content">
		
		<div class="line-data">
			<div class="map">
				<iframe src="<?php echo $line['Map_Code']; ?>" width="400" height="250" frameborder="0" style="border: 0;"></iframe>
			</div>
			<h1><?php echo $line['Name']; ?></h1>
			<p><?php echo $line['Description']; ?></p>
			<a class="join-line stripes" href="<?php wUrl("api/join-line?line={$line['ID']}"); ?>">Join this line</a>
			<a class="back" href="<?php wUrl('lineup'); ?>">Back</a>
		</div>
		
	</div>
</div>