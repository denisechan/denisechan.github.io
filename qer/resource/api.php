<?php
$app = App::$instance;
$db = $app->getDb();
$mode = $LAYOUT->getTarget();

function ajaxResponse($mode, $params, $isAjax, $app, $db) {
	if ($mode === 'get-user-line-data') {
		if (!isset($params['user']) || !isset($params['line'])) return array('code' => 1, 'msg' => 'bad parameters (missing "user" and/or "line")');
		
		$data = getLineData($params['user'], $params['line']);
		
		if ($data === null) return array('code' => 1, 'msg' => 'parameters do not identify a user in a line');
		
		return $data;
	}
	else if ($mode === 'get-user-position') {
		if (!isset($params['user']) || !isset($params['line'])) return array('code' => 1, 'msg' => 'bad parameters (missing "user" and/or "line")');
	
		$wait = $db->fetch('SELECT `Position` FROM `Wait` WHERE `User_Ref`=:user AND `Line_Ref`=:line LIMIT 1', array(
			':user' => $params['user'],
			':line' => $params['line']
		));
		
		if ($wait === null) return array('code' => 1, 'msg' => 'user '.$params['user-id'].' is not in line '.$params['line-id']);
		
		return array('position' => $wait['Position']);
	} 
	else if ($mode === 'join-line') {
		if (!isset($params['line'])) return array('code' => 1, 'msg' => 'bad parameters (missing "line")');
		
		$user = $app->user->getId();
		$line = $params['line'];
		
		if ($db->exists('Wait', array('Confirmed' => 0, 'User_Ref' => $user, 'Line_Ref' => $line))) {
			if ($isAjax) return array('code' => 1, 'msg' => 'Failure; user is already in lineup');
			$app->redirect('lineup', array('notify' => 'You\'re already in this line.', 'err' => true));
		}
		
		$db->query('INSERT INTO `Wait` SET `User_Ref`=:userId, `Line_Ref`=:lineId, `Entry_Time`=:entryTime', array(
			':userId' => $user,
			':lineId' => $line,
			':entryTime' => date('Y-m-d H:i:s')
		));
		
		$line = $db->fetch('SELECT * FROM `Lineup` WHERE `ID`=:lineId LIMIT 1', array(
			':lineId' => $line
		));
		
		if ($isAjax) return array('msg' => 'success'); 
		$app->redirect('lineup-progress/'.$line['ID'], array('notify' => 'You\'re now in line! ('.$line['Name'].')', 'split-show' => 'user'));
		
	} 
	else if ($mode === 'process-user') {
		if (!isset($params['user']) || !isset($params['line']) || !is_numeric($params['line']) || !is_numeric($params['user'])) 
			return array('code' => 1, 'msg' => 'bad "user"/"line" params (missing or invalid/non-numeric)');
		
		$user = $params['user'];
		$line = $params['line'];
		
		if (!$db->exists('Lineup', array('ID' => $line, 'Owner_Ref' => App::$instance->user->getId()))) {
			if ($isAjax) return array('code' => 1, 'msg' => 'not-correct-line-owner');
			$app->redirect('lineup'); //Silent redirect
		}
		
		if (!$db->exists('Wait', array('User_Ref' => $user, 'Line_Ref' => $line))) {
			if ($isAjax) return array('code' => 1, 'msg' => 'user/line IDs do not correspond properly');
			$app->redirect('lineup'); //Silent redirect
		}
		
		$db->query('UPDATE `Wait` SET `Process_Time`=:datetime WHERE `Process_Time` IS NULL AND `User_Ref`=:userId AND `Line_Ref`=:lineId LIMIT 1', array(
			':datetime' => date('Y-m-d H:i:s'),
			':userId' => $user,
			':lineId' => $line
		));
		
		if ($isAjax) return array('msg' => 'successfully processed client');
		$app->redirect("edit-lineup/$line", array('notify' => 'Processed client!'));
	}
	else if ($mode === 'confirm-process') {
		if (!isset($params['line']) || !is_numeric($params['line'])) return array('code' => 1, 'msg' => 'invalid/missing "line" param');
		
		$rec = $db->fetch('SELECT * FROM `Wait` WHERE `User_Ref`=:userId AND `Line_Ref`=:lineId AND `Process_Time` IS NOT NULL '.
			'ORDER BY `ID` DESC LIMIT 1', array(
			':userId' => $app->user->getId(),
			':lineId' => $params['line']
		));
		
		if ($rec === null) return array('code' => 1, 'msg' => 'non-existant record');
		
		$db->query('UPDATE `Wait` SET `Confirmed`=1 WHERE `ID`=:waitId LIMIT 1', array(
			':waitId' => $rec['ID']
		));
		
		if ($isAjax) return array('msg' => 'successfully confirmed process');
		$app->redirect('home/user', array('notify' => 'You\'ve completed the lineup!'));
	} 
	else if ($mode === 'line-data') {
		if (!isset($params['line']) || !is_numeric($params['line'])) return array('code' => 1, 'msg' => 'invalid/missing "line" param');
		if (!isset($params['user']) || !is_numeric($params['user'])) return array('code' => 1, 'msg' => 'invalid/missing "user" param');
		
		$data = lineData($params['user'], $params['line']);
		if ($data === null) return array('code' => 2, 'msg' => 'Invalid line/user credentials');
		
		$data['msg'] = 'successfully retrieved line data';
		return $data;
	}
	else if ($mode === 'leave-line') {
		if (!isset($params['line']) || !is_numeric($params['line'])) return array('code' => 1, 'msg' => 'invalid/missing "line" param');
		if (!isset($params['user']) || !is_numeric($params['user'])) return array('code' => 1, 'msg' => 'invalid/missing "user" param');
		
		$wait = $db->fetch('SELECT `ID` FROM `Wait` WHERE `Line_Ref`=:lineId AND `User_Ref`=:userId LIMIT 1', array(
			':lineId' => $params['line'],
			':userId' => $params['user']
		));
		
		if ($wait === null) return array('code' => 2, 'msg' => 'Invalid line/user credentials');
		
		$db->query('DELETE FROM `Wait` WHERE `ID`=:waitId LIMIT 1', array(
			':waitId' => $wait['ID']
		));
		
		if ($isAjax) return array('msg' => 'successfully left lineup');
		$app->redirect('home/user', array('notify' => 'You exited the line!'));
	}
	
	if ($isAjax) return array('code' => 2, 'msg' => 'Bad ajax mode: "'.$mode.'"');
	$app->redirect('', 'bad-ajax-mode');
}

$response = ajaxResponse($mode, $_REQUEST, isset($_REQUEST['ajax']), $app, $db);

header('Content-Type: application/json');
if (!isset($response['code'])) $response['code'] = 0;
echo json_encode($response);
exit;
