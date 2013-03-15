#!/usr/bin/php
<?php
/**
 * Perform a search
 * 
 * @example ./search.php foo
 */
require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

if($argc < 2) {
	die("Usage: 'search.php <query>'\n");
}
$query = $argv[1];

$cfg = parse_ini_file('config.ini',true);

echo "* Searching '$query' on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$result = $sp->search($query);
print_r($result);
