<?php
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