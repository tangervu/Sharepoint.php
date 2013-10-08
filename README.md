Sharepoint.php
==============

A PHP class to utilize Sharepoint web services.

Cabaple to authenticate using the default NTLM authentication used in Sharepoint. 

The code has been combined and slightly modifies from various other PHP/Sharepoint projects. The development is still in it's early stages so major changes can be expected.
Also any help would be appreciated.

Example
-------

```php
<?php

//Connect to a Sharepoint site
$sp = new Sharepoint('http//example.sharepoint.site/sites/Test/','username','password');

//Display all the libraries on the site
$libraries = $sp->getListCollection();

//Display contents of a library
$libraryItems = $sp->getListItems('{<listGuid>}');

//Download a file
$data = $sp->getFile('http//example.sharepoint.site/sites/Test/DocumentLibrary/example.txt');

//etc...
```

License
-------
LGPL v3
