#!/usr/bin/php
<?php
/**
 * Display details of a list
 * 
 * @example ./listInfo.php {8D936FEE-1FE3-44DB-90B3-6DA60C1B523C}
 */

require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

if($argc < 2) {
	die("Usage: 'listInfo.php <list-name>'\n");
}
$listName = $argv[1];

$cfg = parse_ini_file('config.ini',true);

echo "* List details in library '$listName' on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$details = $sp->getList($listName);

print_r($details); //TODO format the output...


