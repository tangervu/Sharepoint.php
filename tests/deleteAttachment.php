#!/usr/bin/php
<?php
/**
 * Add attachment to a list item
 * 
 * @example ./deleteAttachment.php {747BAF1B-73E2-45ED-9173-2AB0D4E11F30} 12 http://sharepoint/sites/Test/Lists/Sample%20List/12/test_file.txt
 */
require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

if($argc < 4) {
	die("Usage: 'deleteAttachment.php <list-name> <item-id> <url>'\n");
}
$listName = $argv[1];
$itemName = $argv[2];
$url = $argv[3];

$cfg = parse_ini_file('config.ini',true);

echo "* Deleting attachment '$url' in list '$listName' item '$itemName' on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$path = $sp->deleteAttachment($listName, $itemName, $url);


