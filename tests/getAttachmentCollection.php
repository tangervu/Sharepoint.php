#!/usr/bin/php
<?php
/**
 * Lists attachments of a list item
 * 
 * @example ./getAttachmentCollection.php {A279BA14-E7F0-4B3E-A0DE-0CA3AA534B85} 12
 */
require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

if($argc < 3) {
	die("Usage: 'getAttachmentCollection.php <list-name> <item-id>'\n");
}
$listName = $argv[1];
$itemName = $argv[2];

$cfg = parse_ini_file('config.ini',true);

echo "* Attachments in list '$listName' item '$itemName' on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$items = $sp->getAttachmentCollection($listName, $itemName);

print_r($items);

