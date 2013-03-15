#!/usr/bin/php
<?php
/**
 * Prints out groups used in site
 */
require_once('../Sharepoint.php');

header('Content-Type: text/plain; charset=utf-8');

$cfg = parse_ini_file('config.ini',true);

echo "* Group collection on site '{$cfg['site']}' (username: '{$cfg['username']}'):\n\n";
$sp = new Sharepoint($cfg['site'],$cfg['username'],$cfg['password']);
$groups = $sp->getGroupCollectionFromSite();
print_r($groups);
