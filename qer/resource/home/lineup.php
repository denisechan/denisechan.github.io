<?php
$app = App::$instance;
$app->addDomFeel('_feel/search.js');
$app->addDomFeel('home/lineup-feel.js');
$app->addLook('home/lineup-look.css');

$lineups = $app->getDb()->fetchAll("SELECT * FROM `Lineup` ORDER BY `Timestamp`");
?>
<div class="page lineup">
	<div class="content">
		<div class="search"><img src="<?php wResource('_img/search.png'); ?>"/><input type="text"/></div>
		<div class="scroll">
			<ul class="lineups">
				<?php if (!empty($lineups)) { ?>
					<?php foreach ($lineups as $lineup) { ?>
					<li class="search-item lineup<?php if ($lineup['Owner_Ref'] == $app->user->getId()) echo ' owned'; ?>">
						<!--<a href="<?php wUrl("api/join-line?line={$lineup['ID']}"); ?>">
							<div class="fill"></div>
							<div class="name"><?php echo $lineup['Name']; ?></div>
						</a>-->
						<a href="<?php wUrl("inspect/{$lineup['ID']}"); ?>">
							<div class="fill"></div>
							<div class="name"><?php echo $lineup['Name']; ?></div>
						</a>
					</li>
					<?php } ?>
				<?php } else { ?>
				<p>There are no lineups to join right now.</p>
				<?php } ?>
			</ul>
		</div>
	</div>
</div>