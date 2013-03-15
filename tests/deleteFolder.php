#!/usr/bin/php
<?php
/**
 * Prints items in a list
 * 
 * @example ./deleteFolder.php http://sharepoint/sites/Test/DocumentLibrary/TestFolder
 */
require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

if($argc < 2) {
	die("Usage: 'deleteFolder.php <path>'\n");
}
$path = $argv[1];

$cfg = parse_ini_file('config.ini',true);

echo "* Deleting folder '$path' on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$sp->deleteFolder($path);

