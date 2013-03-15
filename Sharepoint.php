<?php
/**
 * A PHP class to utilize Sharepoint web interfaces (SOAP/HTTP etc)
 * 
 * NOTE This class hasn't been properly tested and was originally meant for personal use only. If you find any bugs
 * or missing features, please fix those issues and send me the patches.
 *
 * @link http://msdn.microsoft.com/en-us/library/dd878586%28v=office.12%29.aspx
 * @link http://your.sharepoint.site/_vti_bin/Lists.asmx
 * 
 * @note SoapClient/cURL seem to have some issues with NTLM authentication. This class tries to circumvent those.
 * 
 * @author Tuomas Angervuori <tuomas.angervuori@gmail.com>
 */

require_once(dirname(__FILE__) . '/Sharepoint/Exception.php');
require_once(dirname(__FILE__) . '/Sharepoint/Connection.php');
require_once(dirname(__FILE__) . '/Sharepoint/SoapClient.php');

class Sharepoint {
	
	const CHECKIN_MINOR = 0;
	const CHECKIN_MAJOR = 1;
	const CHECKIN_OVERWRITE = 2;
	
	protected $url;
	protected $user;
	protected $pass;
	
	protected $conn; //connection object to the defined sharepoint site
	protected $soapClients = array();
	protected $tmpWsdlFiles = array();
	
	public $tmpDir = '/tmp';
	public $debug = false; //Print debug informatio to console
	
	/**
	 * @param $url Base url for sharepoint site (eg. http://sharepoint.site.invalid/sites/Project Site/)
	 * @param $user Username for the site 
	 * @param $pass Password for the site
	 */
	public function __construct($url, $user = null, $pass = null) {
		$this->url = $url;
		$this->user = $user;
		$this->pass = $pass;
	}
	
	/**
	 * Clean up created tmp files
	 */
	public function __destruct() {
		foreach($this->tmpWsdlFiles as $file) {
			unlink($file);
		}
	}
	
	/**
	 * @returns Sharepoint\Connection object that can handle NTLM authentication used in Sharepoint
	 */
	public function getConnection() {
		if(!$this->conn) {
			$urlParts = parse_url($this->url);
			if(!isset($urlParts['scheme']) || strtolower($urlParts['scheme']) == 'http') {
				$port = 80;
			}
			else if(strtolower($urlParts['scheme']) == 'https') {
				$port = 443;
			}
			else {
				throw new Sharepoint\Exception("Unknown protocol '{$urlParts['scheme']}'");
			}
			$this->conn = new Sharepoint\Connection($urlParts['host'], $this->user, $this->pass, $port);
		}
		$this->conn->debug = $this->debug;
		return $this->conn;
	}
	
	/**
	 * Returns the WSDL definition for the requested section
	 * 
	 * @param $section The section 
	 * @returns string WSDL xml 
	 * @link http://msdn.microsoft.com/en-us/library/dd878586%28v=office.12%29.aspx
	 */
	public function getWsdl($section = 'Lists') {
		$conn = $this->getConnection();
		$item = self::_getPath($this->url . '/_vti_bin/' . $section . '.asmx?WSDL');
		$response = $conn->get($item);
		return $response['body'];
	}
	
	/**
	 * @param $section The section
	 * @returns Sharepoint\SoapClient SoapClient that communicates with Sharepoint
	 */
	public function getSoapClient($section = 'Lists') {
		if(!isset($this->soapClients[$section])) {
			if($this->user) {
				$settings['login'] = $this->user;
			}
			if($this->pass) {
				$settings['password'] = $this->pass;
			}
			//Load WSDL into tmp file, SoapClient doesn't handle NTLM auth
			if(!isset($this->tmpWsdlFiles[$section])) {
				$this->tmpWsdlFiles[$section] = tempnam($this->tmpDir,'ShareSoap_' . $section . '_');
				file_put_contents($this->tmpWsdlFiles[$section], $this->getWsdl($section));
			}
			$this->soapClients[$section] = new Sharepoint\SoapClient($this->getConnection(), $this->tmpWsdlFiles[$section]);
		}
		return $this->soapClients[$section];
	}
	
	
	
	/**
	 * Returns the names and GUIDs for all lists in the site
	 * 
	 * @returns array Names and GUIDs for all the lists in the site
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/lists.lists.getlistcollection%28v=office.12%29.aspx
	 */
	public function getListCollection() {
		$soap = $this->getSoapClient('Lists');
		$xml = $soap->GetListCollection()->GetListCollectionResult->any;
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		$result = array();
		foreach($dom->getElementsByTagName('Lists') as $lists) {
			foreach($lists->getElementsByTagName('List') as $list) {
				$id = $list->getAttribute('ID');
				$result[$id] = array();
				foreach($list->attributes as $attr) {
					$result[$id][$attr->name] = $attr->value;
				}
			}
		}
		return $result;
	}
	
	/**
	 * Returns the views available for the specified list
	 * 
	 * @param $list Name or GUID of the list
	 * @returns array Names, URLs and GUIDS for the views available
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/views.views.getviewcollection%28v=office.12%29
	 */
	public function getViewCollection($list) {
		$soap = $this->getSoapClient('Views');
		$options = array('listName' => $list);
		$xml = $soap->GetViewCollection($options)->GetViewCollectionResult->any;
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		$result = array();
		foreach($dom->getElementsByTagName('Views') as $views) {
			foreach($views->getElementsByTagName('View') as $view) {
				$id = $view->getAttribute('Name');
				$result[$id] = array();
				foreach($view->attributes as $attr) {
					$result[$id][$attr->name] = $attr->value;
				}
			}
		}
		return $result;
	}
	
	
	
	/**
	 * Returns a schema for the specified list
	 * 
	 * @param $list Name or GUID of the list
	 * @returns array Information from the list
	 * 
	 * @note Some re-thinking might be needed for this method...
	 * @link http://msdn.microsoft.com/en-us/library/lists.lists.getlist%28v=office.12%29.aspx
	 */
	public function getList($list) {
		$options = array(
			'listName' => $list
		);
		$soap = $this->getSoapClient('Lists');
		$xml = $soap->GetList($options)->GetListResult->any;
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		$result = array();
		foreach($dom->getElementsByTagName('List') as $list) {
			$result['Meta'] = array();
			foreach($list->attributes as $attr) {
				$result['Meta'][$attr->name] = $attr->value;
			}
			foreach($list->getElementsByTagName('Fields') as $fields) {
				$result['Fields'] = array();
				foreach($fields->getElementsByTagName('Field') as $field) {
					$id = $field->getAttribute('ID');
					$result['Fields'][$id] = array();
					foreach($field->attributes as $attr) {
						$result['Fields'][$id][$attr->name] = $attr->value;
						if($field->childNodes) {
							///FIXME ChildXML does not work...
							$result['Fields'][$id]['ChildXML'] = array();
							foreach($field->childNodes as $node) {
								$result['Fields'][$id]['ChildXML'][$node->nodeName] = $node->nodeValue;
							}
						}
					}
				}
			}
			foreach($list->getElementsByTagName('RegionalSettings') as $regionalSettings) {
				$result['RegionalSettings'] = array();
				foreach($regionalSettings->childNodes as $node) {
					$result['RegionalSettings'][$node->nodeName] = $node->nodeValue;
				}
			}
			foreach($list->getElementsByTagName('ServerSettings') as $serverSettings) {
				$result['ServerSettings'] = array();
				foreach($serverSettings->childNodes as $node) {
					$result['ServerSettings'][$node->nodeName] = $node->nodeValue;
				}
			}
		}
		return $result;
	}
	
	/**
	 * Returns information about items in the list based on the specified query. 
	 * 
	 * @param $list List GUID or name (eq. {A279BA14-E7F0-4B3E-A0DE-0CA3AA534B85})
	 * @param $view Name of the view, null = default
	 * @param $options Other options (rowLimit, viewFields, queryOptions, WebID)
	 * @returns array Library contents
	 *
	 * @link http://msdn.microsoft.com/en-us/library/lists.lists.getlistitems%28v=office.12%29.aspx
	 * @bug Paging not supported (see ListItemCollectionPositionNext)
	 */
	public function getListItems($list, $view = null, array $options = null) {
		$soap = $this->getSoapClient('Lists');
		if(!$options) {
			$options = array();
		}
		$options['listName'] = $list;
		if($view) {
			$options['viewName'] = $view;
		}
		$xml = $soap->GetListItems($options)->GetListItemsResult->any;
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		$result = array();
		foreach($dom->getElementsByTagName('listitems') as $listItems) {
			foreach($listItems->getElementsByTagNameNS('urn:schemas-microsoft-com:rowset','data') as $data) {
				foreach($data->getElementsByTagNameNS('#RowsetSchema','row') as $row) {
					$id = $row->getAttribute('ows_ID');
					$result[$id] = array();
					foreach($row->attributes as $attr) {
						$name = $attr->name;
						if(substr($name,0,5) == 'ows__') {
							$name = substr($name,5);
						}
						else if(substr($name,0,4) == 'ows_') {
							$name = substr($name,4);
						}
						$result[$id][$name] = $attr->value;
					}
				}
			}
		}
		return $result;
	}
	
	///TODO add/modify/delete list items
	
	/**
	 * Checks out a file
	 * 
	 * @param $file URL to the file (eg. http://your.sharepoint.site/sites/Test Site/Shared Documents/Sample File.txt)
	 * @param $toLocal Is the file checked out for offline editing (default = true)
	 * @param $timestamp string or DateTime object for last modifying time for the file
	 * @returns bool Was the checkout successful
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/lists.lists.checkoutfile%28v=office.12%29.aspx
	 */
	public function checkOutFile($file, $toLocal = true,  $timestamp = null) {
		$options = array(
			'pageUrl' => $file
		);
		if($toLocal) {
			$options['toLocal'] = 'True';
		}
		else {
			$options['toLocal'] = 'False';
		}
		if($timestamp) {
			if(!($timestamp instanceof \DateTime)) {
				$timestamp = new \DateTime($timestamp);
			}
			$options['lastmodified'] = $timestamp->format('d M Y H:i:s e');
		}
		$soap = $this->getSoapClient('Lists');
		return $soap->CheckOutFile($options)->CheckOutFileResult;
	}
	
	/**
	 * Checks in a file
	 * 
	 * @param $file URL to the file (eg. http://your.sharepoint.site/sites/Test Site/Shared Documents/Sample File.txt)
	 * @param $comment Comment for check in
	 * @param $type Check in type (CHECKIN_MINOR, CHECKIN_MAJOR (default), CHECKIN_OVERWRITE)
	 * @returns bool Was the check in successful
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/lists.lists.checkinfile%28v=office.12%29.aspx
	 */
	public function checkInFile($file, $comment = null, $type = 1) {
		$options = array(
			'pageUrl' => $file,
			'CheckinType' => $type
		);
		if($comment) {
			$options['comment'] = $comment;
		}
		$soap = $this->getSoapClient('Lists');
		return $soap->CheckInFile($options)->CheckInFileResult;
	}
	
	/**
	 * Undo check out
	 * 
	 * @param $file URL to the file (eg. http://your.sharepoint.site/sites/Test Site/Shared Documents/Sample File.txt)
	 * @returns bool Was the undo check out successful
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/lists.lists.undocheckout%28v=office.12%29.aspx
	 */
	public function undoCheckOut($file) {
		$options = array(
			'pageUrl' => $file
		);
		$soap = $this->getSoapClient('Lists');
		return $soap->UndoCheckOut($options)->UndoCheckOutResult;
	}
	
	
	
	/**
	 * Download a file
	 * 
	 * @param $url URL to the file (eg. http://your.sharepoint.site/sites/Test Site/Shared Documents/Sample File.txt)
	 * @returns data Contents of the file
	 * @todo Make this accept also relative urls
	 */
	public function getFile($url) {
		$conn = $this->getConnection();
		$result = $conn->get($url);
		return $result['body'];
	}
	
	/**
	 * Upload a file into document library
	 * 
	 * @param $url URL to the file location in Sharepoint
	 * @param $data Contents of the file
	 * @todo Can this be done with soap? Would be nice to be able to also add details to the file (description etc)
	 */
	public function putFile($url, $data) {
		$conn = $this->getConnection();
		$conn->put($url, $data);
	}
	
	/**
	 * Copy a file into new location inside Sharepoint
	 * 
	 * @param $sourceUrl The URL of the file to be copied
	 * @param $destinationUrl The URL to which the file should be copied
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/copy.copy.copyintoitemslocal(v=office.12)
	 */
	public function copyFile($sourceUrl, $destinationUrl) {
		$soap = $this->getSoapClient('Copy');
		$options = array(
			'SourceUrl' => $sourceUrl,
			'DestinationUrls' => array($destinationUrl)
		);
		$response = $soap->CopyIntoItemsLocal($options)->Results->CopyResult;
		if($response->ErrorCode != 'Success') {
			throw new Sharepoint\Exception($response->ErrorMessage);
		}
	}
	
	/**
	 * Delete a file from document library
	 * 
	 * @param $url URL of the file to be deleted
	 */
	public function deleteFile($url) {
		$conn = $this->getConnection();
		$conn->delete($url);
	}
	
	/**
	 * Gets file information
	 * 
	 * @param $file URL to the file (eg. http://your.sharepoint.site/sites/Test Site/Shared Documents/Sample File.txt)
	 * @returns array File information
	 * @note Is this really the best way to fetch file information...?
	 */
	public function getFileInfo($file) {
		$conn = $this->getConnection();
		$result = $conn->head($file);
		return $result['headers'];
	}
	
	/**
	 * Retrieves information on file versions
	 * 
	 * @param $file URL of the file (eg. "Shared Documents/Sample File.txt")
	 * @return array List of file versions
	 * @link http://msdn.microsoft.com/en-us/library/versions.versions.getversions(v=office.12)
	 */
	public function getVersions($file) {
		$soap = $this->getSoapClient('Versions');
		$options = array('fileName' => $file);
		$xml = $soap->GetVersions($options)->GetVersionsResult->any;
		
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		$versions = array();
		foreach($dom->getElementsByTagName('results') as $results) {
			foreach($results->getElementsByTagName('result') as $result) {
				$version = $result->getAttribute('version');
				$versions[$version] = array();
				foreach($result->attributes as $attr) {
					$versions[$version][$attr->name] = $attr->value;
				}
			}
		}
		return $versions;
	}
	
	
	
	/**
	 * Creates a folder into a document list
	 * 
	 * @param $path Path to the folder (eg. "Shared%20documents/Test")
	 * @returns bool 
	 * @throws Sharepoint\Exception creating folder failed
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/ms774480(v=office.12)
	 */
	public function createFolder($path) {
		$soap = $this->getSoapClient('Dws');
		$options = array('url' => $path);
		$xml = $soap->CreateFolder($options)->CreateFolderResult;
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		//Got error
		foreach($dom->getElementsByTagName('Error') as $item) {
			throw new Sharepoint\Exception("Creating folder failed: " . $item->nodeValue);
		}
		return true;
	}
	
	/**
	 * Deletes a folder from a document list
	 * 
	 * @param $path Path to the folder (eg. "Shared%20documents/Test")
	 * @returns bool 
	 * @throws Sharepoint\Exception creating folder failed
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/ms772957(v=office.12)
	 */
	public function deleteFolder($path) {
		$soap = $this->getSoapClient('Dws');
		$options = array('url' => $path);
		$xml = $soap->DeleteFolder($options)->DeleteFolderResult;
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		//Got error
		foreach($dom->getElementsByTagName('Error') as $item) {
			throw new Sharepoint\Exception("Deleting folder failed: " . $item->nodeValue);
		}
		return true;
	}
	
	
	
	/**
	 * Get list of list item attachments
	 * 
	 * @param $list List id (eg. {747BAF1B-73E2-45ED-9173-2AB0D4E11F30})
	 * @param $item Item id (eg. 2)
	 * @returns array List of attachments
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/lists.lists.getattachmentcollection(v=office.12)
	 */
	public function getAttachmentCollection($list, $item) {
		$soap = $this->getSoapClient('Lists');
		$options = array(
			'listName' => $list,
			'listItemID' => $item
		);
		$xml = $soap->GetAttachmentCollection($options)->GetAttachmentCollectionResult->any;
		
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		$result = array();
		foreach($dom->getElementsByTagName('Attachments') as $attachments) {
			foreach($attachments->getElementsByTagName('Attachment') as $attachment) {
				$result[] = $attachment->nodeValue;
			}
		}
		return $result;
	}
	
	/**
	 * Add attachment to a list item
	 * 
	 * @param $list List id (eg. {747BAF1B-73E2-45ED-9173-2AB0D4E11F30})
	 * @param $item Item id (eg. 2)
	 * @param $filename Filename of the attachement (eg. test.txt)
	 * @param $data Contents of the attachments
	 * @returns string Path of the attachment
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/lists.lists.addattachment(v=office.12)
	 */
	public function addAttachment($list, $item, $filename, $data) {
		$soap = $this->getSoapClient('Lists');
		$options = array(
			'listName' => $list,
			'listItemID' => $item,
			'fileName' => $filename,
			'attachment' => $data
		);
		return $soap->AddAttachment($options)->AddAttachmentResult;
	}
	
	/**
	 * Delete attachment from a list item
	 * 
	 * @param $list List id (eg. {747BAF1B-73E2-45ED-9173-2AB0D4E11F30})
	 * @param $item Item id (eg. 2)
	 * @param $url Path of the attachment (eg. http://sharepoint/sites/Testisite/Lists/List test/Attachments/2/test.txt)
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/lists.lists.deleteattachment(v=office.12)
	 */
	public function deleteAttachment($list, $item, $url) {
		$soap = $this->getSoapClient('Lists');
		$options = array(
			'listName' => $list,
			'listItemID' => $item,
			'url' => $url
		);
		$soap->DeleteAttachment($options);
	}
	
	
	
	/**
	 * Search content 
	 * 
	 * @param $string Search string
	 * @param $startAt
	 * @param $count maximum number of results
	 * @returns array 
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/search.queryservice.query(v=office.12)
	 */
	public function search($string, $startAt = null, $count = null) {
		$soap = $this->getSoapClient('Search');
		
		//Create the query xml
		$dom = new \DOMDocument();
		
		$queryPacketElement = $dom->createElement('QueryPacket');
		$queryElement = $dom->createElement('Query');
		$contextElement = $dom->createElement('Context');
		
		$queryTextElement = $dom->createElement('QueryText', $string);
		$queryTypeAttribute = $dom->createAttribute('type');
		$queryTypeAttribute->value = 'STRING';
		$queryTextElement->appendChild($queryTypeAttribute);
		
		$contextElement->appendChild($queryTextElement);
		$queryElement->appendChild($contextElement);
		
		if($startAt || $count) {
			$rangeElement = $dom->createElement('Range');
			if($startAt) {
				$startAtElement = $dom->createElement('StartAt');
				$startAtElement->nodeValue = $startAt;
				$rangeElement->appendChild($startAtElement);
			}
			if($count) {
				$countElement = $dom->createElement('Count');
				$countElement->nodeValue = $count;
				$rangeElement->appendChild($countElement);
			}
			$queryElement->appendChild($rangeElement);
		}
		
		$queryPacketElement->appendChild($queryElement);
		
		$dom->appendChild($queryPacketElement);
		$queryXml = $dom->saveHTML();
		
		//Execute the query
		$xml = $soap->Query(array('queryXml' => $queryXml))->QueryResult;
		
		//Parse the result
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		
		$result = array(
			'Meta' => array(),
			'Results' => array()
		);
		foreach($dom->getElementsByTagName('ResponsePacket') as $responsePacket) {
			foreach($responsePacket->getElementsByTagName('Response') as $response) {
				foreach($response->getElementsByTagName('Status') as $status) {
					$result['Meta']['Status'] = $status->nodeValue;
				}
				foreach($response->getElementsByTagName('Range') as $range) {
					foreach(array('StartAt','Count','TotalAvailable') as $item) {
						foreach($range->getElementsByTagName($item) as $node) {
							$result['Meta'][$item] = $node->nodeValue;
						}
					}
					foreach($range->getElementsByTagName('Results') as $results) {
						foreach($results->getElementsByTagName('Document') as $document) {
							$link = null;
							foreach($document->getElementsByTagName('Action') as $action) {
								foreach($action->getElementsByTagName('LinkUrl') as $linkUrl) {
									$link = $linkUrl->nodeValue;
								}
							}
							$result['Results'][$link] = array();
							foreach(array('Title','Description','Date') as $item) {
								foreach($document->getElementsByTagName($item) as $node) {
									$result['Results'][$link][$item] = $node->nodeValue;
								}
							}
						}
					}
				}
			}
		}
		return $result;
	}
	
	
	
	/**
	 * Returns information about the collection of groups for the current site collection
	 * 
	 * @returns array List of groups
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/ms774594%28v=office.12%29.aspx
	 */
	public function getGroupCollectionFromSite() {
		$soap = $this->getSoapClient('Usergroup');
		$xml = $soap->GetGroupCollectionFromSite(); //->GetGroupCollectionFromSiteResult->any['GetGroupCollectionFromSite'];
		$result = array();
		///FIXME for some strange reason I couldn't parse the response with SoapClient...
		$xml = $soap->__getLastResponse();
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		foreach($dom->getElementsByTagNameNS('http://www.w3.org/2003/05/soap-envelope','Envelope') as $envelope) {
			foreach($envelope->getElementsByTagNameNS('http://www.w3.org/2003/05/soap-envelope','Body') as $body) {
				foreach($body->getElementsByTagName('GetGroupCollectionFromSiteResponse') as $response) {
					foreach($response->getElementsByTagName('GetGroupCollectionFromSiteResult') as $siteResult) {
						foreach($siteResult->getElementsByTagName('GetGroupCollectionFromSite') as $collection) {
							foreach($collection->getElementsByTagName('Groups') as $groups) {
								foreach($groups->getElementsByTagName('Group') as $group) {
									$id = $group->getAttribute('ID');
									$result[$id] = array();
									foreach($group->attributes as $attr) {
										$result[$id][$attr->name] = $attr->value;
									}
								}
							}
						}
					}
				}
			}
		}
		return $result;
	}
	
	/**
	 * Returns information about the collection of users in the specified group
	 * 
	 * @param $group Group name
	 * @returns array List of users
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/ms772554%28v=office.12%29.aspx
	 */
	public function getUserCollectionFromGroup($group) {
		$options = array(
			'groupName' => $group
		);
		$soap = $this->getSoapClient('Usergroup');
		$xml = $soap->GetUserCollectionFromGroup($options); //->GetUserCollectionFromGroupResult->any['GetUserCollectionFromGroup'];
		///FIXME for some strange reason I couldn't parse the response with SoapClient...
		$xml = $soap->__getLastResponse();
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		foreach($dom->getElementsByTagNameNS('http://www.w3.org/2003/05/soap-envelope','Envelope') as $envelope) {
			foreach($envelope->getElementsByTagNameNS('http://www.w3.org/2003/05/soap-envelope','Body') as $body) {
				foreach($body->getElementsByTagName('GetUserCollectionFromGroupResponse') as $response) {
					foreach($response->getElementsByTagName('GetUserCollectionFromGroupResult') as $siteResult) {
						foreach($siteResult->getElementsByTagName('GetUserCollectionFromGroup') as $collection) {
							foreach($collection->getElementsByTagName('Users') as $users) {
								foreach($users->getElementsByTagName('User') as $user) {
									$id = $user->getAttribute('ID');
									$result[$id] = array();
									foreach($user->attributes as $attr) {
										$result[$id][$attr->name] = $attr->value;
									}
								}
							}
						}
					}
				}
			}
		}
		return $result;
	}
	
	/**
	 * Returns information about the specified user
	 * 
	 * @param $login User login (eg. DOMAIN\login)
	 * @returns array User info
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/ms774637%28v=office.12%29.aspx
	 */
	public function getUserInfo($login) {
		$options = array(
			'userLoginName' => $login
		);
		$soap = $this->getSoapClient('Usergroup');
		$soap->GetUserInfo($options); //->GetUserInfoResult->any['GetUserInfo'];
		$result = array();
		
		///FIXME for some strange reason I couldn't parse the response with SoapClient...
		$xml = $soap->__getLastResponse();
		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		foreach($dom->getElementsByTagNameNS('http://www.w3.org/2003/05/soap-envelope','Envelope') as $envelope) {
			foreach($envelope->getElementsByTagNameNS('http://www.w3.org/2003/05/soap-envelope','Body') as $body) {
				foreach($body->getElementsByTagName('GetUserInfoResponse') as $response) {
					foreach($response->getElementsByTagName('GetUserInfoResult') as $siteResult) {
						foreach($siteResult->getElementsByTagName('GetUserInfo') as $info) {
							foreach($info->getElementsByTagName('User') as $user) {
								foreach($user->attributes as $attr) {
									$result[$attr->name] = $attr->value;
								}
							}
						}
					}
				}
			}
		}
		return $result;
	}
	
	/**
	 * Returns the path component and parameters from the url
	 */
	protected static function _getPath($url) {
		$url = parse_url($url);
		$path = $url['path'];
		if(isset($url['query'])) {
			$path .= '?' . $url['query'];
		}
		$path = str_replace('//','/',$path);
		return $path;
	}
}
