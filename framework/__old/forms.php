<?php
/*
	Parent class of Forms and Input elements
	Defines the notion of input nesting
 */
abstract class AbstractInput extends AjaxListener {
	public $par;
	
	public $retryError = null;
	
	public $error = null;
	public $dbName = null;
	
	public function AbstractInput($key, $value = null) {
		parent::ClientValue($key);
		$this->setValue($value);
		$this->par = null;
	}
	
	//==============================================================AJAX HANDLING
	/*
	The default response from an AbstractInput is simply to inform the client-side
	that they have successully contacted $this AbstractInput
	*/
	public function ajaxAction($data) {
		return array(
			'msg' => 'contacted input successfully',
			'class' => get_class($this),
			'key' => $this->getKey(),
			'params' => $data
		);
	}
	
	//==============================================================INNER INPUTS FUNCTIONALITY
	/*
	Apply a custom function to every child BELOW $this AbstractInput.
	If $includeParent is set to true, $func is also applied to $this.
	*/
	public function walk($func, $includeParent = false) {
		if ($includeParent) $func($this);
	}
	
	/*
	Get this input's root; traverse up the chain of parents until the end.
	*/
	public function getRoot() {
		$ret = $this;
		while ($ret->par !== null) $ret = $ret->par;
		return $ret;
	}
	
	//==============================================================TREENODE INTERFACE
	public function getInputTree() {
		return new TreeNode($this);
	}
	
	public function flatten() {
		return array($this);
	}
	
	//==============================================================KEY HANDLING
	/* 
	Returns the unique local identifier of this input
	*/
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
	
	/*
	Returns the absolute key that should be used to identify this input
	*/
	public final function getAbsoluteKey() {
		return implode('/', $this->getKeyHeirarchy());
	}
	
	//==============================================================VALUE HANDLING
	/*
	Get the value of this AbstractInput, ignoring any inner inputs
	*/
	public abstract function getValue();
	
	/*
	Set the value of this AbstractInput, not necessarily taking into account any inner inputs
	*/
	public abstract function setValue($value);

	public function sessionPush() {
		App::$instance->pushSessionItem($this);
	}
	
	public function sessionPull() {
		return App::$instance->pullSessionItem($this);
	}
	
	//==============================================================VALIDATION
	/*
	Recursively generate a tree-like input validation error-report
	*/
	public function getValidationErrors() {	
		$this->error = $this->getValidationError();
		return $this->error === null 
			? null 
			: new TreeNode($this->error);
	}
	
	/*
	Returns the error caused by the input in the current state, or null if the current state is valid
	*/
	public abstract function getValidationError();
	
	//==============================================================SQL FUNCTIONALITY
	public function getSqlKey() {
		//Check if a user-defined value exists for the sql key
		if ($this->dbName !== null) return $this->dbName;
		
		//Convert weird characters to underscores
		$ret = str_replace(array('-', '\'', '"', '`', ' '), '_', $this->getKey());
		
		return $ret;
	}
	
	public function getSqlValue($prepare = false) {
		return App::$instance->getDb()->escape($this->getValue());
	}
	
	//==============================================================HTML-OUTPUT
	/*
	Returns a key/value array of every html attribute that has server-side implications
	*/
	public abstract function listControlAttributes($params = array());
	
	/*
	Returns an html attribute-string containing all attributes with server-side implications
	*/
	public function getControlAttributes($params = array()) {
		$list = array_map(function($val) {
			return htmlspecialchars($val);
		}, $this->listControlAttributes($params));
		$ret = array();
		foreach ($list as $k => $v) $ret[] = "$k=\"$v\"";
		return implode(' ', $ret);
	}
	
	/*
	Echos the server-side-implicating attribute string
	*/
	public final function writeAttributes($params = array()) {
		echo $this->getControlAttributes($params);
	}
	
	/*
	SHORTHAND FUNCTION exactly the same as $this->writeAttributes()
	*/
	public final function wAttrs($params = array()) {
		$this->writeAttributes($params);
	}	

	public function alterKey($keyReplace) {
		return is_array($keyReplace) 
			? str_replace(array_keys($keyReplace), array_values($keyReplace), $this->key)
			: $keyReplace;
	}
	
	/*
	Copies any important values into $instance (an instance of the same class as $this), in
	an attempt to cause $instance to behave similarly to $this.
	*/
	public function populate($instance) {
		$instance->setValue($this->getValue());
	}
	
	/*
	Duplicates $this Input object, giving the clone a new $key value.
	
	$keyReplace may either be a string or an associative array.
	
	if $children is set to true, the children of $this will also be duplicated and
	add to the returned object.
	*/
	public function duplicate($keyReplace, $children = true) {
		$newKey = $this->alterKey($keyReplace);
		
		$class = get_class($this);
		$ret = new $class($newKey);
		$this->populate($ret);
		return $ret;
	}

	//SHORTHAND FUNCTION; exactly the same as $this->duplicate($keyReplace, $children);
	public final function dup($keyReplace, $children = true) {
		return $this->duplicate($keyReplace, $children);
	}
}

abstract class ContainerInput extends AbstractInput {
	public $inputs = array();
	
	public function ContainerInput($key, $value = null) {
		parent::AbstractInput($key, $value);
	}

	/*
	Overridable function allows for any modifications to be made to the $this->inputs
	list before
	*/
	public function initializeInput() {
	}
	
	public function process() {
		//Allow any ajax calls to be handled first by the parent
		parent::process(); 
		
		$this->initializeInput();
		
		//Process all inner inputs before processing ourselves
		foreach ($this->inputs as $inp) if (!$inp->processed) $inp->process();
	}
	
	public function addInput(AbstractInput $input) {
		if (DEBUG && $input->par !== null)
			throw new Exception('Cannot add an input that already has a parent (tried adding '.$input->key.' to '.$this->key.')');
		$this->inputs[$input->key] = $input;
		$input->par = $this;
		return $input;
	}
	
	public final function getInput($key) {
		return arrVal($this->inputs, $key, null);
	}
	
	public final function remInputs() {
		foreach ($this->inputs as $input) $input->par = null;
		$this->inputs = array();
	}
	
	public final function crawl($key) {
		if (is_string($key)) $key = explode('/', $key);
		$ret = $this;
		foreach ($key as $k) {
			if (!isset($ret->inputs[$k])) return null;
			$ret = $ret->inputs[$k];
		}
		return $ret;
	}
	
	/*
	Apply a custom function to every child BELOW $this AbstractInput.
	If $includeParent is set to true, $func is also applied to $this.
	*/
	public final function walk($func, $includeParent = false) {
		parent::walk($func, $includeParent);
		foreach ($this->inputs as $input) $input->walk($func, true);
	}
	
	public final function getInputTree() {
		$ret = parent::getInputTree();
		foreach ($this->inputs as $k => $input) $ret->addNode($k, $input->getInputTree());
		return $ret;
	}
	
	public final function flatten() {
		$ret = parent::flatten();
		foreach ($this->inputs as $k => $input) {
			$inner = $input->flatten();
			$ret = array_merge($ret, $inner);
		}
		return $ret;
	}
	
	public function setValue($val) {
		//TODO: WORRY ABOUT $val BEING JSON OR SQL-INDEX?
		if (is_array($val)) {
			foreach ($val as $k => $v) {
				if (isset($this->inputs[$k])) {
					$this->inputs[$k]->setValue($v);
				}
			}
		}
	}
	
	/*
	Return an array of the values of every non-submit inner input
	*/
	public function getValue() {
		$ret = array();
		foreach ($this->inputs as $k => $inp) {
			$v = $inp->getValue();
			//Only save if v isn't null or an empty array
			if (!empty($v) || is_string($v) || is_numeric($v)) $ret[$k] = $v;
		}
		return $ret;
	}
	
	public final function getValidationErrors() {
		$ret = parent::getValidationErrors();
		if ($ret === null) return null;
		
		foreach ($this->inputs as $k => $input) {
			$err = $input->getValidationErrors();
			if ($err !== null) $ret->addNode($k, $err);
		}
		
		return $ret;
	}
	
	public function getValidationError() {
		foreach ($this->inputs as $input)
			if ($input->error !== null) return 'invalid-inner-input';
	}
	
	public function duplicate($keyReplace, $children = true) {
		$ret = parent::duplicate($keyReplace, $children);
		
		if ($children) foreach ($this->inputs as $inner) $ret->addInput($inner->duplicate($keyReplace));
		
		return $ret;
	}
}

class Form extends ContainerInput {
	
	public $method = 'post';
	private $hasFiles = false;
	
	//Memoized relevant data
	private $memRelData = null;
	
	//If $debug is true, the form will always just show its' values/errors after a submission
	public $debug = false;
	
	//Closure: $this->onSubmit = function($formValues, $errs, $form) { ... };
	public $onSubmit = null;
	
	//True if the form returned as invalid and the user is being given another chance to fill it out
	public $retrying = false;
	
	public function Form($key) {
		parent::AbstractInput($key);
	}
	
	//==============================================================AJAX HANDLING
	public function process() {
		//Allow parent functionality to occur first (ajax handling, value handling)
		parent::process();
		
		$data = $this->getRelevantData();
		if (isset($data['_submit']))
			$this->processSubmission($data);
		else
			$this->processInitialization($data);
	}
	
	public function ajaxAction($data) {
		//TODO: ajax form-submission is handled here; probably just call $this->processSubmission($data)
		$data = $this->processRequestArray($data);
		
		$this->save($data);
		
		$errs = $this->getValidationErrors()->getLeaves();
		
		return array(
			'msg' => 'ajax form submit complete',
			'form-errors' => $errs
		);
	}
	
	private function processRequestArray($data) {
		$ret = array();
		
		foreach ($data as $k => $v) {
			//Take off the 1st key every time (it's the form's key)
			$keys = array_slice(explode('/', $k), 1);
			$ind =& $ret;
			foreach ($keys as $key) {
				if (!isset($ind[$key])) $ind[$key] = array();
				$ind =& $ind[$key];
			}
			//$ind is now a reference to the leaf; setting $ind sets the leaf's value
			$ind = $v;
		}
		
		return $ret;
	}
	
	/*
	Override ClientValue::getRelevantData()
	The data that is relevant to a form is EITHER the $_GET or $_POST array, depending
	on the "method" attribute of the form.
	*/
	public function getRelevantData() {
		if ($this->memRelData === null) {
			$data = strtolower($this->method) === 'post' ? $_POST : $_GET;
			
			//Check if the index corresponding to a submit button exists
			if (isset($data[$this->getKey().'/_submit'])) {
				//If the $_POST/$_GET array is being submitted, that array is the relevant data
				//Process it into the correct format (nested-array format instead of crawlable-keys format)
				$this->memRelData = $this->processRequestArray($data);
			} else {
				//Otherwise, the session-stored data is relevant
				//$this->memRelData = App::$instance->getFormData($this->key);
				$this->memRelData = App::$instance->pullSessionItem($this);
			}
			
			//Ensure that the relevant data is an empty array, if it would otherwise be empty
			if (empty($this->memRelData)) $this->memRelData = array();
			
			if (count($this->memRelData) === 1 && key($this->memRelData) === $this->getKey()) {
				$this->memRelData = $this->memRelData[$this->getKey()];
			}
		}
		
		//Memoize the response because this function is a little hefty, and is called frequently.
		return $this->memRelData;
	}
	
	/*
	Functionality that is relevant if the form is being initialized, as opposed to being
	submitted
	*/
	private function processInitialization($dat) {
		//Check if a retry is in progress at this point
		$this->checkHandleRetry();
		
		//Load from previously saved data
		$this->setValue($dat);
	}
	
	/*
	Goes through all the motions of saving the array of data $dat (in the session)
	
	This involves setting the form's values, saving them to session, and then
	carrying out any actions due to submit buttons.
	*/
	private function save($dat) {
		$app = App::$instance;
		
		//Get the value of which submit button was pressed; remove the submit entry from the array
		$submitVal = $dat['_submit'];
		unset($dat['_submit']);
		
		//Set value and save to session
		$this->setValue($dat);
		$this->sessionPush();
		
		//Search all submit inputs for max 1 SubmitInput whose name matches the submit value
		$tree = $this->getInputTree();	
		$submits = $tree->search(function($inp) use($submitVal) {
			return $inp instanceof SubmitInput 
				&& $inp->name === $submitVal;
		}, 1);
		if (!empty($submits)) {
			$func = $submits[0]->onClick;
			if ($func !== null) $func($submitVal);
		}
	}
	
	/*
	Functionality that is relevant if the form is being processed
	*/
	private function processSubmission($dat) {
		$app = App::$instance;
		
		//Save $dat into the session, check for any resulting errors
		$this->save($dat);
		$errs = $this->getValidationErrors();
		
		if ($this->debug) {
			show($this->getValue(), 'Form Values');
			show($errs === null ? null : $errs->getLeaves(), 'Form Errors');
			exit;
		}
		
		//If this form has an arbitrary handler, run it now
		if ($this->onSubmit !== null) {
			$func = $this->onSubmit;
			$func($this->getValue(), $errs, $this);
		}
		
		//Redirect
		$app->refresh();
	}
	
	/*
	Checks the session for an entry indicating a retry is in progress.
	If a retry is in progress, flags are set both on $this and on the inner inputs
	that correspond to the errors.
	*/
	private function checkHandleRetry() {
		$retryKey = "{$this->getKey()}_errors";
		if (($errs = App::$instance->getParam($retryKey)) !== null) {
			$this->retrying = true;
			
			//Get the inputs of this form as a tree
			$inputs = $this->getInputTree();
			foreach ($errs->getLeaves() as $k => $errMsg) {
				//No such input may exist
				if (($innerInput = $inputs->crawl($k)) === null) continue;
				
				//We found an input that has an error, so set its $retryError variable
				$innerInput->retryError = $errMsg;
			}
		}
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
		$ret = array(
			'method' => $this->method, 
			'action' => App::$instance->getCurrentUrl(), 
			'id' => 'FORM-'.$this->key
		);
		if ($this->hasFiles) $ret['enctype'] = 'multipart/form-data';
		if ($this->retrying) $ret['data-retry'] = 'retrying';
		return $ret;
	}

	public function getSqlCreateTable($tableName, $inputs = null) {
		if ($inputs === null) $inputs = $this->inputs;
		
		$ret = "CREATE TABLE IF NOT EXISTS `$tableName`(";
		$declarations = array();
		$declarations[] = '`ID` INT NOT NULL AUTO_INCREMENT';
		foreach($inputs as $input)
		$declarations[] = "`{$input->getSqlKey()}` {$input->getSqlDeclaration()}";
		$declarations[] = 'PRIMARY KEY(`ID`)';
		$ret .= implode(',', $declarations);
		$ret .= ') '; //End bracket for "CREATE TABLE"
		$ret .= 'COMMENT="Autogen '.date('Y-m-d').'; '.__FILE__.':'.__LINE__.'"'; //Leave a comment on the table
	
		return $ret;
	}
	
	/*
	Returns the `Field_Name`='Field_Value' list for an array of $inputs
	Also can inform inputs to prepare themselves in the case of multi-table inserts
	*/
	private function getSqlEntries($inputs = null, $delim = ',', $prepare = false) {
		if ($inputs === null) $inputs = $this->inputs;
		
		$entry = array();
		foreach ($inputs as $input) {
			$key = $input->getSqlKey();
			$val = $input->getSqlValue($prepare);
			$entry[] = "`$key`='$val'";
		}
		
		return implode($delim, $entry);
	}
	
	public function dbPush($primary = array()) {
		$app = App::$instance;
		
		$tableName = $this->getSqlKey();
		
		$inputs = $this->flatten();
		
		//Filter out any inputs that aren't a subclass of Input, SubmitInputs, and inputs that are db-irrelevant
		$inputs = array_filter($inputs, function($v) {
			return $v instanceof Input && !($v instanceof SubmitInput)
				&& $v->hasDbRelevance;
		});
		
		if (DEBUG) { //Do the following only in debug mode...
			//Check if any local keys conflict with each other and throw an error if they do
			$uniqueNames = array();
			foreach ($inputs as $inp) {
				$k = $inp->getKey();
				if (isset($uniqueNames[$k]))
					throw new Exception('Form '.$this->getKey().' has a conflicting local key: "'.$k.'"');
				$uniqueNames[$k] = true;
			}
		}
		
		//$inputs is currently numerically indexed; convert to associative indexing
		$keylessInputs = $inputs;
		$inputs = array();
		foreach ($keylessInputs as $inp) $inputs[$inp->getKey()] = $inp;
		
		if (DEBUG) { //Do the following only in debug mode...
			//Generate and perform the SQL to create a table for this form
			$tableSql = $this->getSqlCreateTable($tableName, $inputs);
			$app->getDb()->query($tableSql);
		}
		
		$query = '';
		//First check to see if an UPDATE operation is called for
		if (!empty($primary)) { //If there is at least one Input to be used as a primary key...
			//Ensure that $primary is an array
			if (!is_array($primary)) $primary = array($primary);
			//Ensure that every element in $primary is a form input element
			foreach ($primary as &$p) {
				if (is_string($p)) {
					if (DEBUG && !isset($inputs[$p])) throw new Exception('Non-existant field: '.$p.' in form '.$this->getKey());
					$p = $inputs[$p]; //$p is assigned by reference so modify its' value (point it to the Input it refers to)
				}
			}
			
			//The WHERE clause; based off the primary keys passed to this function
			$where = 'WHERE '.$this->getSqlEntries($primary, ' AND ');
			if (($res = $app->getDb()->fetch("SELECT * FROM `$tableName` $where LIMIT 1")) !== null) {
				$setEntries = $this->getSqlEntries($inputs, ',', true);
				$query = "UPDATE `$tableName` SET $setEntries $where";
			}
		}
		
		/*
		Check to see if an INSERT operation is called for (in which case $query is empty)
		$query will NOT by empty (and this block will therefore be skipped) if the logic has
		already determined that an UPDATE operation is required.
		*/
		if (empty($query)) {
			$setEntries = $this->getSqlEntries($inputs, ',', true);
			$query = "INSERT INTO $tableName SET $setEntries";
		}
		
		//By now query must be a valid SQL query
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
	
	public $hasDbRelevance = true;
	
	public $title = '';
	public $required = true;
	public $disabled = false;
	
	public function Input($key, $value = null) {
		parent::AbstractInput($key, $value);
	}
	
	//==============================================================SQL FUNCTIONALITY
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
	
	//==============================================================VALIDATION
	/*
		Returns null, or a string describing an error with the input value $val
	 */
	public abstract function validate($val);
	
	//==============================================================VALUE HANLDING
	public function getValue() {
		return $this->value;
	}
	
	/*
	Extendable function used to set an input's value
	*/
	public function setValue($value) {
		$this->value = $value;
	}
	
	public function writeValue() {
		echo htmlspecialchars($this->value);
	}
	
	//SHORTHAND FUNCTION exactly the same as $this->writeValue()
	public function wValue($params = array()) {
		$this->writeValue($params);
	}
	
	//SHORTHAND FUNCTION; exactly the same as $this->writeInput($attrs)
	public final function wInput($attrs = array()) {
		$this->writeInput($attrs);
	}
	
	public function populate($instance) {
		parent::populate($instance);
		$instance->hasDbRelevance = $this->hasDbRelevance;
		$instance->title = $this->title;
		$instance->required = $this->required;
		$instance->disabled = $this->disabled;
	}
}

class HardInput extends Input {
	public $maxLength;
	private $hardValue;
	
	public function HardInput($key, $value, $maxLength = 45) {
		parent::Input($key, $value);
		$this->hardValue = $value;
		$this->maxLength = $maxLength;
	}
	
	public function getSqlDeclaration() {
		return "VARCHAR({$this->maxLength}) NOT NULL";
	}
	
	public function getValue() {
		return $this->hardValue;
	}
	
	public function sessionPush() { /*do nothing*/ }
	
	public function validate($val) {
		return null;
	}
}

class TextInput extends Input {
	public $wordLimit = null;
	public $charLimit = null;
	
	public function TextInput($key, $value = '') {
		parent::Input($key, $value);
		$this->charLimit = new Range(0, 100); //Default limit of 100 characters
	}
	
	public function getSqlDeclaration() {
		$max = $this->charLimit === null ? 45 : $this->charLimit->max;
		return "VARCHAR($max) NOT NULL";
	}
	
	public function validate($val) {
		if (!empty($val) && !is_string($val)) return 'incorrect-type';
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
		}
		
		if ($this->value !== null && $params !== false && arrVal($params, 'value', true)) {
			$ret['value'] = $this->value;
		}
		
		return parent::listControlAttributes() + $ret;
	}

	/*
	Write the full html <input> element to represent this TextInput.
	The $attrs parameter can be used to add any additional html attributes
	to the element.
	
	If $attrs is a string instead of an array, that string is interpreted as
	the "type" attribute of the input.
	
	If you wish to add a class and id to an element you are generating with
	wInput, you could do it like so:
		|	<?php
		|	$input = $form->addInput(new TextInput('textItem1'));
		|	//Initialize $input.....
		|	?>
		|	<div class="field">
		|		<?php $input->wInput(array('class' => 'my-class', 'id' => 'my-id')); ?>
		|	</div>
	
	This will produce something close to the following markup:
		|	<div class="field">
		|		<input type="text" class="my-class" id="my-id" name="form/textItem1"/>
		|	</div>
		
	Notice that the "type" attribute is omitted - this is because the type defaults to
	text. If you wish to specify another type (like "password") then the array can include
	an entry like:
		|	'type' => 'password'
	*/
	public function writeInput($attrs = array()) {
		if (is_string($attrs)) $attrs = array('type' => $attrs);
		if (!isset($attrs['type'])) $attrs['type'] = 'text';
		$attrStr = htmlAttributes($attrs);
		
		echo '<input ';
		$this->wAttrs();
		if (!empty($attrStr)) echo " $attrStr";
		echo '/>';
	}
	
	/*
	Very similar to $this->writeInput($attrs), except it generates the markup for
	a <textarea> instead of an <input>
	*/
	public function writeTextArea($attrs = array()) {
		$attrStr = htmlAttributes($attrs);
		
		echo '<textarea ';
		$this->wAttrs(false);
		if (!empty($attrStr)) echo " $attrStr";
		echo '>';
		$this->wValue();
		echo '</textarea>';
	}
	
	//SHORTHAND FUNCTION; exactly the same as $this->writeTextArea($attrs)
	public final function wTextArea($attrs = array()) {
		$this->writeTextArea($attrs);
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

class PhoneInput extends TextInput {
	
	public function PhoneInput($key, $value = '') {
		parent::TextInput($key, $value);
	}
	
	public function validate($val) {
		//TODO: validate $val is a phone number
		return parent::validate($val);
	}
}

class DateInput extends TextInput {
	
	public function DateInput($key, $value = null) {
		parent::TextInput($key, $value);
	}
	
	public function validate($val) {
		//TODO: validate $val is a date
		return parent::validate($val);
	}

	public function writeInput($attrs = array()) {
		$attrs['type'] = 'date';
		$attrStr = htmlAttributes($attrs);
		
		echo '<input ';
		$this->wAttrs();
		if (!empty($attrStr)) echo " $attrStr";
		echo '/>';
	}

}

class DateTimeInput extends TextInput {
	
	public function DateTimeInput($key, $value = null) {
		parent::TextInput($key, $value);
	}
	
	public function validate($val) {
		//TODO: validate $val is a datetime
		return parent::validate($val);
	}
	
	public function writeInput($attrs = array()) {
		$attrs['type'] = 'datetime';
		$attrStr = htmlAttributes($attrs);
		
		echo '<input ';
		$this->wAttrs();
		if (!empty($attrStr)) echo " $attrStr";
		echo '/>';
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
		if (!isset($data['value'])) return array('code' => 1, 'err' => 'missing "value" param');
		
		$val = $this->checkUnique($data['value']);
		return array(
			'result' => $val,
			'msg' => $val ? 'value is unique' : 'value already exists'
		);
	}
	
	public function validate($val) {
		if (!$this->checkUnique($val)) return 'value-already-taken';
		return parent::validate($val);
	}
}

class CheckInput extends Input {
	public $needsCheck = false;
	
	public function CheckInput($key) {
		parent::Input($key, null);
	}
	
	public function getSqlDeclaration() {
		return "INT(1) NOT NULL DEFAULT 0";
	}
	
	public function getSqlValue($prepare = false) {
		return App::$instance->getDb()->escape($this->value === null ? 0 : 1);
	}
	
	public function validate($val) {
		if ($this->needsCheck && $val === null) return 'must-check-checkbox';
		return null;
	}
	
	public function listControlAttributes($params = array()) {
		$ret = array('value' => 'on');
		
		if ($this->value !== null) $ret['checked'] = 'checked';
		
		return parent::listControlAttributes($params) + $ret;
	}

	public function writeInput($attrs = array()) {
		$attrs['type'] = 'checkbox';
		$attrStr = htmlAttributes($attrs);
		
		echo '<input ';
		$this->wAttrs();
		if (!empty($attrStr)) echo " $attrStr";
		echo '/>';
	}
}

class NumberInput extends Input {
	public $range;
	public $step = 1;
	
	public function NumberInput($key, $value = 0) {
		parent::Input($key, $value);
		$this->range = new Range(0, 10000000);
	}
	
	public function getSqlDeclaration() {
		$max = $this->range === null ? 11 : (strlen("{$this->range->max}") + 1);
		return "INT($max) NOT NULL";
	}

	public function validate($val) {
		if (empty($val)) $val = 0;
		
		if (!is_numeric($val)) return 'invalid-type';
		$val = (int) $val;
		if (!$this->range->inRange((int) $val)) return 'integer-out-of-range';
		
		return null;
	}

	public function listControlAttributes($params = array()) {
		$ret = array();
		
		if ($this->range !== null) {
			$ret['min'] = $this->range->min;
			$ret['max'] = $this->range->max;
		}
		
		if ($this->step !== null) {
			$ret['step'] = $this->step;
		}
		
		$ret['value'] =  $this->value !== null 
			? $this->value 
			: (arrHas($params, 'value') ? $params['value'] : 0);
		
		return parent::listControlAttributes() + $ret;
	}

	public function writeInput($attrs = array()) {
		if (!isset($attrs['type'])) $attrs['type'] = 'number';
		$attrStr = htmlAttributes($attrs);
		
		echo '<input ';
		$this->wAttrs();
		if (!empty($attrStr)) echo " $attrStr";
		echo '/>';
	}

	public function populate($input) {
		$input->range = $this->range->duplicate();
	}
}

abstract class MultiInput extends Input {
	public $options;
	
	public function MultiInput($key, $options = array()) {
		parent::Input($key);
		$this->setOptions($options);
	}
	
	public function setOptions($options) {
		$this->options = array();
		foreach ($options as $k => $v) $this->options[is_numeric($k) ? $v : $k] = $v;
	}
	
	public abstract function writeOptions();
	
	//SHORTHAND FUNCTION exactly the same as $this->writeOptions()
	public function wOptions() {
		$this->writeOptions();
	}
}

class SelectInput extends MultiInput {

	public function SelectInput($key, $options = array()) {
		parent::MultiInput($key, $options);
	}
	
	public function getSqlDeclaration() {
		$max = 0;
		foreach ($this->options as $k => $v) {
			$len = strlen($k);
			if ($len > $max) $max = $len;
		}
		return "VARCHAR($max) NOT NULL";
	}
	
	public function validate($val) {
		//Selects with no options allow for dynamic client-side values to be added and the like
		if (empty($this->options) || $val === null) return null;
		foreach ($this->options as $k => $v) if ($val === $k) return null;
		return 'option-not-on-list('.strRep($val).')';
	}
	
	public function writeOptions() {
		foreach ($this->options as $k => $v) {
			$sel = $k === $this->getValue() ? ' selected="selected"' : '';
			echo '<option value="'.htmlspecialchars($k).'"'.$sel.'>'.$v.'</option>';
		}
	}

	public function writeInput($attrs = array()) {
		$attrStr = htmlAttributes($attrs);
		
		echo '<select ';
		$this->wAttrs();
		if (!empty($attrStr)) echo " $attrStr";
		echo '>';
		$this->wOptions();
		echo '</select>';
	}
	
	public function writeSelect($attrs = array()) {
		$this->writeInput($attrs);
	}
	
	//SHORTHAND FUNCTION; exactly the same as $this->writeSelect($attrs);
	public final function wSelect($attrs = array()) {
		$this->writeSelect($attrs);
	}
}

class CompoundInput extends ContainerInput {
	private $format = 'json';
	
	public function CompoundInput($key, $format = 'json', $dbTable = 'Options', $dbRef = 'Ref', $dbKey = 'Key', $dbVal = 'Value') {
		parent::ContainerInput($key);
		$this->setFormat($format, $dbTable, $dbRef, $dbKey, $dbVal);
	}
	
	public function initializeInput() {
		//Set all children below $this to not have db relevance
		$this->walk(function($input) { $input->hasDbRelevance = false; });
		parent::initializeInput();
	}
	
	public final function setFormat($format, $dbTable = 'Options', $dbRef = 'Ref', $dbKey = 'Key', $dbVal = 'Value') {
		$format = strtolower($format);
		if ($format !== 'json' && $format !== 'sql') throw new Exception('Format must be "json" or "sql"');
		$this->format = $format === 'json' 
			? 'json'
			: array('format' => $format, 'table' => $dbTable, 'ref' => $dbRef, 'key' => $dbKey, 'val' => $dbVal);
	}
	
	public final function getSqlDeclaration() {
		$format = is_array($this->format) ? $this->format['format'] : $this->format;
		if ($format === 'json') {
			return 'VARCHAR(2000) NOT NULL';
		} else if ($format === 'sql') {
			return 'INT(11) NOT NULL';
		}
	}
	
	/*
	Override getSqlValue to ensure that in the json case, the value is json-encoded
	*/
	public final function getSqlValue($prepare = false) {
		$db = App::$instance->getDb();
		$format = is_array($this->format) ? $this->format['format'] : $this->format;
		
		$optVals = $this->getValue();
		
		if ($format === 'json') {
			$val = json_encode($optVals);
		} else if ($format === 'sql') {
			
			//TODO: SQL saving; remove previous values and then upload new ones to option and return insert-id
			$table = $this->format['table'];
			$ref = $this->format['ref'];
			$key = $this->format['key'];
			$val = $this->format['val'];
			$max = $db->fetch("SELECT MAX(`$ref`) AS `Max` FROM `$table`");
			$max = $max['Max'];
			
			foreach ($optVals as $k => $optVal) {
				$db->query("INSERT INTO `$table` SET `$key`=:key, `$val`=:val", array(
					':key' => $k,
					':val' => $optVal
				));
			}
		}
		
		return $db->escape($val);
	}
	
	public function validate($val) {
		return null;
	}

	public function listControlAttributes($params = array()) {
		return array();
	}
}

class MultiCheckInput extends CompoundInput {
	public $options;
	public $range;
	
	public function MultiCheckInput($key, $options = array()) {
		parent::CompoundInput($key);
		$this->setOptions($options);
		$this->range = null;
	}
	
	public function setOptions($options) {
		$this->options = array();
		$count = 0;
		foreach ($options as $k => $v) {
			$key = is_numeric($k) ? "opt-$count" : $k;
			$inp = $this->addInput(new CheckInput($key));
			$inp->hasDbRelevance = false;
			$this->options[] = array(
				'input' => $inp,
				'desc' => $v
			);
			$count++;
		}
	}
	
	public function validate($val) {
		if (empty($val)) $val = array();
		if (!is_array($val)) return 'incorrect-input-format';
		foreach ($val as $k => $v) {
			//TODO: This if is messy as hell, clean it up!
			if (!empty($v) 
				&& !array_search($v, $this->options)
				&& $v !== 'on'
				&& !isset($this->options[$k])) return 'received-invalid-option('.$v.')';
		}
		
		if ($this->range !== null) {
			$num = count($val);
			if ($num < $this->range->min) return 'too-few-options-selected';
			if ($num > $this->range->max) return 'too-many-options-selected';
		}
		
		return null;
	}
	
	public function writeOptions() {
		foreach ($this->options as $v) {
			$input = $v['input'];
			$desc = $v['desc'];
			echo '<label>';
			$input->writeInput();
			echo $desc.'</label>';
		}
	}
	
	//SHORTHAND FUNCTION; exactly the same as $this->writeOptions();
	public final function wOptions() {
		$this->writeOptions();
	}
}

class ListInput extends CompoundInput {
	public $template;
	private $addHandler;
	private $remHandler;
	public $length;
	public $range;
	
	public function ListInput($key, $template, $format = 'json') {
		parent::CompoundInput($key, $format);
		$this->template = $template;
		
		$pass = $this;
		$this->addHandler = function($term) use($pass) { $pass->addItem(); };
		$this->remHandler = function($term) use($pass) { $pass->remItem($term); };
		
		$this->range = new Range(1, 20);
	}
	
	/*
	The idea here is to persistently add a new input; get the old array from the
	session, add another entry into it by duplicating $this->template, set the value
	of $this to this new extended array, and then push the value back to the session
	*/
	public function addItem() {
		$pulledValue = $this->sessionPull();
		if (empty($pulledValue)) $pulledValue = array();
		
		if (count($pulledValue) >= $this->range->max) return false;
		
		$dup = $this->template->duplicate(array('{n}' => ''.count($pulledValue)));
		$value = $dup->getValue();
		$pulledValue[$dup->getKey()] = $value;
		
		$this->setValue($pulledValue);
		$this->sessionPush(); //Push back the extended value
		return array(
			'key' => $dup->getKey(),
			'val' => $value
		);
	}
	
	/*
	One addition is that the submit $term of the button needs to be parsed to
	determine the appropriate index. Remove buttons have a term in the format of:
	
		|	remove-{$this->getKey()}-{$n}
	
	Where $n is the index of the option to remove.
	
	Similar to $this->addItem(); retrieve the old value-array from the session, 
	unset the appropriate index in this array, and then push the reduced array back 
	into the session.
	
	An addition is needed because after unsetting a value, the array keys will not 
	be ordered correctly. To make matters trickier, the array is associative but 
	each key has a numeric component, and these numeric components need to be
	ordered in the array. For this reason a loop is used along with altering the 
	template's key ( through $this->template->alter() ) to re-key the array.
	
	This function returns true/false based on whether $key validly identifies a
	value in this ListInput.
	*/
	public function remItem($key) {
		/*
		The submit name of a remove button may need to be parsed to determine the 
		appropriate index. Remove buttons have a term in the format of:
		
			|	remove-{$this->getKey()}-{$n}
		
		Where $n is the index of the option to remove.
		*/
		if (substr($key, 0, 7) === 'remove-') {
			$matches = array(); //Stores the results of the regex search
			$search = 'remove-'.preg_quote($this->getKey(), '~');
			if (preg_match("~$search-([0-9]+)~", $key, $matches)) {
				//The index is the numeric part of $term, matched by the above regex
				$key = $this->template->alterKey(array('{n}' => $matches[1]));
			}
		} else if (is_numeric($key)) {
			$key = $this->template->alterKey(array('{n}' => $key));
		}
		
		//Check if the index that was requested to be removed exists
		$pulledValue = $this->sessionPull();
		if (!isset($pulledValue[$key])) return false;
		
		if (count($pulledValue) <= $this->range->min) return false;
		
		
		//Unset the item that was requested to be removed
		unset($pulledValue[$key]);
		
		//Set value to the new value, and push to session
		$this->setValue($pulledValue);
		$this->sessionPush();
		return true;
	}
	
	/*
	ListInputs have strange array-keys because they are both associative AND ordered.
	The numeric components of the keys must be consecutive for the ListInput. This
	function takes a value for the input that is not necessarily consecutive, and
	orders it.
	*/
	private function rekeyValue($val) {
		if (empty($val)) return $val;
		
		$ret = array(); $count = 0;
		foreach ($val as $v) $ret[$this->template->alterKey(array('{n}' => $count++))] = $v;
		return $ret;
	}
	
	/*
	Setting a ListInput's value can cause extra Input children to be generated in order
	to store the full amount of data.
	*/
	public function setValue($val) {
		$this->remInputs();
		
		$this->length = count($val);
		for ($i = 0; $i < $this->length; $i++) {
			$inp = $this->addInput($this->template->duplicate(array('{n}' => "$i")));
			
			$rem = $this->addInput(new SubmitInput("remove-{$this->key}-$i"));
			$rem->onClick = $this->remHandler;
		}
		
		$add = $this->addInput(new SubmitInput("add-{$this->key}"));
		$add->onClick = $this->addHandler;
		
		//Always process values through $this->rekeyValue($val) before sending them to superclass methods
		parent::setValue($this->rekeyValue($val));
		
		//Run this in order to ensure any ContainerInput functionality is applied to the new children
		$this->initializeInput();
	}
	
	public function ajaxAction($data) {
		if (!isset($data['action'])) return array(
			'code' => 1, 
			'err' => 'missing "action" param',
			'msg' => 'the "action" param may be "rem" or "add" indicating whether you would like to add an item or remove an item from the list'
		);
		
		$act = $data['action'];
		if ($act === 'rem') {
			if (!isset($data['key'])) return array(
				'code' => 1, 
				'err' => 'missing "key" param',
				'msg' => 'the "key" param may either be the index of the item in the list, or the "name" attribute of the removal button corresponding to that row'
			);
			if ($this->remItem($data['key'])) {
				return array('msg' => 'successfully removed item');
			} else {
				return array('msg' => 'could not remove the item - it may not exist server-side');
			}
		} else if ($act === 'add') {
			$keyVal = $this->addItem();
			
			if ($keyVal === false) {
				return array(
					'code' => 1,
					'err' => 'cannot add item to list'
				);
			} else {
				return array(
					'msg' => 'successfully added item',
					'key' => $keyVal['key'],
					'val' => $keyVal['val']
				);
			}
		} else {
			return array(
				'code' => 1,
				'err' => 'invalid "action" param',
				'msg' => 'the "action" param should either be the string "rem" or "add"'
			);
		}
		
		return array('msg' => 'echo', 'paramsReceived' => $data);
	}	
	
	public function getNthInput($n) {
		return $this->getInput(str_replace('{n}', "$n", $this->template->key));
	}
	
	public function getNthRemoveButton($n) {
		return $this->getInput("remove-{$this->key}-$n");
	}
	
	public function getAddButton() {
		return $this->getInput("add-{$this->key}");
	}
	
	/*
	ListInputs never have an empty value; the minimum the value can contain is
	an array containing the (uninitialized) template value (result of $this->template->getValue())
	*/
	public function getValue() {
		$ret = parent::getValue();
		if (is_array($ret) && !empty($ret)) return $ret;
		//TODO: Here's the issue, an empty array comes through first
		return array($this->template->duplicate(array('{n}' => 0))->getValue());
	}
	
	public function listControlAttributes($params = array()) {
 		return array('data-list-length' => $this->length);
	}
}

class SubmitInput extends Input {
	//The name of this submit element - every submit element has the same key, but they have different names
	public $name;
	//Closure that is run when this submit button is pressed client-side
	public $onClick = null;
	
	public function SubmitInput($name) {
		parent::Input($name);
		$this->hasDbRelevance = false;
		$this->name = $name;
	}
	
	public function getSqlDeclaration() {
		return 'VARCHAR(45) NOT NULL';
	}
	
	public function validate($val) {
		return null;
	}
	
	public function listControlAttributes($params = array()) {
		return array('value' => $this->name) + parent::listControlAttributes($params);
	}
	
	public function getControlAttributes($params = array()) {
		$output = parent::getControlAttributes($params);
		
		//The new name attribute ought to only consider the root (form) key, followed by "_submit"
		$keys = $this->getKeyHeirarchy();
		$newName = htmlspecialchars($keys[0].'/_submit');
		
		return preg_replace('~name="[^"]*"~', 'name="'.$newName.'"', $output);
	}

	public function writeInput($attrs = array()) {
		echo '<input type="submit" ';
		$this->wAttrs($attrs);
		echo '/>';
	}
	
	public function writeButton($attrs = array()) {
		if (is_string($attrs)) {
			$text = $attrs;
			$attrs = array();
		} else if (isset($attrs['text'])) {
			$text = $attrs['text'];
			unset($attrs['text']);
		} else {
			$text = $this->getKey();
		}
		echo '<button ';
		$this->wAttrs($attrs);
		echo '>'.$text.'</button>';
	}
	
	//SHORTHAND FUNCTION; exactly the same as $this->writeButton($attrs);
	public function wButton($attrs = array()) {
		$this->writeButton($attrs);
	}
}

abstract class FileInput extends Input {
	//Limit in kb of filesize
	public $size;
	
	public function FileInput($key) {
		parent::Input($key);
		$this->size = new Range(0, 1000);
	}
	
}