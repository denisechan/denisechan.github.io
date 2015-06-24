<?php
/*
Returns true/false depending on if $key is set in $arr
*/
function arrHas($arr, $key) {
	return is_array($arr) && isset($arr[$key]);
}
/*
Returns $arr[$val], or $def in the case that $arr[$val] is not set (isset($arr[$val]) === false)
*/
function arrVal($arr, $val, $def = null) {
	return arrHas($arr, $val) ? $arr[$val] : $def;
}
/*
Return the string representation of a value
*/
function strRep($val) {
	if ($val === true) return '_TRUE';
	else if ($val === false) return'_FALSE';
	else if (empty($val)) {
		if ($val === null) return '_NULL';
		else if (is_array($val)) return '_EMPTY_ARRAY';
		else if (is_string($val)) return '_EMPTY_STRING';
		else if (is_object($val)) return '_EMPTY_OBJECT('.get_class($val).')';
	}
	
	return $val;
}
/*
Returns the markup to show a debug panel for $val
*/
function debugHtml($val, $back = '#000000', $font = '#55ff55') {
	return '<pre style="background-color: '.$back.'; color: '.$font.'; width: 80%; max-height: 450px; overflow-y: auto; margin-top: 0; padding: 2px 5px;">'.print_r(strRep($val), true).'</pre>';
}
/*
Writes a debug panel for $val
*/
function show($val, $title = null, $back = '#000000', $font = '#55ff55') {
	if ($title !== null) {
		$styles = 'font-size: 20px; margin: 0; padding: 10px; border-top-right-radius: 30px; background-color: '.$back.'; width: 250px; color: '.$font.';';
		echo "<h1 style=\"$styles\">$title</h1>";
	}
	echo debugHtml($val, $back, $font);
}
/*
Returns the markup to represent a single frame of a stack trace 
(don't call this function directly)
*/
function exceptionFrameHtml($frame) {
	return '<div style="padding-bottom: 2px; margin-bottom: 8px; border-bottom: 1px solid #aaaaaa;">'.
		'<div>File: <span style="font-size: 100%; color: #cc0000;">'.$frame['file'].'</div>'.
		'<div>Line: <span style="font-size: 100%; color: #cc0000;">'.$frame['line'].'</div>'.
		'<div>Function: <span style="font-size: 100%; color: #cc0000;">'.$frame['function'].'</div>'.
	'</div>';
}
/*
Returns the absolute patch locating the specified file.
$file is a filepath relative to the root of this application
*/
function getFile($file) {
	return '/'.ROOT_DIR."/$file";
}
/*
Returns the URL for the specified resource.
$url is relative to the app's "resource" folder.

If your app looks like:

	|	domain.com
	|		|
	|		|---Application
	|				|
	|				|---resource
	|				|		|
	|				|		|---images
	|				|		|		|
	|				|		|		|---logo.png
	|				|		|		|---background.jpg
	|				|		|
	|				|		|---styles
	|				|				|
	|				|				|---styles1.css
	|				|				|---styles2.css
	|				|
	|				|
	|				|---config.php
	|				|---index.php
	|				|---layout.php

	
You can get the logo image with 
	|	getResource('images/logo.png');
Or the 2nd stylesheet with
	|	getResource('styles2.css');
	
The values returned from these calls are fit to be put directly into the html, as the
"href" of an image element, or the "src" of a script element.
*/
function getResource($url) {
	return getUrl("resource/$url");
}
function wResource($url) {
	echo getResource($url);
}
/*
Similar to getResource($url) but is relative to the application instead of
the application's "resource" directory.
*/
function getUrl($url) {
	$doubleSlash = strrpos($url, '//');
	if ($doubleSlash !== false) return substr($url, $doubleSlash);
	
	return '/'.ROOT_URL."/$url";
}
function wUrl($url) {
	echo htmlspecialchars(getUrl($url));
}

function isAbsUrl($url) {
	return substr($url, 0, 1) === '/' 
		|| substr($url, 0, 7) === 'http://' 
		|| substr($url, 0, 8) === 'https://';
}
function getExtension($url) {
	$matches = array();
	if (!preg_match('~(?:.+)\.([a-zA-Z0-9]+)$~', $url, $matches)) return null;
	return $matches[1];
}

function htmlAttributes($attrs) {
	$attrStr = array();
	foreach ($attrs as $k => $v) $attrStr[] = $k.'="'.htmlspecialchars($v).'"';
	return implode(' ', $attrStr);
}

function errorReport($error) {
	if (DEBUG) throw new Exception($error);
}

//Generates the initiation vector for encryption/decryption
function genInitVector() {
	//Parameters to this are $size (of the initiation vector) and $source
    return mcrypt_create_iv(
		mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB), 
		MCRYPT_RAND);
}
//Encrypt a string (by default uses the ENCRYPT_KEY constant as the key)
function encrypt($str, $key = null) {
	if ($key === null) $key = ENCRYPT_KEY;
	//Takes a cipher-mode, encryption key, unencrypted string, mode, and initiation vector
    $enc = mcrypt_encrypt(
		MCRYPT_BLOWFISH, 
		$key, utf8_encode($str), 
		MCRYPT_MODE_ECB, 
		genInitVector());
	
	return strtr(rtrim(base64_encode($enc), '='), '+/', '-_');
}
//Decrypt an encrypted string (by default uses the ENCRYPT_KEY constant as the key)
function decrypt($str, $key = null) {
	if ($key === null) $key = ENCRYPT_KEY;
	
	$str = base64_decode(strtr($str, '-_', '+/'));
	
	//Takes a cipher-mode, encryption key, encrypted string, mode, and initiation vector
    return mcrypt_decrypt(
		MCRYPT_BLOWFISH, 
		$key, $str, 
		MCRYPT_MODE_ECB, 
		genInitVector());
}

//Validation functions
function isValidEmail($str) {
	return preg_match('~[a-z._]+@[a-z._]+\.[a-z]{2,6}~', strtolower($str));
}
