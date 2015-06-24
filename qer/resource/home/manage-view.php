<?php
$app = App::$instance;
$db = $app->getDb();

$inner = $LAYOUT->getInner();

$lineups = $db->fetchAll("SELECT * FROM `Lineup` WHERE `Owner_Ref`=:owner", array(
	':owner' => $app->user->getId()
));
?>
<div class="page view-lineups">
	<?php if ($inner !== null) { echo $inner; } ?>
	<div class="content">
		<?php if ($app->hasParam('new-lineup')) { ?>
		<p>Lineup "<?php echo $app->getParam('new-lineup'); ?>" successfully created!</p>
		<?php } ?>
		<h1>Lineups you manage:</h1>
		<ul class="lineups">
			<?php foreach ($lineups as $lineup) { ?>
			<li class="lineup">
				<div class="name">Name: <?php echo $lineup['Name']; ?></div>
				<div class="desc">Description: <?php echo $lineup['Description']; ?></div>
				<div class="loc">Location: <?php echo $lineup['Location']; ?></div>
				<div class="controls">
					<div class="control">
						<a href="<?php echo getUrl("edit-lineup/{$lineup['ID']}"); ?>">Manage</a>
					</div>
				</div>
			</li>
			<?php } ?>
		</ul>
		<a href="<?php echo getUrl('manage'); ?>">Back</a>
	</div>
</div>