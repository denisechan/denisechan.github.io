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
	return '<pre style="background-color: '.$back.'; color: '.$font.';">'.print_r($val, true).'</pre>';
}
function show($val) {
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
	
	public function hasNode($k) {
		return isset($this->subTree[$k]);
	}
	
	public function getNode($k) {
		if (!$this->hasNode($k)) return $this->addNode($k, null);
		return $this->subTree[$k];
	}
	
	public function addNode($k, $v) {
		if (!($v instanceof TreeNode)) $v = new TreeNode($v);
		$this->subTree[$k] = $v;
		return $v;
	}
	
	public function flatten($key = 0) {
		$arr = array();
		$arr[$key] = $this->value;
		foreach ($this->subTree as $k => $node) {
			$inner = $node->flatten($k);
			$arr = array_merge($arr, $inner);
		}
		return $arr;
	}
}

//==================================================================DATABASE
class DB {
	private $con;
	
	public static function dateTime() {
		return date('Y-m-d H:i:s');
	}
	
	public function DB($host, $db, $user, $pass, $port = 3306) {
		$this->con = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
		$this->con->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
		if (DEBUG) $this->con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
	}
	
	public function getPDO() {
		return $this->con;
	}
	
	public function query($sql, $params = array()) {
		$query = $this->con->prepare((string) $sql);
		$query->execute($params);
		return strlen($sql) > 6 && strtoupper(substr($sql, 0, 6)) === 'INSERT' ?
			$this->con->lastInsertId() : $query;
	}
	
	public function fetch($sql, $params = array()) {
		$ret = $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
		return !empty($ret) ? $ret : null;
	}
	
	public function fetchAll($sql, $params = array()) {
		return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
	}

	public function quote($val) {
		return $this->con->quote($val);
	}
	
	public function escape($val) {
		return substr($this->quote($val), 1, -1);
	}
}

interface DBPiece {
	public function show();
}

interface DBCondition extends DBPiece {
	public function showCondition();
}

class DBTable implements DBPiece {
	
	public $name;
	
	public function DBTable($name) {
		$this->name = $name;
	}

	public function show() {
		return '`'.$this->name.'`';
	}
}

class DBKey implements DBPiece {
	
	public $name;
	
	public function DBKey($name) {
		$this->name = $name;
	}
	
	public function show() {
		return '`'.$this->name.'`';
	}
	
}

class DBValue implements DBPiece {
	
	public $name;
	
	public function DBValue($name) {
		$this->name = $name;
	}
	
	public function show() {
		return '\''.$this->name.'\'';
	}
	
}

class DBKeyValue implements DBCondition {

	public $key;
	public $value;
	
	public function DBKeyValue($key, $value) {
		if (is_string($key)) $key = new DBKey($key);
		if (is_string($value)) $value = new DBValue($value);
		$this->key = $key;
		$this->value = $value;
	}
	
	public function show() {
		return $this->key->show().'='.$this->value->show();
	}
	
	public function showCondition() {
		//The condition happens to look exactly like the ordinary form
		return $this->show();
	}
	
}

class DBKeys implements DBPiece {
	public $keys;
	
	public function DBKeys($keys) {
		if (func_num_args() > 1) $this->keys = func_get_args();
		else {
			$this->keys = is_array($keys) ? $keys : array($keys);
		}
		foreach ($this->keys as &$key) if (is_string($key)) $key = new DBKey($key);
	}
	
	public function show() {
		$strs = array_map(function($key) { return $key->show(); }, $this->keys);
		return implode(',', $strs);
	}
}

class DBAllKeys extends DBKeys {
	
	public function DBAllKeys() {
		parent::DBKeys(array());
	}
	
	public function show() {
		return '*';
	}
	
}

class DBOr implements DBCondition {
	public $condition1;
	public $condition2;
	
	public function DBOr(DBCondition $condition1, DBCondition $condition2) {
		$this->condition1 = $condition1;
		$this->condition2 = $condition2;
	}
	
	public function show() {
		return 'OR';
	}
	
	public function showCondition() {
		return $this->condition1->showCondition().' OR '.$this->condition2->showCondition();
	}
}

class DBAnd implements DBCondition {
	public $condition1;
	public $condition2;
	
	public function DBAnd(DBCondition $condition1, DBCondition $condition2) {
		$this->condition1 = $condition1;
		$this->condition2 = $condition2;
	}
	
	public function show() {
		return 'AND';
	}
	
	public function showCondition() {
		return $this->condition1->showCondition().' AND '.$this->condition2->showCondition();
	}
}

class DBWhere implements DBPiece {
	
	public $conditions;
	
	public function DBWhere($conditions = array()) {
		if (!is_array($conditions)) $conditions = array($conditions);
		$this->conditions = $conditions;
	}
	
	public function addCond($condition) {
		$this->conditions[] = $condition;
	}
	
	public function show() {
		$condStr = array_map(function(DBCondition $v) { return $v->showCondition(); }, $this->conditions);
		$conds = implode(' AND ', $condStr);
		return "WHERE $conds";
	}
	
}

class DBLimit implements DBPiece {
	
	public $num;
	
	public function DBLimit($num) {
		$this->num = $num;
	}
	
	public function show() {
		return 'LIMIT '.$this->num;
	}
}

class DBOrder implements DBPiece {
	
	const ASCENDING = 0;
	const DESCENDING = 1;
	
	public $type;
	
	public function DBOrder($type) {
		$this->type = $type;
	}
	
	public function show() {
		return 'ORDER '.$this->type === DBOrder::ASCENDING ? 'ASC' : 'DESC';
	}
	
}

abstract class DBQuery implements DBPiece {
	public abstract function go();
}

class DBQuerySelect extends DBQuery {
	
	public $keys;
	public $table;
	public $where;
	
	public $order;
	public $limit;
	
	public function DBQuerySelect(DBKeys $keys, DBTable $table, DBWhere $where = null, DBOrder $order = null, DBLimit $limit = null) {
		$this->keys = $keys;
		$this->table = $table;
		$this->where = $where;
		
		$this->order = $order;
		$this->limit = $limit;
	}
	
	public function show() {
		$ret = 'SELECT '.$this->keys->show().' FROM '.$this->table->show(); 
		
		if ($this->where !== null) $ret .= ' '.$this->where->show();
		if ($this->order !== null) $ret .= ' '.$this->order->show();
		if ($this->limit !== null) $ret .= ' '.$this->limit->show();
		
		return $ret;
	}

	public function go() {
		$sql = $this->show();
		
		$db = App::$instance->getDB();
		return ($this->limit !== null && $this->limit->num === 1)
			? $db->fetch($sql)
			: $db->fetchAll($sql);
	}
}

//==================================================================FORMS, INPUTS
/*
Parent class of Forms and Input elements
Defines the notion of input nesting
*/
abstract class AbstractInput {
	
	public $key;
	public $par;
	public $inputs = array();
	public $error = null;
	
	public function AbstractInput($key) {
		$this->key = $key;
		$this->par = null;
	}
	
	public function addInput(AbstractInput $input) {
		if (DEBUG && $input->par !== null)
			throw new Exception('Cannot add an input that already has a parent (tried adding '.$input->key.' to '.$this->key.')');
		$this->inputs[$input->key] = $input;
		$input->par = $this;
		return $input;
	}
	
	public function getFullValue() {
		$ret = new TreeNode($this->getValue());
		foreach ($this->inputs as $k => $input) $ret->addNode($k, $input->getFullValue());
		return $ret;
	}
	
	public function getInputTree() {
		$ret = new TreeNode($this);
		foreach($this->inputs as $k => $input) $ret->addNode($k, $input);
		return $ret;
	}
	
	public function flatten() {
		$ret = array();
		$ret[] = $this;
		foreach ($this->inputs as $k => $input) {
			$inner = $input->flatten();
			$ret = array_merge($ret, $inner);
		}
		return $ret;
	}
	
	public abstract function getValue();

	//Recursively set values for this input and its' nested inner inputs
	public function setValue(TreeNode $node) {
		$this->value = $node->value;
		$this->error = null;
		foreach ($node->subTree as $k => $subNode) {
			if (isset($this->inputs[$k])) {
				$this->inputs[$k]->setValue($subNode);
			}
		}
	}
	
	//Recursively generate a tree-like input validation error-report
	public function getValidationErrors() {
		$innerErrs = array();
		foreach ($this->inputs as $k => $input) {
			$err = $input->getValidationErrors();
			if ($err !== null) $innerErrs[$k] = $err;
		}
		$this->error = $this->getValidationError();
		return $this->error === null 
			? null 
			: new TreeNode($this->error, $innerErrs);
	}
	
	//Returns the error caused by the input in the current state, or null if the current state is valid
	public abstract function getValidationError();
	
	//Returns a key/value array of every html attribute that has server-side implications
	public abstract function listControlAttributes($params = array());
	
	//Returns the unique local identifier of this input
	public function getKey() {
		return $this->key;
	}
	
	//Returns the list of keys that "ought" to be used to identify this input
	public function getKeyHeirarchy() {
		$inp = $this;
		$ret = array();
		do { $ret[] = $inp->getKey(); $inp = $inp->par; } while($inp !== null);
		return $ret;
	}
	
	//Returns the absolute key that should be used to identify this input
	public final function getAbsoluteKey() {
		$inp = $this;
		$ret = array();
		do { $ret[] = $inp->getKey(); $inp = $inp->par; } while($inp !== null);
		return implode('/', array_reverse($this->getKeyHeirarchy()));
	}
	
	public function getSqlKey() {
		//Convert weird characters to spaces, perform ucwords, replace spaces with underscores
		return str_replace(' ', '_', ucwords(str_replace(array('-', '_', "'", '"', '`'), ' ', $this->getKey())));
	}
	
	public function getSqlValue() {
		return App::$instance->getDb()->escape($this->getValue());
	}
	
	//Returns an html attribute-string containing all attributes with server-side implications
	public final function getControlAttributes($params = array()) {
		$list = array_map(function($val) {
			return htmlspecialchars($val);
		}, $this->listControlAttributes($params));
		$ret = array();
		foreach ($list as $k => $v) $ret[] = "$k=\"$v\"";
		return implode(' ', $ret);
	}
	
	//Echos the server-side-implicating attribute string
	public final function writeAttributes($params = array()) {
		echo $this->getControlAttributes($params);
	}
	
	//SHORTHAND FUNCTION exactly the same as $this->writeAttributes()
	public final function wAttrs($params = array()) {
		$this->writeAttributes($params);
	}
	
}

class ContainerInput extends AbstractInput {
	
	public function ContainerInput($key) {
		parent::AbstractInput($key);
	}
	
	public function getValue() {
		return null;
	}
	
	public function getValidationError() {
		foreach ($this->inputs as $inp)
			if ($inp->error !== null) return 'container-contains-invalid-inputs';
		return null;
	}
	
	public function listControlAttributes($params = array()) {
		return array();
	}
}

class Form extends AbstractInput {
	
	public $method = 'post';
	public $hasFiles = false;
	
	public $onSubmit = null;
	
	public function Form($key) {
		parent::AbstractInput($key);
	}
	
	public function getValue() {
		return null;
	}
	
	public function getValidationError() {
		foreach ($this->inputs as $inp)
			if ($inp->error !== null) return 'form-contains-invalid-inputs';
		return null;
	}
	
	/*
	Forms need to know when they have a FileInput added 
	This is because it introduces the need for the enctype attribute
	Override AbstractInput::addInput() to check for this
	*/
	public function addInput(AbstractInput $input) {
		if (DEBUG && $input->getKey() === 'ID') throw new Exception('Don\'t name a form input "ID" - choose another name');
		if ($input instanceof FileInput) $this->hasFiles = true;
		return parent::addInput($input);
	}
	
	public function listControlAttributes($params = array()) {
		$ret = array('method' => $this->method, 'action' => '', 'id' => 'FORM-'.$this->key);
		if ($this->hasFiles) $ret['enctype'] = 'multipart/form-data';
		return $ret;
	}
	
	public function loadFromState($formState) {
		$rootInputs = $formState->subTree;
		foreach ($rootInputs as $k => $inp)
			if (isset($this->inputs[$k])) $this->inputs[$k]->setValue($inp);
	}
	
	/*
	Ought to be called once a form has been fully defined
	*/
	public function process() {
		$m = strtolower($this->method);
		$dat = $m === 'post' ? $_POST : $_GET;
		
		//The presence of the "_submit" key means the form was submitted
		if (isset($dat['_submit']))
			$this->processSubmission($dat);
		else
			$this->processInitialization($dat);
	}
	
	public function processInitialization($dat) {
		//Check for and retrieve any saved states for this form
		$formState = App::$instance->getFormData($this->key);
		
		//If there is a previously saved state, load it
		if (!empty($formState)) $this->loadFromState($formState);
	}
	
	public function processSubmission($dat) {
		$app = App::$instance;
		
		$formState = new TreeNode(null);
		
		foreach ($dat as $absoluteKey => $v) {
			//The absolute key is a slash-separated list of local keys
			$pcs = explode('/', $absoluteKey);
			
			//Pointer to root of tree
			$node = $formState;
			
			//Move pointer to branch until the full key-heirarchy is traversed
			foreach ($pcs as $key) $node = $node->getNode($key);
			
			//Set the value of that node
			$node->value = $v;
		}
		
		//Save the submitted state to the instance
		$app->setFormData($this->key, $formState);
		$this->loadFromState($formState);
		
		//Form validation; get errors, and then hand them off to the form processing function
		$errs = $this->getValidationErrors();
		if ($this->onSubmit !== null) {
			$func = $this->onSubmit;
			$func($formState, $errs);
		}
		
		//Redirect
		$app->refresh();
	}

	public function dbPush($primary = array(), $replace = array()) {
		$app = App::$instance;
		
		$tableName = $this->getSqlKey();
		
		$inputs = $this->flatten();
		//Filter out any inputs that aren't a subclass of Input, as well as any SubmitInputs
		$inputs = array_filter($inputs, function($v) {
			return $v instanceof Input && !($v instanceof SubmitInput);
		});
		if (DEBUG) { //Do the following only in debug mode...
			//Check if any local keys conflict with each other and throw an error if they do
			$uniqueNames = array();
			foreach ($inputs as $inp) {
				$k = $inp->getKey();
				if (isset($uniqueNames[$k])) 
					throw new Exception('Form '.$this->getKey().' has conflicting local keys: "'.$k.'"');
				$uniqueNames[$k] = true;
			}
		}
		
		//$inputs is currently numerically indexed; convert to associative indexing
		$keylessInputs = $inputs;
		$inputs = array();
		foreach ($keylessInputs as $inp) $inputs[$inp->getKey()] = $inp;
		
		if (DEBUG) { //Do the following only in debug mode...
			//Generate and perform the SQL to create a table for this form
			$tableSql = "CREATE TABLE IF NOT EXISTS `$tableName`(";
			$declarations = array();
			$declarations[] = '`ID` INT NOT NULL AUTO_INCREMENT';
			foreach($inputs as $inp)
				$declarations[] = "`{$inp->getSqlKey()}` {$inp->getSqlDeclaration()}";
			$declarations[] = 'PRIMARY KEY(`ID`)';
			$tableSql .= implode(',', $declarations);
			$tableSql .= ') '; //End bracket for "CREATE TABLE"
			$tableSql .= 'COMMENT="Autogen '.date('Y-m-d').'; '.__FILE__.':'.__LINE__.'"'; //Leave a comment on the table
			
			show($tableSql);
			
			$app->getDb()->query($tableSql);
		}
		
		//Generate the SET fields string because regardless of whether we need to UPDATE or INSERT we will need it
		$entry = array();
		foreach ($inputs as $inp) $entry[] = "`{$inp->getSqlKey()}`='{$inp->getSqlValue()}'";
		$setQuery = 'SET '.implode(',', $entry);
		
		$query = '';
		//First check to see if an UPDATE operation is called for
		if (!empty($primary)) { //If there is at least one Input to be used as a primary key...
			//Ensure that $primary is an array
			if (!is_array($primary)) $primary = array($primary);
			//Ensure that every element in $primary is a form input element
			foreach ($primary as &$p) {
				if (is_string($p)) {
					if (DEBUG && !isset($inputs[$p])) throw new Exception('Non-existant field: '.$p.' in form '.$this->getKey());
					$p = $inputs[$p];
				}
			}
			
			$whereFields = array();
			foreach ($primary as $p) $whereFields[] = "`{$p->getSqlKey()}`='{$p->getSqlValue()}'";
			$where = 'WHERE '.implode(',', $whereFields);
			show($where);
			
			if (($res = $app->getDb()->fetch("SELECT * FROM `$tableName` $where LIMIT 1")) !== null) {
				$query = "UPDATE `$tableName` $setQuery $where";
				show($query);
				show($res);
			}
		}
		
		//Check to see if an INSERT operation is called for
		if (empty($query)) { //$query is empty if an UPDATE operation was deemed inappropriate
			$query = "INSERT INTO $tableName $setQuery";
			show($query);
		}
		
		$app->getDb()->query($query);
	}
}

abstract class Input extends AbstractInput {
	
	public $value = null;
	
	public $title;
	public $required;
	public $disabled;
	
	public function Input($key) {
		parent::AbstractInput($key);
	}
	
	public abstract function getSqlDeclaration();
	
	public function listControlAttributes($params = array()) {
		$ret = array('name' => $this->getAbsoluteKey());
		if ($this->required) $ret['required'] = 'required';
		if ($this->disabled) $ret['disabled'] = 'disabled';
		return $ret;
	}
	
	public function getValidationError() {
		foreach ($this->inputs as $input)
			if ($input->error !== null) return 'invalid-inner-input';
			
		return $this->validate($this->value);
	}
	
	/*
	Inputs need to remove the last element from their key heirarchy because it is the form key
	This is mostly an optimization because the main purpose is to ensure keys in the form submission
	array do not unnecessarily long
	*/
	public function getKeyHeirarchy() {
		$ret = parent::getKeyHeirarchy();
		array_pop($ret);
		return $ret;
	}
	
	/*
	Returns null, or a string describing an error with the input value $val
	*/
	public abstract function validate($val);
	
	public function getValue() {
		return $this->value;
	}
	
	public function writeValue() {
		echo htmlspecialchars($this->value);
	}
	
	//SHORTHAND FUNCTION exactly the same as $this->writeValue()
	public function wValue($params = array()) {
		$this->writeValue($params);
	}
}

class HardInput extends Input {
	public $maxLength;
	
	public function HardInput($key, $value, $maxLength = 45) {
		parent::Input($key);
		$this->value = $value;
		$this->maxLength = $maxLength;
	}
	
	public function getSqlDeclaration() {
		return "VARCHAR({$this->maxLength}) NOT NULL";
	}
	
	public function validate($val) {
		return null;
	}
}

class TextInput extends Input {
	const SHORT = 0;
	const LONG = 1;
	
	public $type = TextInput::SHORT;
	public $wordLimit = null;
	public $charLimit = null;
	
	public function TextInput($key, $value = null) {
		parent::Input($key, $value);
		$this->charLimit = new Range(0, 100); //Default limit of 100 characters
	}
	
	public function getSqlDeclaration() {
		return 'VARCHAR('.($this->charLimit === null ? 45 : $this->charLimit->max).') NOT NULL';
	}
	
	public function validate($val) {
		if (!is_string($val)) return 'incorrect-type';
		$len = strlen($val);
		if ($this->wordLimit !== null) {
			$words = substr_count($val, ' ') + 1;
			if ($words < $this->wordLimit->min) return 'too-few-words';
			if ($words > $this->wordLimit->max) return 'too-many-words';
		}
		if ($this->charLimit !== null) {
			$chars = strlen($val);
			if ($chars < $this->charLimit->min) return 'too-few-characters';
			if ($chars > $this->charLimit->max) return 'too-many-characters';
		}
		
		return null;
	}
	
	/*
	Setting $params === false will cause the attribute list to omit the value attribute
	This is useful when generating the attributes for a textarea (which has its' value
	as innerHtml content, not an attribute)
	*/
	public function listControlAttributes($params = array()) {
		$ret = array();
		
		//Set up wordlimit data
		if ($this->wordLimit !== null) {
			$ret['-data-word-min'] = $this->wordLimit->min;
			$ret['-data-word-max'] = $this->wordLimit->max;
		}
		
		//Set up charlimit data
		if ($this->charLimit !== null) {
			$ret['minlength'] = $this->charLimit->min;
			$ret['maxlength'] = $this->charLimit->max;
			
			$errMsg = '';
			if ($this->charLimit->min > 0) {
				$errMsg = 'Please enter at least '.$this->charLimit->min.' characters (max '.$this->charLimit->max.')';
			} else {
				$errMsg = 'Please enter no more than '.$this->charLimit->max.' characters';
			}
			
			$ret['title'] = $errMsg;
		}
		
		if ($this->value !== null && $params !== false && arrVal($params, 'value', true)) {
			$ret['value'] = $this->value;
		}
		
		return parent::listControlAttributes() + $ret;
	}
	
	public function getValue() {
		return $this->value;
	}
}

class SelectInput extends Input {
	public $options;
	
	public function SelectInput($key, $options = array()) {
		parent::Input($key);
		$this->options = array();
		foreach ($options as $k => $v)
			$this->options[is_numeric($k) ? $v : $k] = $v;
	}
	
	public function getSqlDeclaration() {
		$max = 0;
		foreach ($this->options as $k => $v) {
			$len = strlen($k);
			if ($len > $max) $max = $len;
		}
		return 'VARCHAR('.$max.') NOT NULL';
	}
	
	public function validate($val) {
		foreach ($this->options as $k => $v) if ($val === $k) return null;
		return 'option-not-on-list';
	}
	
	public function writeOptions() {
		foreach ($this->options as $k => $v)
			echo '<option value="'.htmlspecialchars($k).'">'.$v.'</option>';
	}
	
	//SHORTHAND FUNCTION exactly the same as $this->writeOptions()
	public function wOptions() {
		$this->writeOptions();
	}
}

class SubmitInput extends Input {
	//The name of this submit element - every submit element has the same key, but they have different names
	public $name;
	
	public function SubmitInput($name) {
		parent::Input('_submit');
		$this->name = $name;
	}
	
	public function getSqlDeclaration() {
		return 'VARCHAR(45) NOT NULL';
	}
	
	public function validate($val) {
		return null;
	}
	
	//Submit widgets need a non-absolute key, regardless of how they're nested
	public function getKeyHeirarchy() {
		//Return an array containing only the first key
		$ret = parent::getKeyHeirarchy();
		return array($ret[0]);
	}
}

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
		
		$file = getFile("layout/{$this->filename}");
		if (DEBUG && !file_exists($file)) return debugHtml('<p>Add the following file to the layouts directory:<br/><b>'.$this->filename.'</b></p>');
		
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
		} else if (isset($this->layouts['*'])) {
			$targ = $this->layouts['*'];
		} else if (DEBUG) {
			echo '<div class="error">NON-EXISTANT PAGE: '.implode('/', App::$instance->url).'</div>';
			return null;
		}
		
		return $targ->go($this->targetUrl);
	}
}

class App {
	
	public static $instance;
	
	public $name = null;
	
	public $url = null;
	public $file = null;
	public $layout = null;
	public $db = null;
	
	public $lookFiles = array();
	public $feelFiles = array();
	public $titles = array();
	
	//True indicates that final output should have unnecessary whitespace stripped
	public $compressOutput = true;
	
	//Reference to application-protected session object
	public $session = array();
	
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
	}
	
	//Store form-data while the user stays on this site
	public function setFormData($name, $data) {
		$this->session['_form'][$name] = $data;
	}
	
	//Retrieve saved form data
	public function getFormData($name) {
		return arrVal($this->session['_form'], $name, null);
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
	
	public function refresh() {
		header('Location: /'.ROOT_URL.'/'.implode('/', $this->url));
		exit;
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