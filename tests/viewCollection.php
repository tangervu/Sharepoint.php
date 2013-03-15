#!/usr/bin/php
<?php
/**
 * Displays the list of available views for a library
 * 
 * @example ./viewCollection.php {8D936FEE-1FE3-44DB-90B3-6DA60C1B523C}
 */

require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

if($argc < 2) {
	die("Usage: 'viewCollection.php <list-name>'\n");
}
$listName = $argv[1];

$cfg = parse_ini_file('config.ini',true);

echo "* Views available in library '$listName' on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$details = $sp->getViewCollection($listName);

print_r($details); //TODO format the output...


