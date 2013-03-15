#!/usr/bin/php
<?php
/**
 * Prints out list collection
 */
require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

$cfg = parse_ini_file('config.ini',true);

echo "* List collection on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$listCollection = $sp->getListCollection();

foreach($listCollection as $list) {
	echo 'ID: ' . $list['ID'] . "\n";
	echo 'Name: ' . $list['Name'] . "\n";
	echo 'Title: ' . $list['Title'] . "\n";
	echo 'Description: ' . $list['Description'] . "\n";
	echo 'DefaultViewUrl: ' . $list['DefaultViewUrl'] . "\n";
	echo "\n";
}
