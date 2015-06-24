<?php
/*
	Parent class of Forms and Input elements
	Defines the notion of input nesting
 */
abstract class AbstractInput extends AjaxListener {
	
	public $par;
	public $inputs = array();
	public $error = null;
	public $dbName = null;
	
	public function AbstractInput($key) {
		parent::ClientValue($key);
		$this->par = null;
	}
	
	public function ajaxAction($data) {
		
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
		foreach($this->inputs as $k => $input) 
		$ret->addNode($k, $input->getInputTree());
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
	
	//Get the value of this AbstractInput, ignoring any inner inputs
	public abstract function getValue();
	
	//Recursively set values for this input and its' nested inner inputs
	public final function setValue(TreeNode $node) {
		$this->value = $node->value;
		$this->error = null;
		foreach ($node->subTree as $k => $subNode) {
			if (isset($this->inputs[$k])) {
				$this->inputs[$k]->setValue($subNode);
			}
		}
	}
	
	//Recursively generate a tree-like input validation error-report
	public final function getValidationErrors() {
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
	
	public function getRoot() {
		$ret = $this;
		while ($ret->par !== null) $ret = $ret->par;
		return $ret;
	}
	
	//Returns the unique local identifier of this input
	public function getKey() {
		return $this->key;
	}
	
	/*
		Returns the list of keys that "ought" to be used to unambiguously identify this input
		The list is ordered from deep -> shallow.
		
		E.g.:
		$keys = $this->getKeyHeirarchy();
		
		//First key in the array is the key of the root AbstractInput containing $this
		$keys[0] === $this->getRoot()->getKey();
		
		//Last key in the array is the local key for $this
		$keys[count($keys) - 1] === $this->getKey();
	 */
	public function getKeyHeirarchy() {
		$inp = $this;
		$ret = array();
		//First add the keys in the opposite order that we need them
		do { $ret[] = $inp->getKey(); $inp = $inp->par; } while($inp !== null);
		
		//Reverse the array to get it from deep -> shallow ordering
		return array_reverse($ret);
	}
	
	//Returns the absolute key that should be used to identify this input
	public final function getAbsoluteKey() {
		$inp = $this;
		$ret = array();
		do { $ret[] = $inp->getKey(); $inp = $inp->par; } while($inp !== null);
		return implode('/', $this->getKeyHeirarchy());
	}
	
	public function getSqlKey() {
		//Convert weird characters to spaces, perform ucwords, replace spaces with underscores
		return $this->dbName !== null
			? $this->dbName 
			: str_replace(' ', '_', str_replace(array('-', '_', "'", '"', '`'), ' ', $this->getKey()));
	}
	
	public function getSqlValue() {
		return App::$instance->getDb()->escape($this->getValue());
	}
	
	//Returns an html attribute-string containing all attributes with server-side implications
	public function getControlAttributes($params = array()) {
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
	
	public $submitInputs = array();
	
	public $method = 'post';
	public $hasFiles = false;
	
	//If $debug is true, the form will always just show its' values/errors after a submission
	public $debug = false;
	
	//Closure: $this->onSubmit = function($formValues, $errs, $form) { ... };
	public $onSubmit = null;
	
	//True if the form returned as invalid and the user is being given another chance to fill it out
	public $retrying = false;
	
	public function Form($key) {
		parent::AbstractInput($key);
	}
	
	/*
		Override AbstractInput::needsProcess()
		Forms should ALWAYS process; this is because they are eing used to either generate
		markup, or process submitted data. In both cases processing needs to be done; if
		the form is generating markup it needs to process in order to pre-fill any fields
		that need to be pre-filled; if it is processing submitted data it needs to be
		processing the $_POST array.
	 */
	public function needsProcess() {
		return true;
	}
	
	/*
		Override ClientValue::getRelevantData()
		The data that is relevant to a form is EITHER the $_GET or $_POST array, depending
		on the "method" attribute of the form.
	 */
	public function getRelevantData() {
		return strtolower($this->method) === 'post' ? $_POST : $_GET;
	}
	
	public function submitKey() {
		return $this->getKey().'/_submit';
	}
	
	/*
		Override AbstractInput::action($data)
		A form's client-value action involves checking to see if the user is submitting the
		form and then:
		
		-If the user is NOT submitting the form, pre-filling the inner inputs of the form so
		the user may view the form with their previous input OR
		-If the user IS submitting the form, checking the input for errors and then allowing
		any user-defined functionality to run.
	 */
	public function action($data) {
		foreach ($this->inputs as $input) $input->process();
		if (isset($data[$this->submitKey()]))
			$this->processSubmission($data);
		else
			$this->processInitialization($data);
	}
	
	/*
		Functionality that is relevant if the form is being initialized (as opposed to being
		submitted)
	 */
	public function processInitialization($dat) {
		//Check if a retry is in progress...
		$errs = App::$instance->getParam($this->getKey().'_errors');
		if ($errs !== null) {
			$this->retrying = true;
			
			//Get the inputs of this form as a tree
			$inputs = $this->getInputTree();
			$errs = $errs->getLeaves();
			foreach ($errs as $k => $errNode) {
				$innerInput = $inputs->crawl($k);
				
				//No such input may exist
				if ($innerInput === null) continue;
				//Otherwise $innerInput is initially a TreeNode, we want the Input it contains
				else $innerInput = $innerInput->value; 
				$errMsg = $errNode->value;
				$innerInput->retryError = $errMsg;
			}
		}
		
		//Check for and retrieve any saved states for this form
		$formState = App::$instance->getFormData($this->key);
		
		//If there is a previously saved state, load it
		if (!empty($formState)) $this->loadFromState($formState);
	}
	
	/*
		Functionality that is relevant if the form is being processed
	 */
	public function processSubmission($dat) {
		$app = App::$instance;
		
		//Get the value of which submit button was pressed
		$submitValue = $dat[$this->submitKey()];
		unset($dat[$this->submitKey()]);
		
		$formState = new TreeNode(null); //null because the form itself has no data
		foreach ($dat as $absoluteKey => $v) {
			//The absolute key is a slash-separated list of local keys
			$pcs = explode('/', $absoluteKey);
			array_shift($pcs); //remove the first element
			
			//Pointer to root of tree
			$node = $formState;
			
			//Move pointer to branch until the full key-heirarchy is traversed
			foreach ($pcs as $key) $node = $node->getNode($key);
			
			//Node now points to a specific input-node; set the value of that node
			$node->value = $v;
		}
		
		//Save the submitted state to the instance
		$app->setFormData($this->key, $formState);
		
		//Set the values for every inner Input instance so they may validate their own values
		$this->loadFromState($formState);
		
		//Form validation; get errors, and then hand them off to the form processing function
		$errs = $this->getValidationErrors();
		
		if ($this->debug) {
			echo 'Form values:<br/>';
			show($formState);
			echo 'Form errors:<br/>';
			show($errs);
			exit;
		}
		
		//Handle the specific actions of the submit button that was pressed
		foreach ($this->submitInputs as $submit) {
			if ($submit->name === $submitValue) {
				if ($submit->onClick !== null) {
					$func = $submit->onClick;
					$func();
				}
				break;
			}
		}
		
		//If this form has an arbitrary handler, run it now
		if ($this->onSubmit !== null) {
			$func = $this->onSubmit;
			$func($formState, $errs, $this);
		}
		
		//Redirect
		$app->refresh();
	}
	
	/*
		Implement AbstractInput::getValue()
		Forms do not have an intrinsic value.
	 */
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
		if ($input instanceof SubmitInput) $this->submitInputs[] = $input;
		return parent::addInput($input);
	}
	
	public function listControlAttributes($params = array()) {
		$ret = array('method' => $this->method, 'action' => '', 'id' => 'FORM-'.$this->key);
		if ($this->hasFiles) $ret['enctype'] = 'multipart/form-data';
		if ($this->retrying) $ret['data-retry'] = 'retrying';
		return $ret;
	}
	
	public function loadFromState($formState) {
		//$rootInputs = $formState->subTree;
		foreach ($formState->subTree as $k => $inp)
		if (isset($this->inputs[$k])) $this->inputs[$k]->setValue($inp);
	}
	
	public function dbPush($primary = array()) {
		$app = App::$instance;
		
		$tableName = $this->getSqlKey();
		
		$inputs = $this->flatten();
		//Filter out any inputs that aren't a subclass of Input, SubmitInputs, and inputs that are db-irrelevant
		$inputs = array_filter($inputs, function($v) {
			return $v instanceof Input 
				&& !($v instanceof SubmitInput)
				&& $v->hasDbRelevance;
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
		
		//Check to see if an INSERT operation is called for (in which case $query is empty)
		if (empty($query)) $query = "INSERT INTO $tableName $setQuery";
		
		$app->getDb()->query($query);
		
		return true;
	}
	
	public function retry($errs = null) {
		App::$instance->refresh(array(
		$this->getKey().'_errors' => $errs
		));
	}
}

abstract class Input extends AbstractInput {
	
	public $value = null;
	
	public $retryError = null;
	
	public $hasDbRelevance = true;
	
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
		if ($this->retryError !== null) $ret['data-retry'] = $this->retryError;
		return $ret;
	}
	
	public function getValidationError() {
		foreach ($this->inputs as $input)
		if ($input->error !== null) return 'invalid-inner-input';
		
		return $this->validate($this->value);
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
			$ret['data-word-min'] = $this->wordLimit->min;
			$ret['data-word-max'] = $this->wordLimit->max;
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

class EmailInput extends TextInput {
	public function EmailInput($key, $value = null) {
		parent::TextInput($key, $value);
	}
	
	public function validate($val) {
		if (!isValidEmail($val)) return 'incorrect-email-format';
		return parent::validate($val);
	}
}

class DBUniqueInput extends TextInput {
	
	public $table;
	public $field;
	
	public function DBUniqueInput($key, $table, $field) {
		parent::TextInput($key);
		$this->table = $table;
		$this->field = $field;
	}
	
	private function checkUnique($val) {
		$db = App::$instance->getDb();
		return !$db->exists($this->table, array($this->field => $val));
	}
	
	public function ajaxAction($data) {
		return array('results' => $this->checkUnique);
	}
	
	public function validate($val) {
		if (!$this->checkUnique()) return 'value-already-taken';
		return parent::validate($val);
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
	//Closure that is run when this submit button is pressed client-side
	public $onClick = null;
	
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
	
	public function getControlAttributes($params = array()) {
		$output = parent::getControlAttributes($params);
		
		//The new name attribute ought to only consider the root (form) key, followed by "_submit"
		$keys = $this->getKeyHeirarchy();
		$newName = htmlspecialchars($keys[0].'/_submit');
		
		return preg_replace('~name="[^"]*"~', 'name="'.$newName.'"', $output);
	}
}