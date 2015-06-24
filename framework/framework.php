<?php
//==================================================================INITIALIZATION
/*
Procedural initialization stuff
In debug mode error reporting is enabled and functions are set up to catch any uncaught exceptions.
You may notice that register_shutdown_function is also used - it serves the purpose of catching
uncatchable fatal Errors (not the same as Exceptions)
*/
if (DEBUG) { 
	ini_set('display_errors', true);
	error_reporting(E_ALL); 
	
	
	function handleException($ex) {
		if ($ex instanceof Exception) {
			$msg = $ex->getMessage();
			$trace = $ex->getTrace();
		} else if (is_array($ex)) {
			$msg = $ex['message'];
			$trace = $ex['trace'];
		}
		
		$results = array();
		if (preg_match('~^(.+), called in (/(?:.+)\.php) on line ([0-9]+)~', $msg, $results)) {
			$msg = arrVal($results, 1, $msg);
			$file = arrVal($results, 2, 'unknown file');
			$line = arrVal($results, 3, 'unknown line');
			
			array_unshift($trace, array('file' => $file, 'line' => $line, 'function' => 'function'));
		} else if (is_array($ex)) {
			$file = arrVal($ex, 'file', 'unknown file');
			$line = arrVal($ex, 'line', 'unknown line');
			
			array_unshift($trace, array('file' => $file, 'line' => $line, 'function' => 'function'));
		}
		
		echo '<div style="font-size: 24px; color: #cc0000; margin-bottom: 10px;">Error: '.$msg.'</div>';
		echo '<div style="font-size: 20px;">';
		
		$count = 100;
		foreach ($trace as $frame) {
			echo '<div style="font-size: '.$count.'%;">';
			echo exceptionFrameHtml($frame);
			echo '</div>';
			$count = max(30, $count - 5);
		}
		
		echo '</div>';
		exit;
	};
	set_exception_handler('handleException');
	set_error_handler(function($num, $str, $file, $line) {
		if (DEBUG) throw new ErrorException($str, $num, 0, $file, $line);
		exit;
	});
	register_shutdown_function(function() {
		$err = error_get_last();
		if (!empty($err) && $err['type'] === E_ERROR) {
			//ob_end_clean();
			$err['trace'] = array();
			handleException($err);
		}
	});
}

//Set the timezone and start the session.
date_default_timezone_set(TIMEZONE);
session_start();

//==================================================================UTILITY FUNCTIONS
require_once('functions.php');

//==================================================================AJAX INTERFACE
/*
A class to maintain the state of a server-side value while providing a lightweight interface to
the client side for updating this value.
*/
abstract class ClientValue {
	
	public $processed = false;
	public $key;
	
	public function ClientValue($key) {
		$this->key = $key;
	}
	
	public abstract function needsProcess();
	
	public abstract function action($data);
	
	/*
	Default ClientValue data is considered to be the $_POST array.
	*/
	public function getRelevantData() {
		return $_POST;
	}
	
	public function process() {
		if ($this->processed) {
			if (DEBUG) throw new Exception('Processed ClientValue twice: '.$this->key);
			return;
		}
		$this->processed = true;
		if ($this->needsProcess()) $this->action($this->getRelevantData());
	}

	public function setData($val) {
		App::$instance->session['_priv'][$this->key] = $val;
	}
	
	public function getData() {
		return arrVal(App::$instance->session['_priv'], $this->key, null);
	}
	
	public function hasData() {
		return isset(App::$instance->session['_priv'][$this->key]);
	}
	
	public function remData() {
		unset(App::$instance->session['_priv'][$this->key]);
	}
}

/*
Class that listens (and terminates ordinary page display) if a particular ajax value is request
from the client side. 
*/
abstract class AjaxListener extends ClientValue {
	
	public function AjaxListener($key) {
		parent::ClientValue($key);
	}
	
	public function needsProcess() {
		return isset($_POST['ajax']) && $_POST['ajax'] === $this->key;
	}
	
	//Returns the $_POST array, without the 'ajax' key (which can be effectively be hidden from the user)
	public function getRelevantData() {
		$ret = $_POST; unset($ret['ajax']);
		return $ret;
	}

	//The action that this AjaxValue takes when it is triggered; parameters appear as the $data parameter
	abstract function ajaxAction($data);
	
	/*
		An AjaxValue object's action is to respond to an ajax request from the client side, and return
		a response to the client; AjaxValue->action($data) sets up the Ajax environment - declares the
		Content-Type of the HTTP header to be application/ajax, and encodes the subclasses' response
		into json format before outputting it.
		
		This function exits the current application run because an ajax response has been achieved and
		that is all the client side needs to receive in this case.
	 */
	public function action($data) {
		//The subclasses' response to the request described by $data
		$response = $this->ajaxAction($data);
		
		//Do some structural checking on $data before outputting the json:
		
		//Ensure empty($response) !== true
		if (empty($response)) $response = array();
		//Ensure $response is an array
		if (!is_array($response)) $response = array('msg' => $response); 	
		//Ensure $response has a 'code' index
		if (!isset($response['code'])) $response['code'] = 0;
		//If $response's 'code' index indicates an error, ensure there is an 'err' index as well
		if ($response['code'] !== 0 && !isset($response['err'])) $response['err'] = 'unknown error';
		//If a $response has an error, include the ajax identifier for this object in the message
		if (isset($response['err'])) {
			if (!is_array($response['err'])) $response['err'] = array('msg' => $response['err']);
			$response['err']['ajax-id'] = $this->key;
		}
		
		//Generate HTTP header, return the response in json format
		header('Content-Type: application/json');
		exit(json_encode($response));
	}
}

/*
Class to maintain the state of a value on the server-side.
Also specifies a default value for this variable in the case that it has not yet been initialized
by an explicit call
*/
abstract class AjaxValue extends AjaxListener {
	
	public $initialValue = null;
	
	public function AjaxValue($key) {
		parent::ClientValue($key);
		$this->initialValue = $this->defaultValue();
	}
	
	//The default value an AjaxValue object should start with if none has been sent yet
	abstract function defaultValue();
	
	//Extend the ClientValue::process() function - set a default value for the AjaxValue if none is set
	public function process() {
		if (!$this->hasData()) $this->setData($this->initialValue);
		parent::process();
	}

	public function value() {
		return $this->getData();
	}
}

class BooleanAjaxValue extends AjaxValue {
	
	public function BooleanAjaxValue($key) {
		parent::AjaxValue($key);
	}
	
	public function defaultValue() {
		return false;
	}
	
	public function ajaxAction($data) {
		//A value parameter is required
		if (!isset($data['value'])) return array('code' => 1, 'err' => 'missing "value" param');
		
		//Get the value
		$val = $data['value'];
		
		//Resolve $val to a boolean (casting alone doesn't work, need to check for strings 'true' and 'false')
		//Also, note the "==" instead of "===" for checking numeric values
		if 		($val === 'true'  || $val == 1) $val = true;
		else if ($val === 'false' || $val == 0) $val = false;
		else return array('code' => 2, 'err' => array('msg' => 'invalid boolean value', 'value' => $val));
			
		$this->setData($val);
		return 'success';
	}
	
	public function on() {
		return $this->getData();
	}
}

class IntegerAjaxValue extends AjaxValue {
	
	public function IntegerAjaxValue($key) {
		parent::AjaxValue($key);
	}
	
	public function defaultValue() {
		return 0;
	}
	
	public function ajaxAction($data) {
		//A value parameter is required
		if (!isset($data['value'])) return array('code' => 1, 'err' => 'missing "value" param');
		
		$this->setData((int) $data['value']);
	}
}

class StringAjaxValue extends AjaxValue {
	
	public function StringAjaxValue($key) {
		parent::AjaxValue($key);
	}
	
	public function defaultValue() {
		return '';
	}
	
	public function ajaxAction($data) {
		//A value parameter is required
		if (!isset($data['value'])) return array('code' => 1, 'err' => 'missing "value" param');
		
		$this->setData((string) $data['value']);
	}
}

//==================================================================UTILITY
class Range {
	public $min;
	public $max;
	
	public function Range($min, $max) {
		$this->min = $min;
		$this->max = $max;
	}
	
	/*
	Returns whether $num is within this range
	*/
	public function inRange($num) {
		return $num <= $this->max && $num >= $this->min;
	}
	
	/*
	Returns -1 if $num is below the range, 1 if $num is above the 
	range, and 0 if num is within the range
	*/
	public function validity($num) {
		return $num > $this->max ? 1 
			: ($num < $this->min ? -1 : 0);
	}

	public function duplicate() {
		return new Range($this->min, $this->max);
	}
}

class TreeNode {
	public $value;
	public $subTree;
	
	public function TreeNode($value = null, $subTree = array()) {
		$this->value = $value;
		$this->subTree = $subTree;
	}
	
	public function isLeaf() {
		return empty($this->subTree);
	}
	
	/*
	*/
	public function hasNode($k) {
		return isset($this->subTree[$k]);
	}
	
	/*
	Return the node under key $k.
	If no node exists at index $k a new TreeNode object is created (with $value === null),
	add to $this TreeNode, and returned.
	
	This method always returns a TreeNode.
	*/
	public function getNode($k) {
		if (!$this->hasNode($k)) return $this->addNode($k, null);
		return $this->subTree[$k];
	}
	
	public function remNode($k) {
		unset($this->subTree[$k]);
	}
	
	//Add a node under key $k with value $v
	public function addNode($k, $v = null) {
		if (!($v instanceof TreeNode)) $v = new TreeNode($v);
		$this->subTree[$k] = $v;
		return $v;
	}
	
	//Flattens a recursive TreeNode structure into a linear array (breadth-first)
	public function flatten($key = 0) {
		$arr = array();
		$arr[$key] = $this->value;
		foreach ($this->subTree as $k => $node) {
			$inner = $node->flatten($k);
			$arr = array_merge($arr, $inner);
		}
		return $arr;
	}
	
	public function toArray($keyPath = array(), $includeParent = false) {
		//Allow $keyPath to be passed as a string (representing the first key in a key-path)
		if ($keyPath !== null && !is_array($keyPath)) $keyPath = array($keyPath);
		
		$ret = array();
		
		//Add $this to the return array
		if ($includeParent) {
			if ($keyPath !== null)	$ret[implode('/', $keyPath)] = $this->value;
			else					$ret[] = $this->value;
		}
		
		//Add all nodes underneath $this to the return array
		foreach ($this->subTree as $key => $node) {
			$newKeyPath = $keyPath;
			//If leaves are meant to be crawlable, the current key must be passed on to the inner nodes
			if ($keyPath !== null) $newKeyPath[] = $key; //Take advantage of copy-by-value here
			
			$innerLeaves = $node->toArray($newKeyPath, true);
			$ret = array_merge($ret, $innerLeaves);
		}
		return $ret;
	}

	public function filter($func) {
		$ret = new TreeNode($this->value);
		foreach ($this->subTree as $k => $node) {
			if ($func($node->value)) $ret->subTree[$k] = $node->filter($func);
		}
			//if ($func($node->value)) $ret->subTree[$k] = $node->filter($func);
		return $ret;
	}
	
	public function map($func) {
		$ret = new TreeNode($func($this->value));
		foreach($this->subTree as $k => $node) $ret->subTree[$k] = $node->map($func);
		return $ret;
	}
	
	public function search($func, $max = null, &$ret = array()) {
		foreach ($this->subTree as $node) {
			if ($func($node->value)) {
				$ret[] = $node->value;
				if ($max !== null && count($ret) >= $max) break;
			}
			$node->search($func, $max, $ret);
		}
		return $ret;
	}
	
	//"Crawl" through the TreeNode with an array of keys
	public function crawl($keys) {
		if (is_string($keys)) $keys = explode('/', $keys);
		$node = $this;
		foreach ($keys as $key) {
			if ($node->hasNode($key)) $node = $node->getNode($key);
			else return null;
		}
		return $node->value;
	}
	
	/*
	$keyPath should never be specified when this function is called 
	*/
	public function getLeaves($keyPath = array()) {
		//Allow $keyPath to be passed as a string (representing the first key in a key-path)
		if ($keyPath !== null && !is_array($keyPath)) $keyPath = array($keyPath);
		
		$ret = array();
		if ($this->isLeaf()) {
			//Do crawlable/associative indexing if crawlableLeaves are requested
			if ($keyPath !== null)	$ret[implode('/', $keyPath)] = $this->value;
			//Do numeric indexing otherwise
			else					$ret[] = $this->value;
		} else {
			foreach ($this->subTree as $key => $node) {
				$newKeyPath = $keyPath;
				//If leaves are meant to be crawlable, the current key must be passed on to the inner nodes
				if ($keyPath !== null) $newKeyPath[] = $key; //Take advantage of copy-by-value here
				
				$innerLeaves = $node->getLeaves($newKeyPath);
				$ret = array_merge($ret, $innerLeaves);
			}
		}
		return $ret;
	}
}

//==================================================================EMAILS
class EmailSender {
	
	public $sender;
	
	public function EmailSender($sender) {
		$this->sender = $sender;
	}
	
	public function send($recipient, $subject, $message) {
		$headers = 
			"MIME-Version: 1.0\r\n".
			"From: {$this->sender}\n".
			"Reply-to: {$this->sender}\n".
			"Return-path: {$this->sender}\n".
			"X-Priority: 1\r\n".
			"Content-Type: text/plain; charset=ISO-8859-1\r\n".
			"Content-Transfer-Encoding: base64\r\n";
		
		$result = mail($recipient, $subject, base64_encode($message), $headers);
		if (DEBUG && !$result) throw new Exception("Mail function failed\r\nSender: {$this->sender}\r\nRecipient: $recipient;\r\nSubject: \"$subject\"");
		return $result;
	}
}

//==================================================================DATABASE
require_once('db-model.php');

class DB {
	private $con;
	
	public static function dateTime() {
		return date('Y-m-d H:i:s');
	}
	
	public function DB($host, $db, $user, $pass, $port = 3306) {
		$this->con = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
		$this->con->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
		$this->con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	
	public function getPDO() {
		return $this->con;
	}
	
	public function query($sql, $params = array()) {
		$query = $this->con->prepare((string) $sql);
		$query->execute($params);
		
		return strlen($sql) > 6 
			&& strtoupper(substr($sql, 0, 6)) === 'INSERT' ?
			$this->con->lastInsertId() : $query;
	}
	
	public function parseSqlForTable($sql) {
		//Search for a backtick-enclosed tablename first...
		$matches = array();
		if (!preg_match('~ FROM `([a-zA-Z_-]+)`~', $sql, $matches)) {
			//If not found search for an unenclosed tablename
			$matches = array();
			if (!preg_match('~ FROM ([^` ]+) ~', $sql, $matches)) return null;
		}
		return isset($matches[1]) ? $matches[1] : null;
	}
	
	public function fetch($sql, $params = array()) {
		if (DEBUG) {
			$table = $this->parseSqlForTable($sql);
			if (!$this->tableExists($table)) return null;
		}
		$ret = $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
		return !empty($ret) ? $ret : null;
	}
	
	public function fetchAll($sql, $params = array()) {
		if (DEBUG) {
			$table = $this->parseSqlForTable($sql);
			if ($table !== null && !$this->tableExists($table)) return array();
		}
		return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
	}

	public function tableExists($table) {
		try {
			return $this->con->query("DESCRIBE `$table`") ? true : false;
		} catch (Exception $e) {
			return false;
		}
	}
	
	public function exists($table, $params) {
		if (DEBUG && !$this->tableExists($table)) return false;
		
		//exit(json_encode(array('here' => $table, 'params' => $params)));
		
		$fieldMatches = array();
		foreach ($params as $k => $v) $fieldMatches[] = "`$k`=?";
		$query = "SELECT 1 FROM `$table` WHERE ".implode(' AND ', $fieldMatches);
		$result = $this->fetch($query, array_values($params));
		return $result !== null;
	}
	
	public function quote($val) {
		return $this->con->quote($val);
	}
	
	public function escape($val) {
		return substr($this->quote($val), 1, -1);
	}
}

//==================================================================FORMS, INPUTS
require_once('forms.php');

//==================================================================USER STUFF
abstract class User {
	public function User() {
	}
	
	abstract function logOut();
}

//==================================================================DATA OUTPUT
abstract class OutputData {
	private static $modes = array('show' => 'inline', 'download' => 'attachment');
	
	public $serveMode = 'download';
	
	public $data;
	
	public function OutputData($data) {
		//Ensure we at least have workable (array) data
		if (!is_array($data)) errorReport('Tried to instantiate OutputData with non-array data', $data);
		//Ensure the array is 2D
		if (!is_array(reset($data))) $data = array($data);
		
		$this->data = $data;
	}
	
	public abstract function toString();
	
	public abstract function getHeaders();
	
	public abstract function extension();
	
	public function setServeMode($mode) {
		if (!isset(OutputData::$modes[$mode])) errorReport('Cannot set serve mode to '.$mode.'; must be one of: '.implode(', ', array_keys(PDFData::$modes)));
		$this->serveMode = $mode;
	}
	
	public function getFilename($term) {
		$ext = $this->extension();
		return substr($term, -(strlen($ext) + 1)) !== ".$ext" ? "$term.$ext" : $term;
	}
	
	public function download($filename = 'download') {
		$filename = $this->getFilename($filename);
		$headers = $this->getHeaders();
		$headers['Content-Disposition'] = OutputData::$modes[$this->serveMode].'; filename="'.$filename.'"';
		foreach ($headers as $k => $v) header("$k: $v");
		echo $this->toString();
		exit;
	}
	
	public function save($filename) {
		$filename = $this->getFilename($filename);
		$file = fopen($filename, 'wb');
		if (!$file) errorReport('Failed to create output file: '.$filename);
		$str = $this->toString();
		fwrite($file, $str, strlen($str));
		fclose($file);
	}
}

class PDFData extends OutputData {
	private $pageW;
	private $pageH;
	
	public $title = 'Data';
	public $rowTitle = 'Row';
	
	public $header1Height = 20;
	public $header2Height = 12;
	public $valueHeight = 5;
	
	public function PDFData($data) {
		parent::OutputData($data);
	}
	
	public function extension() {
		return 'pdf';
	}
	
	public function getHeaders() {
		return array(
			'Pragma' => 'public',
			'Content-Type' => 'application/pdf',
			'Cache-Control' => 'private, max-age=0, must-revalidate',
			
			'Content-Transfer-Encoding' => 'binary'
		);
	}
	
	private function writeHeader1($pdf, $str) {
		$pdf->SetFont('Helvetica', 'B', 20);
		$pdf->SetTextColor(100, 100, 150);
		$pdf->Cell($this->pageW, $this->header1Height, $str, 1, 1, 'C');
	}
	
	private function writeHeader2($pdf, $str) {
		$pdf->SetFont('Helvetica', 'B', 16);
		$pdf->SetTextColor(100, 100, 150);
		$pdf->Cell($this->pageW, $this->header2Height, $str, 0, 1, 'L');
	}
	
	private function writeField($pdf, $key, $val) {
		$kw = $this->pageW * 0.25;
		$vw = $this->pageW - $kw;
		
		$val = $this->breakString($pdf, $val, $vw);
		foreach ($val as $k => $v) {
			//if ($k > 2) break;
			//Draw a key cell (for EVERY row! Because the cell needs to push the value cell forward
			$pdf->SetTextColor(120, 120, 200);
			$pdf->setFont('Helvetica', 'B', 12);
			$pdf->Cell($kw, $this->valueHeight, $k === 0 ? $key : '', 0, 0, 'L');
			
			//Draw a value cell to the left of the key cell
			$pdf->SetTextColor(0, 0, 1);
			$pdf->setFont('Helvetica', '', 12);
			$pdf->Cell($vw, $this->valueHeight, $v, 0, 1, 'L');
		}
	}
	
	private function writeRow($pdf, $title, $row) {
		$this->writeHeader2($pdf, $title);
		foreach ($row as $k => $v) $this->writeField($pdf, $k, $v);
	}
	
	private function headerHeight($header) {
		return $this->header1Height;
	}
	
	private function rowHeight($pdf, $row) {
		//Only here to be informative, plus $vw is used for $this->breakString($v)
		$kw = $this->pageW * 0.25;
		$vw = $this->pageW - $kw;
		
		$valLines = 0;
		foreach ($row as $val) $valLines += count($this->breakString($pdf, $val, $vw)) + 1;
		return $this->header2Height + ($valLines * $this->valueHeight);
	}
	
	/*
	Some strings are too long to fit in a fixed amount of horizontal space!
	This function inserts line breaks appropriately into a string until it fits properly.
	*/
	private function breakString($pdf, $str, $maxW) {
		//Only here to be informative, plus $vw is used for $this->breakString($v)
		$kw = $this->pageW * 0.25;
		$vw = $this->pageW - $kw;
		
		$pcs = is_array($str) ? $str : explode("\n", $str);
		$newPcs = array();
		
		foreach ($pcs as $str) {
			$len = $pdf->GetStringWidth($str);
			if ($len <= $maxW) {	//If the string fits on one line
				$newPcs[] = $str;
			} else {			//Otherwise, add newlines.
				$words = explode(' ', $str);
				
				//While another word can be added onto the line without exceeding the width, add another word.
				$maxWords = 0;	//This is the number
				while ($pdf->GetStringWidth(implode(' ', array_slice($words, 0, $maxWords + 1))) <= $maxW) {
					$maxWords++;
				}
				
				$newPcs[] = array_slice($words, 0, $maxWords);
				$newPcs = array_merge($newPcs, $this->breakString($pdf, array(array_slice($words, $maxWords)), $vw));
			}
		}
		return $newPcs;
	}
	
	public function toString() {
		require_once('fpdf/fpdf.php');
		
		$pdf = new FPDF();
		$this->pageW = $pdf->w - ($pdf->lMargin + $pdf->rMargin);
		$this->pageH = $pdf->h - ($pdf->tMargin + $pdf->bMargin);
		$pdf->AddPage('P'); 
		
		$this->writeHeader1($pdf, $this->title);
		$count = 1;
		foreach ($this->data as $data) {
			//Ensure enough height is remaining on the current page to display the next row
			$curY = $pdf->y;
			$remaining = $this->pageH - $curY;
			$dataHeight = $this->rowHeight($pdf, $data);
			
			//Definition of a "large" item: longer than 30% of a page
			$largeItem = $dataHeight > $this->pageH * 0.3;	
			//Definition of "minimum height": The height of a row header and 3 rows
			$minimumFits = $remaining > $this->header2Height + ($this->valueHeight * 3);
			
			/*
			This if says to break to a new page in 2 cases.
			1) The "minimum" amount doesn't fit - this means that there isn't enough space for
				a row-header with 3 rows to fit.
			2) The item is "small", and there isn't enough room on the page to fit the entire 
				item.
			*/
			if (!$minimumFits || (!$largeItem && $remaining < $dataHeight)) { $pdf->AddPage('P'); }
			
			$this->writeRow($pdf, $this->rowTitle.' '.($count++), $data);
		}
		
		return $pdf->Output('', 'S');
	}
}

class CSVData extends OutputData {
	
	public function CSVData($data) {
		parent::OutputData($data);
	}
	
	public function extension() {
		return 'csv';
	}
	
	public function getHeaders() {
		return array(
			'Pragma' => 'public',
			'Content-Type' => 'application/octet-stream',
			'Cache-Control' => 'private, max-age=0, must-revalidate',
		);
	}
	
	public function toString() {
		$data = $this->data;
		array_unshift($data, array_keys($data[0]));
		
		$rows = array();
		foreach ($data as $rowData) {
			$row = array();
			foreach ($rowData as $item) {
				//Replace any double-quotes with double-double-quotes (lol)
				$row[] = '"'.str_replace('"', '""', $item).'"';
			}
			//Implode the row items together with commas
			$rows[] = implode(',', $row);
		}
		//Implode the lines together with line-feeds
		return implode("\n", $rows);
	}
}

//==================================================================STYLERS
abstract class Styler {
	
	private $form;
	
	public function Styler($form) {
		$this->form = $form;
	}
	
	public function render($label, $inp, $p1 = null, $p2 = null, $p3 = null, $p4 = null) {
		if (is_string($inp)) $inp = $this->form->crawl($inp);
		if ($inp === null) {
			if (DEBUG) throw new Exception('Input under key '.$key.' doesn\'t exist');
		} else {
			$this->doRender($label, $inp, $p1, $p2, $p3, $p4);
		}
	}
	
	public abstract function doRender($label, $inp, $p1 = null, $p2 = null, $p3 = null, $p4 = null);
}

//==================================================================LAYOUT, PAGES, APPLICATION
class Layout {
	private static $buffering;
	
	public $filename;
	public $layouts;
	private $defaultPage;
	public $target;
	public $targetUrl;
	
	public function Layout($filename, $defaultPage = null, $layouts = array()) {
		$this->filename = $filename;
		$this->defaultPage = $defaultPage;
		$this->layouts = $layouts;
	}
	
	public function addLayout($key, $layout) {
		$this->layouts[$key] = $layout;
	}
	
	public function getDefaultPage() {
		return $this->defaultPage;
	}
	
	public function go($urlPcs) {
		$this->targetUrl = $urlPcs;
		$this->target = count($this->targetUrl) > 0 ? array_shift($this->targetUrl) : null;
		if ($this->target === null || !$this->hasLayoutFor($this->target)) {
			$def = $this->getDefaultPage();
			if ($def !== null) {
				$this->targetUrl = $urlPcs;
				$this->target = $def;
			}
		}
		
		//The default key is used to locate an inner layout when the next-url is empty
		// if (empty($urlPcs)) {
			// $def = $this->getDefaultPage();
			// if ($def !== null) $urlPcs = array($def);
		// }
		
		
		$file = getFile("resource/{$this->filename}");
		if (DEBUG && !file_exists($file)) return debugHtml('<p>Add the following file to the "resource" directory:<br/><b>'.$this->filename.'</b></p>');
		
		ob_start();					//Buffer start
		
		$LAYOUT = $this;
		App::$instance->currentLayout = $this;
		require($file);
		
		$ret = ob_get_contents();
		ob_end_clean();				//Buffer end
		
		return $ret;
	}
	
	public function getInner() {
		if (empty($this->target)) return null;
		
		if (!$this->hasLayoutFor($this->target)) return $this->getInvalidContent();
		
		$innerLayout = $this->getLayoutFor($this->target);
		
		return $innerLayout === null ? null : $innerLayout->go($this->targetUrl);
	}
	
	public function hasWildcard($target) { 
		return array_key_exists('*', $this->layouts);
	}
	
	public function hasLayoutFor($target) {
		return isset($this->layouts[$target]) || $this->hasWildcard($target);
	}
	
	public function getLayoutFor($target) {
		if (isset($this->layouts[$target])) return $this->layouts[$target];
		if ($this->hasWildcard($target)) return $this->layouts['*'];
		return null;
	}
	
	public function getInvalidContent() {
		return 'Unfortunately this page does not exist.';
	}
	
	public function getFilename() {
		return $this->filename;
	}
	
	public function getUrl() {
		$url = $this->targetUrl;
		$url[] = $this->target;
		return implode('/', $url);
	}
	
	public function getTarget() {
		return $this->target;
	}
}

class LoggedLayout extends Layout {
	
	public $defaultLoggedPage;
	
	public function LoggedLayout($filename, $defaultPage = null, $defaultLoggedPage = null, $layouts = array()) {
		parent::Layout($filename, $defaultPage, $layouts);
		$this->defaultLoggedPage = $defaultLoggedPage;
	}
	
	public function getDefaultPage() {
		return App::$instance->isLoggedIn() ? $this->defaultLoggedPage : parent::getDefaultPage();
	}

	public function getLayoutFor($target) {
		$ret = parent::getLayoutFor($target);
		if ($ret instanceof LoggedInLayout && !App::$instance->isLoggedIn()) return null;
		return $ret;
	}
}

class LoggedInLayout extends Layout {
	public function LoggedInLayout($filename, $defaultPage = null, $layouts = array()) {
		parent::Layout($filename, $defaultPage, $layouts);
	}
}

class App {
	
	//Static reference to the App
	public static $instance;
	
	//Unique name identifier for the App. No two Apps on the same server should have the same name
	public $name = null;
	
	//State variables
	public $url = null;		//Current url (array-format)
	public $file = null;	//Current filename
	public $layout = null;	//Layout returned by the layout.php file
	public $currentLayout = null;	//The layout generating the current page
	
	//Database reference
	public $db = null;
	
	//Email sender reference
	public $emailSender = null;
	
	//The user object
	public $user = null;
	
	//Presentation variables
	public $lookFiles = array();		//List of css files
	public $feelFilesDom = array();		//List of js files that modify the DOM
	public $feelFilesNoDom = array();	//LIst of js files that don't affect the DOM
	public $jsValues = array();
	public $titles = array();			//List of title components
	public $stdResources = true;		//True indicates to include standard css/js from the framework URL
	
	//True indicates that final output should have unnecessary whitespace stripped
	public $compressOutput = true;
	
	//Reference to application-protected session object
	public $session = array();
	
	//Parameters that should be present on the page the app redirects to
	public $redirParams = array();
	
	public function App($name) {
		App::$instance = $this;
		
		//Give the app its' unique name
		$this->name = $name;
		
		//Parse the URL the htaccess file has formulated
		$this->url = explode('/', $_GET['_requrl']);
		$this->file = strpos(end($this->url), '.') 
			? array_pop($this->url) 
			: 'index.php';
		
		//Get the layout file that will be used to associate URLs with their pages
		$this->layout = require_once(getFile('layout.php'));
		
		//Initialize a private index in the session array if there isn't one already
		if (!isset($_SESSION['fw-'.$this->name])) $_SESSION['fw-'.$this->name] = array();
		
		//Link to the session array through a reference variable
		$this->session =& $_SESSION['fw-'.$this->name];
		
		//Initialize the framework session array with some default values (on the 1st run)
		if (!isset($this->session['_priv'])) {
			$this->session['_priv'] = array();
			$this->session['_form'] = array();
			$this->session['_user'] = array();
		}
		
		//Initialize any redirect parameters
		if (isset($this->session['_priv']['redir'])) {
			$this->redirParams = $this->session['_priv']['redir'];
			unset($this->session['_priv']['redir']);
		}
		
		//Try to initialize the user
		$this->user = $this->generateUser();
		
		//Require default resources from the framework's URL location
		if ($this->stdResources) {
			// if (defined('FRAMEWORK_URL')) {
				// $frameworkUrl = '/'.FRAMEWORK_URL;
			// } else {
				// $rtStr = trim(ROOT_URL, '/');
				// $fwStr = trim(FRAMEWORK_DIR, '/');
				
				// $rtDir = explode('/', $rtStr);
				// $fwDir = explode('/', $fwStr);
				// $fwMatch = false;
				// foreach ($rtDir as $rtMatch => $pc) {
					// $fwMatch = array_search($pc, $fwDir);
					// if ($fwMatch !== false) break;
				// }
				// if ($fwMatch === false) throw new Exception('Could not determine framework URL from FRAMEWORK_DIR');
				
				// $rtDir = array_slice($rtDir, $rtMatch + 1);
				// $fwDir = array_slice($fwDir, $fwMatch + 1);
				
				// $frameworkUrl = getUrl(str_repeat('../', count($rtDir)).implode('/', $fwDir));
			// }
			
			$this->addLook('/'.FRAMEWORK_URL.'/look.css');
			$this->addDomlessFeel('/'.FRAMEWORK_URL.'/feel.js');
		}
	}
	
	//==============================================================USER HANDLING
	/*
	Returns an instance of User to represent the current user, or null if there is no user
	Default implementation returns null to express that there is no user
	*/
	public function generateUser() {
		return null;
	}
	
	/*
	Returns true/false indicating if a user is currently signed in
	*/
	public function isLoggedIn() {
		return $this->user !== null;
	}
	
	/*
	Action to log the user out
	*/
	public function logOut() {
		$this->user->logOut();
		$this->user = null;
		$this->setUserData(null);
	}
	
	//==============================================================HTML HEADER VALUES
	public function addLook($css) {
		if (DEBUG && ($ext = getExtension($css)) !== 'css' && $ext !== null) errorReport('Bad look file extension: '.$ext);
		if (!isAbsUrl($css)) $css = getResource($css);
		$this->lookFiles[crc32($css)] = $css;
	}
	
	public function getLooks() {
		return array_values($this->lookFiles);
	}
	
	public function addFeel($js) {
		if (DEBUG && ($ext = getExtension($js)) !== 'js' && $ext !== null) errorReport('Bad feel file extension: '.$ext);
		$this->addDomFeel($js);
	}
	
	public function addDomFeel($js) {
		if (DEBUG && ($ext = getExtension($js)) !== 'js' && $ext !== null) errorReport('Bad feel file extension: '.$ext);
		if (!isAbsUrl($js)) $js = getResource($js);
		$this->feelFilesDom[crc32($js)] = $js;
	}
	
	public function addDomlessFeel($js) {
		if (DEBUG && ($ext = getExtension($js)) !== 'js' && $ext !== null) errorReport('Bad feel file extension: '.$ext);
		if (!isAbsUrl($js)) $js = getResource($js);
		$this->feelFilesNoDom[crc32($js)] = $js;
	}
	
	public function getFeels() {
		return array_values(array_merge($this->feelFilesNoDom, $this->feelFilesDom));
	}
	
	public function getDomFeels() {
		return array_values($this->feelFilesDom);
	}
	
	public function getDomlessFeels() {
		return array_values($this->feelFilesNoDom);
	}
	
	public function setJavascriptValue($key, $val) {
		$this->jsValues[$key] = $val;
	}
	
	public function getJavascriptValues() {
		return $this->jsValues;
	}
	
	public function getJavascriptLine($key, $val) {
		if (is_string($val))		$val = '"'.$val.'"';
		else if (is_array($val))	$val = json_encode($val);
		return 'var '.$key.' = '.$val.';';
	}
	
	public function getJavascriptValuesElement() {
		if (empty($this->jsValues)) return '';
		$lines = array();
		foreach ($this->jsValues as $k => $v) $lines[] = $this->getJavascriptLine($k, $v);
		$ret = '<script type="text/javascript">';
		$ret .= implode(' ', $lines);
		$ret .= '</script>';
		return $ret;
	}
	
	public function addTitle($title) {
		$this->titles[] = $title;
	}
	
	public function setTitle($title) {
		$this->titles = array($title);
	}
	
	public function getTitle($sep = ' - ') {
		return implode($sep, $this->titles);
	}
	
	//==============================================================ACCESS TO MEMBER CLASSES
	public function getDB() {
		if ($this->db === null) 
			$this->db = new DB(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
		
		return $this->db;
	}
	
	public function getEmailSender() {
		if ($this->emailSender === null)
			$this->emailSender = new EmailSender(SENDER_EMAIL);
		return $this->emailSender;
	}
	
	//==============================================================URL/REDIRECT HANDLING
	public function getCurrentUrl() {
		return '/'.ROOT_URL.'/'.implode('/', $this->url);
	}
	
	public function redirect($url, $params = array()) {
		if (is_string($params)) $params = array($params => true);
		if (!empty($params)) $this->session['_priv']['redir'] = $params;
		header('Location: /'.ROOT_URL.'/'.$url);
		exit;
	}
	
	public function refresh($params = array()) {
		//Get the current url string by imploding the $this->url components
		$this->redirect(implode('/', $this->url), $params);
	}
	
	public function hasParam($key) {
		return isset($this->redirParams[$key]);
	}
	
	public function getParam($key) {
		return arrVal($this->redirParams, $key, null);
	}
	
	public function getParams() {
		return $this->redirParams;
	}
	
	//===============================================================SPECIAL SESSION DATA STORAGE
	public function pushSessionItem(AbstractInput $item) {
		$keys = explode('/', $item->getAbsoluteKey());
		//$lastInd = count($keys) - 1;
		//$final = $keys[$lastInd];
		//unset($keys[$lastInd]);
		
		$ptr =& $this->session['_form'];
		foreach ($keys as $key) {
			if (!isset($this->session['_form'][$key])) $this->session['_form'][$key] = array();
			$ptr =& $ptr[$key];
		}
		$ptr  = $item->getValue();
	}
	public function pullSessionItem(AbstractInput $item) {
		$keys = explode('/', $item->getAbsoluteKey());
		$ptr =& $this->session['_form'];
		foreach ($keys as $key) {
			if (!isset($this->session['_form'][$key])) return null;
			$ptr =& $ptr[$key];
		}
		return $ptr;
	}
	
	public function clearSessionItem(AbstractInput $item) {
		//TODO: this is sloppy, unsetting the pointer needs to work instead
		if ($item instanceof Form) {
			unset($this->session['_form'][$item->getKey()]);
			return;
		}
		$keys = explode('/', $item->getAbsoluteKey());
		$ptr =& $this->session['_form'];
		foreach ($keys as $key) {
			if (!isset($this->session['_form'][$key])) return null;
			$ptr =& $ptr[$key];
		}
		unset($ptr);
	}
	
	public function setUserData($data) {
		$this->session['_user'] = $data;
	}
	
	public function getUserData() {
		return $this->session['_user'];
	}
	
	//==============================================================ENTRY-POINT
	public function go() {
		//In debug mode the run time is tracked
		if (DEBUG) $start = microtime(true);
		
		$output = $this->layout->go($this->url);
		
		//If compression is enabled the output is processed
		if ($this->compressOutput) {
			//Replace any occurrence of 1 or more whitespace characters with a single space
			$output = preg_replace('~\s+~', ' ', $output);
			//Remove any single spaces directly separating 2 elements
			$output = preg_replace('~> <~', '><', $output);
		}
		
		//In debug mode the final run time is output if it is alarmingly high
		if (DEBUG) {
			$ms = ((microtime(true) - $start) * 1000);
			if ($ms > 50) echo '[[ Warning; long runtime: Took '.$ms.'ms ]]<br/>';
		}
		
		echo $output;
	}
}
	