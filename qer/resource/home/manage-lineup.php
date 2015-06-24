<?php
class LineManagerDataListener extends AjaxListener {
	public $line;
	public $user;
	
	public function LineManagerDataListener($key, $user, $line) {
		parent::AjaxListener($key);
		$this->user = $user;
		$this->line = $line;
	}
	
	public function ajaxAction($data) {
		$db = App::$instance->getDb();
		$waitData = $db->fetchAll('SELECT * FROM `Wait` WHERE `Line_Ref`=:lineId AND `Process_Time` IS NULL ORDER BY `Entry_Time` ASC', array(
			':lineId' => $this->line
		));
		
		$now = time();
		
		$userData = array();
		foreach ($waitData as $wait) {
			$user = $db->fetch('SELECT * FROM `User` WHERE `ID`=:userId', array(
				':userId' => $wait['User_Ref']
			));
			$user['Position'] = $wait['Position'];
			$user['Wait_Secs'] = $now - strtotime($wait['Entry_Time']);
			$userData[] = $user;
		}
		
		$numUsers = count($userData);
		if ($numUsers === 0) {
			$html = '<li><p>There are no users in this line</p></li>';
		} else {
			$html = '';
			foreach ($userData as $user) {
				$html .=
					'<li class="user">'.
						'<div class="data username">'.
							'<label>Username: </label><span>'.$user['User'].'</span>'.
						'</div>'.
						'<div class="data wait">'.
							'<label>Waiting for: </label><span>'.getClock($user['Wait_Secs'], 'inline white').'</span>'.
						'</div>'.
						'<a class="process stripes" href="'.getUrl("api/process-user?line={$this->line}&user={$user['ID']}").'">Process</a>'.
					'</li>';
			}
		}
		return array(
			'lineup-length' => $numUsers,
			'user-html' => $html
		);
	}
}

$app = App::$instance;
$app->addLook('home/manage-lineup-look.css');
$app->addDomFeel('home/manage-lineup-feel.js');
$db = $app->getDb();

$inner = $LAYOUT->getTarget();

//Ensure that the owner's ID is a part of the query - otherwise users could manage lines they don't own
$line = $db->fetch('SELECT * FROM `Lineup` WHERE `ID`=:lineId && `Owner_Ref`=:userId', array(
	':lineId' => $inner,
	':userId' => $app->user->getId()
));

//If the lineup doesn't exist it's possibly a malicious attempt to access an unowned lineup
if ($line === null) $app->redirect('home');

$listener = new LineManagerDataListener('line-manager-data', $app->user->getId(), $line['ID']);
$listener->process();

$waits = $db->fetchAll('SELECT * FROM `Wait` WHERE `Line_Ref`=:lineId AND `Process_Time` IS NULL ORDER BY `Entry_Time` DESC', array(
	':lineId' => $line['ID']
));

$now = time();
$userLineup = array();
foreach ($waits as &$wait) {
	$user = $db->fetch('SELECT * FROM `User` WHERE `ID`=:userId', array(
		':userId' => $wait['User_Ref']
	));
	$user['Position'] = $wait['Position'];
	$user['Wait_Secs'] = $now - strtotime($wait['Entry_Time']);
	$userLineup[] = $user;
}
usort($userLineup, function($v1, $v2) {
	return $v1['Position'] > $v2['Position'];
});
?>
<div class="page manage-lineup">
	<div class="content">
		<div class="lineup-data">
			<div class="data main">
				<label>Lineup:</label> <?php echo $line['Name']; ?>
			</div>
			<div class="data tall">
				<label>Description:</label>
				<p>
					<?php echo $line['Description']; ?>
				</p>
			</div>
			<div class="data">
				<label>Location:</label> <?php echo $line['Location']; ?>
			</div>
			<div class="data">
				<label>Created:</label> <?php echo $line['Timestamp']; ?>
			</div>
		</div>
		
		<h1>Users in this line</h1>
		<div class="users-data">
			<ol class="users">
				<?php 
				$result = $listener->ajaxAction(null); 
				echo $result['user-html'];
				?>
			</ol>
		</div>
	</div>
</div>