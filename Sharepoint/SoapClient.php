<?php
/**
 * SoapClient object tuned to work with NTLM authentication
 * 
 * @author Tuomas Angervuori <tuomas.angervuori@gmail.com>
 * @license http://opensource.org/licenses/LGPL-3.0 LGPL v3
 */

namespace Sharepoint;

require_once(dirname(__FILE__) . '/Connection.php');
require_once(dirname(__FILE__) . '/Exception.php');

class SoapClient extends \SoapClient {
	
	protected $conn;
	
	public function __construct(Connection $conn, $wsdl, array $options = array()) {
		$this->conn = $conn;
		$settings = array(
			'soap_version' => \SOAP_1_2,
			'exceptions' => true,
			'trace' => 1
		);
		foreach($options as $key => $value) {
			$settings[$key] = $value;
		}
		parent::__construct($wsdl, $settings);
	}
	
	public function __doRequest($request, $location, $action, $version) {
		$url = parse_url($location);
		$path = $url['path']; //FIXME ääkkösiä sisältävät osoitteet menevät tässä rikki, pitää urlenkoodata...
		
		$item = $url['path'];
		if(isset($url['query'])) {
			$item .= '?' . $url['query'];
		}
		if($version == \SOAP_1_2) {
			$headers = array(
				'Content-Type' => 'application/soap+xml; charset=utf-8'
			);
		}
		else {
			$headers = array(
				'Content-Type' => 'text/xml; charset=utf-8',
				'SOAPAction' => '"' . $action . '"'
			);
		}
		
		$this->__last_request_headers = array();
		foreach($headers as $key => $value) {
			$this->__last_request_headers[] = $key . ': ' . $value;
		}
		
		try {
			$result = $this->conn->post($item, $request, $headers);
		}
		catch(ConnectionException $e) {
			$dom = new \DOMDocument();
			$dom->loadXML($e->getMessage());
			$str = 'SOAP returned an error: ';
			foreach($dom->getElementsByTagName('errorstring') as $element) {
				$str .= $element->nodeValue;
			}
			throw new SoapClientException(trim($str), $e->getCode());
		}
		return $result['body'];
	}
}

/**
 * Exceptions thrown from SoapClient
 */
class SoapClientException extends Exception { }