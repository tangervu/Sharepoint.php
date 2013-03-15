#!/usr/bin/php
<?php
/**
 * Delete a file from a document list
 * 
 * @example ./copyFile.php http://sharepoint/sites/Test/Lists/Sample%20List/12/original_file.txt http://sharepoint/sites/Test/Lists/Sample%20List/12/new_file.txt
 */
require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

if($argc < 2) {
	die("Usage: 'deleteFile.php <file-url>'\n");
}
$url = $argv[1];

$cfg = parse_ini_file('config.ini',true);

echo "* Deleting file '$url' from site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$sp->deleteFile($url);


