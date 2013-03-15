#!/usr/bin/php
<?php
/**
 * Prints items in a list
 * 
 * @example ./listItems.php {8D936FEE-1FE3-44DB-90B3-6DA60C1B523C}
 */
require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

if($argc < 2) {
	die("Usage: 'listItems.php <list-name> [<viewName>]'\n");
}
$listName = $argv[1];
$viewName = null;
if(isset($argv[2])) {
	$viewName = $argv[2];
}

$cfg = parse_ini_file('config.ini',true);

echo "* List items in library '$listName' on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$items = $sp->getListItems($listName, $viewName);

print_r($items); //Don't know really whats inside the list, so printing everything

