<?php 
include("functions.php");

// The bouncer!
if (!login($_COOKIE['user'],$_COOKIE['pass']) ) {
	header("Location: login.php?e=5");
}