#!/usr/bin/php
<?php
/**
 * Make a copy of a file inside Sharepoint
 * 
 * @example ./copyFile.php http://sharepoint/sites/Test/Lists/Sample%20List/12/original_file.txt http://sharepoint/sites/Test/Lists/Sample%20List/12/new_file.txt
 */
require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

if($argc < 3) {
	die("Usage: 'copyFile.php <orig-url> <new-url>'\n");
}
$origFile = $argv[1];
$newFile = $argv[2];

$cfg = parse_ini_file('config.ini',true);

echo "* Copying file '$origFile' as '$newFile' on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$sp->copyFile($origFile, $newFile);


