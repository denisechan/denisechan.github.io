<?php
//Procedural initialization stuff (error display, timezone, session initialization)
if (DEBUG) { ini_set('display_errors', true); error_reporting(E_ALL); }
date_default_timezone_set(TIMEZONE);
session_start();

//Utility functions
function arrVal($arr, $val, $def = null) {
	return isset($arr[$val]) ? $arr[$val] : $def;
}
function debugHtml($val, $back = '#000000', $font = '#55ff55') {
	if ($val === true) $val = '_TRUE';
	else if ($val === false) $val = '_FALSE';
	else if (empty($val)) {
		if ($val === null) $val = '_NULL';
		else if (is_array($val)) $val = '_EMPTY_ARRAY';
		else if (is_string($val)) $val = '_EMPTY_STRING';
		else if (is_object($val)) $val = '_EMPTY_OBJECT('.get_class($val).')';
	}
	return '<pre style="background-color: '.$back.'; color: '.$font.'; max-height: 600px; overflow-y: auto;">'.print_r($val, true).'</pre>';
}
function show($val, $title = null) {
	if ($title !== null) echo "<h1>$title</h1>";
	echo debugHtml($val);
}
function getFile($file) {
	return '/'.ROOT_DIR."/$file";
}
function getResource($url) {
	return getUrl("resource/$url");
}
function getUrl($url) {
	$doubleSlash = strrpos($url, '//');
	if ($doubleSlash !== false) return substr($url, $doubleSlash);
	
	return '/'.ROOT_URL."/$url";
}

//Generates the initiation vector for encryption/decryption
function genInitVector() {
	//Parameters to this are $size (of the initiation vector) and $source
    return mcrypt_create_iv(
		mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB), 
		MCRYPT_RAND);
}
function encrypt($str) {
	//Takes a cipher-mode, encryption key, unencrypted string, mode, and initiation vector
    $enc = mcrypt_encrypt(
		MCRYPT_BLOWFISH, 
		ENCRYPT_KEY, utf8_encode($str), 
		MCRYPT_MODE_ECB, 
		genInitVector());
	
	return strtr(rtrim(base64_encode($enc), '='), '+/', '-_');
}
function decrypt($str) {
	$str = base64_decode(strtr($str, '-_', '+/'));
	
	//Takes a cipher-mode, encryption key, encrypted string, mode, and initiation vector
    return mcrypt_decrypt(
		MCRYPT_BLOWFISH, 
		ENCRYPT_KEY, $str, 
		MCRYPT_MODE_ECB, 
		genInitVector());
}

//Validation functions
function isValidEmail($str) {
	return preg_match('~[a-z._]+@[a-z._]+\.[a-z]{2,6}~', strtolower($str));
}

//==================================================================AJAX INTERFACE
/*
A class to maintain the state of a server-side value while providing a lightweight interface to
the client side for updating this value.
*/
abstract class ClientValue {
	
	private $processed = false;
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
		if ($this->processed) throw new Exception('Processed ClientValue twice: '.$this->key);
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
		$ret = $_POST;
		unset($ret['ajax']);
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
	
	public function value() {
		return $this->getData();
	}
}

class StringAjaxValue extends AjaxValue {
	
	public function StringAjaxValue($key) {
		parent::StringAjaxValue($key);
	}
	
	public function defaultValue() {
		return '';
	}
	
	public function ajaxAction($data) {
		//A value parameter is required
		if (!isset($data['value'])) return array('code' => 1, 'err' => 'missing "value" param');
		
		$this->setData((string) $data['value']);
	}
	
	public function value() {
		return $this->getData();
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
		return $num > $this->max 
			? 1 
			: (
				$num < $this->min 
				? -1
				: 0
			);
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
	
	//Add a node under key $k with value $v
	public function addNode($k, $v) {
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

	//"Crawl" through the TreeNode with an array of keys
	public function crawl($keys) {
		if (is_string($keys)) $keys = explode('/', $keys);
		$node = $this;
		foreach ($keys as $key) {
			if ($node->hasNode($key)) $node = $node->getNode($key);
			else return null;
		}
		return $node;
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
			if ($keyPath !== null)	$ret[implode('/', $keyPath)] = $this;
			//Do numeric indexing otherwise
			else					$ret[] = $this;
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
	
	public function fetch($sql, $params = array()) {
		$ret = $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
		return !empty($ret) ? $ret : null;
	}
	
	public function fetchAll($sql, $params = array()) {
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
interface User {
}

class DBUser {
	public $table;
	public $keys;
	public $where;
	
	public $data;
	
	public function DBUser(DBTable $table, DBKeys $keys = null, DBWhere $where = null) {
		$this->table = $table;
		$this->keys = $keys !== null ? $keys : new DBAllKeys();
		$this->where = $where;
		
		$query = new DBQuerySelect($this->keys, $this->table, $this->where);
		$this->data = $query->go();
	}
}

//==================================================================LAYOUT, PAGES, APPLICATION
class Layout {
	private static $buffering;
	
	public $filename;
	public $layouts;
	public $defKey;
	public $target;
	public $targetUrl;
	
	public function Layout($filename, $defKey = null, $layouts = array()) {
		$this->filename = $filename;
		$this->defKey = $defKey;
		$this->layouts = $layouts;
	}
	
	public function addLayout($key, $layout) {
		$this->layouts[$key] = $layout;
	}
	
	public function go($urlPcs) {
		if (empty($urlPcs) && $this->defKey !== null) $urlPcs = array($this->defKey);
		
		$this->target = array_shift($urlPcs);
		$this->targetUrl = $urlPcs;
		
		$file = getFile("resource/{$this->filename}");
		if (DEBUG && !file_exists($file)) return debugHtml('<p>Add the following file to the "resource" directory:<br/><b>'.$this->filename.'</b></p>');
		
		ob_start();					//Buffer start
		
		$LAYOUT = $this;
		require($file);
		
		$ret = ob_get_contents();
		ob_end_clean();				//Buffer end
		
		return $ret;
	}
	
	public function getInner() {
		if (empty($this->target)) return '';
		
		if (isset($this->layouts[$this->target])) {
			$targ = $this->layouts[$this->target];
		} else if (array_key_exists('*', $this->layouts)) {
			$targ = $this->layouts['*'];
		} else if (DEBUG) {
			echo '<div class="error">NON-EXISTANT PAGE: '.implode('/', App::$instance->url).'</div>';
			return null;
		}
		
		return $targ instanceof Layout 
			? $targ->go($this->targetUrl)
			: $this->target;
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
	
	//Database reference
	public $db = null;
	
	//Email sender reference
	public $emailSender = null;
	
	//Presentation variables
	public $lookFiles = array();	//List of css files
	public $feelFiles = array();	//List of js files
	public $titles = array();		//List of title components
	public $stdResources = true;	//True indicates to include standard css/js from the framework URL
	
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
		}
		
		//Initialize any redirect parameters
		if (isset($this->session['_priv']['redir'])) {
			$this->redirParams = $this->session['_priv']['redir'];
			unset($this->session['_priv']['redir']);
		}
		
		//Require default resources from the framework's URL location
		if ($this->stdResources) {
			if (defined('FRAMEWORK_URL')) {
				$frameworkUrl = FRAMEWORK_URL;
			} else {
				$rtStr = trim(ROOT_URL, '/');
				$fwStr = trim(FRAMEWORK_DIR, '/');
				
				$rtDir = explode('/', $rtStr);
				$fwDir = explode('/', $fwStr);
				$fwMatch = false;
				foreach ($rtDir as $rtMatch => $pc) {
					$fwMatch = array_search($pc, $fwDir);
					if ($fwMatch !== false) break;
				}
				if ($fwMatch === false) throw new Exception('Could not determine framework URL from FRAMEWORK_DIR');
				
				$rtDir = array_slice($rtDir, $rtMatch + 1);
				$fwDir = array_slice($fwDir, $fwMatch + 1);
				
				$frameworkUrl = str_repeat('../', count($rtDir)).implode('/', $fwDir);
			}
			$this->lookFiles[] = getUrl("$frameworkUrl/look.css");
			$this->feelFiles[] = getUrl("$frameworkUrl/feel.js");
		}
	}
	
	//Store form-data while the user stays on this site
	public function setFormData($name, $data) {
		$this->session['_form'][$name] = $data;
	}
	
	//Retrieve saved form data
	public function getFormData($name) {
		return arrVal($this->session['_form'], $name, null);
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
	
	public function addLook($css) {
		if (is_array($css)) {
			foreach ($css as $c) $this->addLook($c);
			return;
		}
		$this->lookFiles[] = getResource($css);
	}
	
	public function getLooks() {
		return $this->lookFiles;
	}
	
	public function addFeel($js) {
		if (is_array($js)) {
			foreach ($js as $j) $this->addFeel($j);
			return;
		}
		$this->feelFiles[] = getResource($js);
	}
	
	public function getFeels() {
		return $this->feelFiles;
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
	
	public function go() {
		$output = $this->layout->go($this->url);
		if ($this->compressOutput) {
			//Replace any occurrence of 1 or more whitespace characters with a single space
			$output = preg_replace('~\s+~', ' ', $output);
			//Remove any single spaces directly separating 2 elements
			$output = preg_replace('~> <~', '><', $output);
		}
		echo $output;
	}
}