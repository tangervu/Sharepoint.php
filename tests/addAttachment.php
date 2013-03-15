#!/usr/bin/php
<?php
/**
 * Add attachment to a list item
 * 
 * @example ./addAttachment.php {747BAF1B-73E2-45ED-9173-2AB0D4E11F30} 12 test_file.txt
 */
require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

if($argc < 4) {
	die("Usage: 'addAttachment.php <list-name> <item-id> <file>'\n");
}
$listName = $argv[1];
$itemName = $argv[2];
$file = $argv[3];

$fileName = basename($file);
$data = file_get_contents($file);
if(!$data) {
	die("Could not read file '$file'");
}

$cfg = parse_ini_file('config.ini',true);

echo "* Adding attachment '$file' in list '$listName' item '$itemName' on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$path = $sp->addAttachment($listName, $itemName, $fileName, $data);

echo "Attachment added as '$path'\n\n";

