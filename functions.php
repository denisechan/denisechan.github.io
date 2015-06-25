<?php


// check users login
function login($user,$pass) {
	if ($user) {
		$r = query("SELECT * FROM `users` WHERE `username` LIKE '$user' AND `password` LIKE '$pass' LIMIT 1");
		if (mysql_num_rows($r)) {
			return(true);
		} else {
			return(false);
		}
	}
}


// database connection
function connect() {
	$db = mysql_connect("localhost","qer","123456") or die(mysql_error());
	mysql_select_db("qer",$db) or die(mysql_error());
	return($db);
}

function query($q) {
	$db = connect();
	$r = mysql_query($q);
	return($r);
}
