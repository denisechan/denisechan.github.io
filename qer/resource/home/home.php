<?php
$app = App::$instance;
$app->addLook('home/home-look.css');
$app->addDomFeel('home/home-feel.js');

//The pane to show can be requested through redirect parameters...
$p = $app->getParam('split-show');
//Or the url components
if ($p === null) $p = $LAYOUT->getTarget();

//Map manager/user to left/right
if ($p === 'manager') $p = 'left';
else if ($p === 'user') $p = 'right';
else $p = null;

$ownedLineups = $app->getDb()->fetchAll('SELECT * FROM `Lineup` WHERE `Owner_Ref`=:userId', array(
	':userId' => $app->user->getId()
));
$waitLineups = $app->getDb()->fetchAll('SELECT * FROM `Lineup` WHERE EXISTS('.
	'SELECT * FROM `Wait` WHERE `Confirmed`!=1 AND `User_Ref`=:userId AND `Line_Ref`=`Lineup`.`ID`)', array(
	':userId' => $app->user->getId()
));
?>
<div class="page home">
	<div class="content">
		<div class="split<?php if ($p !== null) echo " $p"; ?> <?php echo $app->user->isUser() ? 'force-right' : 'force-left'; ?>">
			<div class="pane left manager">
				<div class="title"><h1>Manage<br/>Your<br/>Lines</h1></div>
				<div class="content">
					<div class="fill"></div>
					<div class="center">
						<h1>Manage Your<br/>Lineups</h1>
						<a class="new-line major-link stripes" href="<?php wUrl('manage/new'); ?>">Create a new line</a>
						<?php if (!empty($ownedLineups)) { ?>
							<p>Lines you already manage:</p>
							<ul class="lineups">
								<?php foreach ($ownedLineups as $lineup) { 
								$id = $lineup['ID']; 
								$title = $lineup['Name']; ?>
								<li class="lineup">
									<a class="edit-line" href="<?php wUrl("edit-lineup/$id"); ?>"><?php echo $title; ?></a>
								</li>
								<?php } ?>
							</ul>
						<?php } ?>
					</div>
				</div>
			</div>
			<div class="pane right user">
				<div class="title"><h1>Line<br/>Up</h1></div>
				<div class="content">
					<div class="fill"></div>
					<div class="center">
						<h1>Line up!</h1>
						<a class="join-line major-link stripes" href="<?php wUrl('lineup'); ?>">Join a line</a>
						<?php if (!empty($waitLineups)) { ?>
							<p>Lines you're currently waiting in:</p>
							<ul class="lineups">
								<?php foreach ($waitLineups as $lineup) { 
								$id = $lineup['ID']; 
								$title = $lineup['Name']; ?>
								<li class="lineup">
									<a class="view-line" href="<?php wUrl("lineup-progress/$id"); ?>"><?php echo $title; ?></a>
								</li>
								<?php } ?>
							</ul>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>