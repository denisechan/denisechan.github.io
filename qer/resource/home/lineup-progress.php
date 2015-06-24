<?php
class LineDataListener extends AjaxListener {
	
	public $user;
	public $line;
	
	public function LineDataListener($key, $user, $line) {
		parent::AjaxListener($key);
		$this->user = $user;
		$this->line = $line;
	}
	
	public function ajaxAction($p) {
		$data = getLineData($this->user, $this->line);
		if ($data === null) return array('code' => 2, 'msg' => 'Invalid line/user credentials');
		
		$data['msg'] = 'successfully retrieved line data';
		
		$waitTime = time() - $data['entry-time'];
		$data['wait-clock'] = getClock($waitTime, 'wait-time');
		
		$data['estimate-clock'] = $data['estimated-time'] !== null
			? getClock($data['estimated-time'] - $waitTime, 'down')
			: '<p>Any moment now!</p>';
		
		$len = $data['current-length'];
		$data['line-length'] = 'There '.amt('is', $len).' <span class="length">'.$len.'</span> '.amt('person', $len).' in line.'.($len === 1 ? ' (It\'s you!)' : '').'</span>';
		
		$pos = $data['current-position'];
		$ahead = $pos - 1;
		$data['line-ahead'] = $ahead === 0
			? 'You\'re next!'
			: '('.$ahead.' '.amt('person', $ahead).' ahead of you)';
		
		$procTime = $data['process-time'];
		$data['process-content'] = $procTime === null 
			?	null
			:	'<h1>You\'re up!</h1>'.
				'<a href="'.getUrl('api/confirm-process?line='.$this->line).'" class="continue stripes" style="opacity: 0;">I\'m done!</a>';
		
		return $data;
	}
}

$app = App::$instance;

$db = $app->getDb();
$line = $app->currentLayout->getTarget();

//Potential malicious attempt
if (!is_numeric($line)) $app->redirect('home');

$listener = new LineDataListener('line-data', $app->user->getId(), $line);
$listener->process();

$line = $db->fetch('SELECT * FROM `Lineup` WHERE `ID`=:lineId AND EXISTS('.
	'SELECT * FROM `Wait` WHERE `Line_Ref`=`Lineup`.`ID` AND `User_Ref`=:userId) '.
	'LIMIT 1', array(
	':lineId' => $line,
	':userId' => $app->user->getId()
));

//Potential malicious attempt (access un-owned line)
if ($line === null) $app->redirect('home');

$app->addLook('home/lineup-progress-look.css');
$app->addDomFeel('home/lineup-progress-feel.js');
$app->setJavascriptValue('line', $line['ID']);
$app->setJavascriptValue('user', $app->user->getId());
?>
<div class="page lineup-progress">
	<div class="content">
		<div class="fill"></div>
		<?php
		$lineData = $listener->ajaxAction(null);
		$processed = !empty($lineData['process-content']);
		$now = time();
		?>
		<div class="ticket-body <?php if ($processed) echo 'processed'; ?>">
			<div class="item wait-number"><?php echo $lineData['historical-position']; ?></div>
			You've waited:
			<div class="item wait-time"><?php echo $lineData['wait-clock']; ?></div>
			<div class="unprocessed">
				Estimated wait time:
				<div class="item estimate-time"><?php echo $lineData['estimate-clock']; ?></div>
				<div class="item line-length"><?php echo $lineData['line-length']; ?></div>
				<div class="item line-ahead"><?php echo $lineData['line-ahead']; ?></div>
			</div>
			<div class="processed">
				<?php if ($processed) { echo $lineData['process-content']; } ?>
			</div>
		</div>
	</div>
	<div class="lineup-controls controls">
		<a class="control" href="<?php wUrl('home/user'); ?>">Back</a>
		<a class="control" href="<?php wUrl('api/leave-line?user='.$app->user->getId().'&line='.$line['ID']); ?>">Leave this line</a>
	</div>
</div>