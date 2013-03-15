#!/usr/bin/php
<?php
/**
 * Lists information about file versions
 * 
 * @example ./getVersions.php Shared%20Documents/test.txt
 */
require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

if($argc < 2) {
	die("Usage: 'getVersions.php <filename>'\n");
}
$path = $argv[1];

$cfg = parse_ini_file('config.ini',true);

echo "* Fetching file versions on filename '$path' on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
print_r($sp->getVersions($path));
