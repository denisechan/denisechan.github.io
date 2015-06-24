<?php
define(ROOT_DIR, '/home/skylad5/public_html/demo/qer');
define(ROOT_URL, 'demo/qer');
define(FRAMEWORK_DIR, '/home/skylad5/public_html/demo/framework');
define(FRAMEWORK_URL, 'demo/framework');
define(TIMEZONE, 'Canada/Eastern');
define(DEBUG, true);
define(ENCRYPT_KEY, 'demokey');
define(SENDER_EMAIL, 'no-reply@skyladder.net');

define(DB_NAME, 'skylad5_Qer');
define(DB_HOST, '127.0.0.1');
define(DB_USER, 'skylad5_gershom');
define(DB_PASS, base64_decode('aW1zb3NtYXJ0'));
define(DB_PORT, 3306);

//Don't change this next line - it loads the framework that will make the application work
require_once(FRAMEWORK_DIR.'/framework.php');

function getLineData($userId, $lineId) {
	$db = App::$instance->getDb();
	$wait = $db->fetch('SELECT `ID`, `Process_Time`, `Entry_Time` FROM `Wait` WHERE '.
		'`Line_Ref`=:lineId AND `User_Ref`=:userId AND '.
		'`Confirmed`=0 LIMIT 1', array(
		':lineId' => $lineId,
		':userId' => $userId
	));
	
	if ($wait === null) return null;
	
	$histLen = $db->fetch('SELECT COUNT(*) AS `Num` FROM `Wait` WHERE `Line_Ref`=:lineId', array(
		':lineId' => $lineId
	));
	
	$curLen = $db->fetch('SELECT COUNT(*) AS `Num` FROM `Wait` WHERE `Line_Ref`=:lineId AND `Process_Time` IS NULL', array(
		':lineId' => $lineId
	));
	
	$curPos = $db->fetch('SELECT COUNT(*) AS `Num` FROM `Wait` WHERE `Line_Ref`=:lineId AND `Entry_Time`<:entryTime AND `Process_Time` IS NULL', array(
		':lineId' => $lineId,
		':entryTime' => $wait['Entry_Time']
	));
	
	//Use max 10 records to estimate wait time
	$waitData = $db->fetchAll('SELECT `Process_Time`, `Entry_Time` FROM `Wait` WHERE '.
		'`Line_Ref`=:lineId AND '.
		'`Process_Time` IS NOT NULL '.
		'ORDER BY `Process_Time` DESC LIMIT 10', array(
		':lineId' => $lineId
	));
	
	$curLen = $curLen['Num'];
	$histLen = $histLen['Num'];
	$curPos = $curPos['Num'] + 1;
	$histPos = $curPos + ($histLen - $curLen);
	
	$timeEst = null;
	$len = count($waitData);
	if ($len >= 2) { //Need minimum 2 records to get a decent average
		$timeEst = 0;
		
		foreach ($waitData as $wd)
			$timeEst += (strtotime($wd['Process_Time']) - strtotime($wd['Entry_Time']));

		//0.95 instead of 1 to suggest that the estimate time is less than the actual time
		//(So that people arrive early)
		$timeEst *= 0.95 / $len;
	}
	
	return array(
		'historical-length' => $histLen,
		'historical-position' => $histPos,
		'current-length' => $curLen,
		'current-position' => $curPos,
		'entry-time' => strtotime($wait['Entry_Time']),
		'process-time' => $wait['Process_Time'],
		'wait-id' => $wait['ID'],
		'estimated-time' => $timeEst
	);
}

function getClock($time, $class = null) {
	$time -= ($day = floor($time / (24 * 60 * 60))) * (24 * 60 * 60);
	$time -= ($hr = floor($time / (60 * 60))) * (60 * 60);
	$time -= ($min = floor($time / 60)) * 60;
	$sec = (int) $time;
	
	//$time = strtotime($timestamp);
	$ret = '<div class="clock'.($class !== null ? ' '.htmlspecialchars($class) : '').'">';
	$ret .= '<span class="item'.($day == 0 ? ' gone' : '').'"><span class="day">'. 	$day. 	'</span>D,</span>';
	$ret .= '<span class="item'.( $hr == 0 ? ' gone' : '').'"><span class="hr">'. 	$hr. 	'</span>h</span>';
	$ret .= '<span class="item'.($min == 0 ? ' gone' : '').'"><span class="min">'. 	$min. 	'</span>m</span>';
	$ret .= '<span class="item'.($sec == 0 ? ' gone' : '').'"><span class="sec">'. 	$sec. 	'</span>s</span>';
	$ret .= '</div>';
	
	return $ret;
}

function wClock($time, $class = null) {
	echo getClock($time, $class);
}

function amt($str, $num) {
	$data = array(
		'is' => 'are',
		'person' => 'people'
	);
	
	if (!isset($data[$str])) return $num == 1 ? $str : $str.'s';
	
	return $num == 1 ? $str : $data[$str];
}

class QerApp extends App {
	
	public function QerApp($key) {
		parent::App($key);
	}
	
	public function generateUser() {
		$data = $this->getUserData();
		return empty($data) || !isset($data['id'])
			? null 
			: new QerUser($data['id']);
	}
}

class QerUser extends User {
	
	public $data;
	
	public function QerUser($id) {
		parent::User();
		$this->data = App::$instance->getDb()->fetch("SELECT * FROM `User` WHERE `ID`=:id", array(
			':id' => $id
		));
	}
	
	public function logOut() {
		
	}
	
	public function getId() {
		return $this->data['ID'];
	}
	
	public function getUsername() {
		return $this->data['User'];
	}
	
	public function getEmail() {
		return $this->data['Email'];
	}

	public function isUser() {
		return $this->data['Type'] === 'user';
	}
	
	public function isManager() {
		return $this->data['Type'] === 'manager';
	}
}