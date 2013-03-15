#!/usr/bin/php
<?php
/**
 * Upload a file into Sharepoint
 * 
 * @example ./copyFile.php http://sharepoint/sites/Test/Lists/Sample%20List/12/original_file.txt http://sharepoint/sites/Test/Lists/Sample%20List/12/new_file.txt
 */
require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

if($argc < 3) {
	die("Usage: 'putFile.php <local-file> <destination-url>'\n");
}
$localFile = $argv[1];
$destUrl = $argv[2];
$data = file_get_contents($localFile);
if(!$data) {
	die("Could not open '$localFile'");
}

$cfg = parse_ini_file('config.ini',true);

echo "* Uploading file '$localFile' as '$destUrl' on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$sp->putFile($destUrl, $data);


