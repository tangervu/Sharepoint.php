<?php
/**
 * A class for HTTP connections to Sharepoint
 * 
 * For some reason Curl couldn't handle NTLM authentication...
 * 
 * Modified the code from http://forums.fedoraforum.org/showthread.php?t=230535
 * 
 * @author Tuomas Angervuori <tuomas.angervuori@gmail.com>
 * @license http://opensource.org/licenses/LGPL-3.0 LGPL v3
 */

namespace Sharepoint;

require_once(dirname(__FILE__) . '/Exception.php');

class Connection {
	
	/**
	 * Copyless: DJ Maze http://dragonflycms.org/
	 *
	 * http://davenport.sourceforge.net/ntlm.html
	 * http://www.dereleased.com/2009/07/25/post-via-curl-under-ntlm-auth-learn-from-my-pain/
	 */
	
	//flags
	const FLAG_UNICODE        = 0x00000001; // Negotiate Unicode
	const FLAG_OEM            = 0x00000002; // Negotiate OEM
	const FLAG_REQ_TARGET     = 0x00000004; // Request Target
//		const FLAG_               = 0x00000008; // unknown
	const FLAG_SIGN           = 0x00000010; // Negotiate Sign
	const FLAG_SEAL           = 0x00000020; // Negotiate Seal
	const FLAG_DATAGRAM       = 0x00000040; // Negotiate Datagram Style
	const FLAG_LM_KEY         = 0x00000080; // Negotiate Lan Manager Key
	const FLAG_NETWARE        = 0x00000100; // Negotiate Netware
	const FLAG_NTLM           = 0x00000200; // Negotiate NTLM
//	const FLAG_               = 0x00000400; // unknown
	const FLAG_ANONYMOUS      = 0x00000800; // Negotiate Anonymous
	const FLAG_DOMAIN         = 0x00001000; // Negotiate Domain Supplied
	const FLAG_WORKSTATION    = 0x00002000; // Negotiate Workstation Supplied
	const FLAG_LOCAL_CALL     = 0x00004000; // Negotiate Local Call
	const FLAG_ALWAYS_SIGN    = 0x00008000; // Negotiate Always Sign
	const FLAG_TYPE_DOMAIN    = 0x00010000; // Target Type Domain
	const FLAG_TYPE_SERVER    = 0x00020000; // Target Type Server
	const FLAG_TYPE_SHARE     = 0x00040000; // Target Type Share
	const FLAG_NTLM2          = 0x00080000; // Negotiate NTLM2 Key
	const FLAG_REQ_INIT       = 0x00100000; // Request Init Response
	const FLAG_REQ_ACCEPT     = 0x00200000; // Request Accept Response
	const FLAG_REQ_NON_NT_KEY = 0x00400000; // Request Non-NT Session Key
	const FLAG_TARGET_INFO    = 0x00800000; // Negotiate Target Info
//	const FLAG_               = 0x01000000; // unknown
//	const FLAG_               = 0x02000000; // unknown
//	const FLAG_               = 0x04000000; // unknown
//	const FLAG_               = 0x08000000; // unknown
//	const FLAG_               = 0x10000000; // unknown
	const FLAG_128BIT         = 0x20000000; // Negotiate 128
	const FLAG_KEY_EXCHANGE   = 0x40000000; // Negotiate Key Exchange
	const FLAG_56BIT          = 0x80000000; // Negotiate 56
	
	protected $user;
	protected $password;
	protected $domain;
	protected $workstation;
	
	protected $host;
	protected $port;
	protected $socket;
	protected $msg1;
	protected $msg3;
	
	public $last_send_headers;
	
	public $debug = false;
	
	function __construct($host, $user = null, $password = null, $port = 80, $domain='', $workstation='') {
		
		if (!function_exists($function='mcrypt_encrypt')) {
			throw new ConnectionException('NTLM Error: the required "mcrypt" extension is not available');
		}
		if($port == 443) {
			$socketHost = 'ssl://' . $host;
		}
		else {
			$socketHost = $host;
		}
		if (!$this->socket = fsockopen($socketHost, $port, $errno, $errstr, 30)) {
			throw new ConnectionException("NTLM_HTTP failed to open. Error {$errno}: {$errstr}");
		}
		$userData = explode('@',$user);
		if(isset($userData[1])) {
			$domain = $userData[1];
		}
		
		$this->host = $host;
		$this->port = $port;
		$this->user = $userData[0];
		$this->password = $password;
		$this->domain = $domain;
		$this->workstation = $workstation;
	}
	
	function __destruct() {
		if ($this->socket) {
			fclose($this->socket);
			$this->socket = null;
		}
	}
	
	public function get($uri, array $headers = array()) {
		return $this->request($uri, 'get', null, $headers);
	}
	
	public function post($uri, $data, array $headers = array()) {
		return $this->request($uri, 'post', $data, $headers);
	}
	
	public function put($uri, $data, array $headers = array()) {
		return $this->request($uri, 'put', $data, $headers);
	}
	
	public function head($uri, array $headers = array()) {
		return $this->request($uri, 'head', null, $headers);
	}
	
	public function delete($uri, array $headers = array()) {
		return $this->request($uri, 'delete', null, $headers);
	}
	
	public function request($uri, $method = null, $data = null, array $headers = array()) {
		
		if(!$method) {
			if($data) {
				$method = 'post';
			}
			else {
				$method = 'get';
			}
		}
		if(strtolower($method) == 'head') {
			$hasBody = false;
		}
		else {
			$hasBody = true;
		}
		
		$sendHeaders = $headers;
		if($this->msg3) {
			$sendHeaders['Authorization'] = 'NTLM ' . $this->msg3;
		}
		if($data) {
			$sendHeaders['Content-Length'] = strlen($data);
		}
		$this->_sendHeaders($uri, $sendHeaders, $method);
		
		if($data) {
			$this->_sendData($data);
		}
		
		$response = $this->_getResponse($hasBody);
		
		if (401 === $response['status']) {
			if(!$this->user) {
				throw new ConnectionException('Login required: no username defined');
			}
			$this->msg3 = null;
			if(!$response['NTLM']) {
				$sendHeaders = $headers;
				// Send The Type 1 Message
				$sendHeaders['Authorization'] = 'NTLM ' . $this->TypeMsg1();
				if($data) {
					$sendHeaders['Content-Length'] = 0;
				}
				$this->_sendHeaders($uri, $sendHeaders, $method);
				$response = $this->_getResponse($hasBody);
				if(!$response['NTLM']) {
					throw new ConnectionException('NTLM Authorization failed at step 1');
				}
			}
			if($response['NTLM']) {
				$sendHeaders = $headers;
				// Send The Type 3 Message
				$sendHeaders['Authorization'] = 'NTLM ' . $this->TypeMsg3($response['NTLM']);
				if($data) {
					$sendHeaders['Content-Length'] = strlen($data);
				}
				$this->_sendHeaders($uri, $sendHeaders, $method);
				if($data) {
					$this->_sendData($data);
				}
				
				$response = $this->_getResponse($hasBody);
			}
		}
		
		if ($response['status'] >= 400) {
			throw new ConnectionException($response['statusMessage'], $response['status']);
		}
		
		return $response;
	}
	
	protected function _getResponse($hasBody = true) {
		$response = array(
			'status' => null,
			'statusMessage' => null,
			'headers' => array(),
			'NTLM' => null,
			'body' => null
		);
		
		$isHead = true;
		$isHttpStatus = true;
		$contentLength = null;
		$contentLoaded = 0;
		$maxContent = 1024;
		
		while(!feof($this->socket)) {
			
			//HTTP response headers section
			if($isHead) {
				
				$line = fgets($this->socket, $maxContent);
				if($this->debug) {
					echo $line;
				}
				
				$line = trim($line);
				//First line contains the HTTP status code
				if($isHttpStatus) {
					$parts = explode(' ', $line, 3);
					$response['status'] = (int)$parts[1];
					$response['statusMessage'] = $parts[2];
					$isHttpStatus = false;
				}
				else {
					if($line == '') {
						$isHead = false;
					}
					else {
						list($name, $value) = explode(': ',$line,2);
						if(strtolower($name) == 'content-length') {
							$contentLength = (int)$value;
						}
						if(strtolower($name) == 'www-authenticate' && substr($value,0,4) == 'NTLM') {
							$response['NTLM'] = substr($value,5);
						}
						
						if(isset($response['headers'][$name])) {
							if(!is_array($response['headers'][$name])) {
								$response['headers'][$name] = array($response['headers'][$name]);
							}
							$response['headers'][$name][] = $value;
						}
						else {
							$response['headers'][$name] = $value;
						}
					}
				}
			}
			
			//Response body
			else {
				//No body in HTTP response
				if($contentLength == 0 || !$hasBody) {
					break;
				}
				
				if(is_null($response['body'])) {
					$response['body'] = '';
				}
				
				
				$loadLen = $maxContent;
				if($contentLength) {
					$loadLen = $contentLength - strlen($response['body']) + 1;
					if($loadLen > $maxContent) {
						$loadLen = $maxContent;
					}
				}
				
				$line = fgets($this->socket, $loadLen);
				if($this->debug) {
					echo $line;
				}
				
				$response['body'] .= $line;
				if($contentLength) {
					if($contentLength <= strlen($response['body'])) {
						break;
					}
				}
			}
		}
		return $response;
	}
	
	protected function _getHeaderString($uri, array $headers, $method = 'get') {
		$headerString = strtoupper($method) . ' ' . $uri . " HTTP/1.1\r\n";
		$headerString .= 'Host: ' . $this->host . "\r\n";
		
		if($headers) {
			foreach($headers as $key => $value) {
				if(is_array($value)) {
					foreach($value as $subValue) {
						$headerString .= $key . ': ' . $subValue . "\r\n";
					}
				}
				else {
					$headerString .= $key . ': ' . $value . "\r\n";
				}
			}
		}
		
		return trim($headerString);
	}
	
	protected function _sendHeaders($uri, array $headers, $method = 'get') {
		$headerString = $this->_getHeaderString($uri, $headers, $method);
		$this->last_send_headers = $headerString;
		if($this->debug) {
			echo $headerString . "\r\n\r\n";
		}
		return fwrite($this->socket, $headerString . "\r\n\r\n");
	}
	
	protected function _sendData($data) {
		if($this->debug) {
			echo $data;
		}
		return fwrite($this->socket, $data);
	}
	
	public function TypeMsg1() {
		if (!$this->msg1) {
			$flags = self::FLAG_UNICODE | self::FLAG_OEM | self::FLAG_REQ_TARGET | self::FLAG_NTLM;
//			self::FLAG_ALWAYS_SIGN | self::FLAG_NTLM2 | self::FLAG_128BIT | self::FLAG_56BIT;
			$offset = 32;
			$d_length = strlen($this->domain);
			$d_offset = $d_length ? $offset : 0;
			if ($d_length) {
				$offset += $d_length;
				$flags |= self::FLAG_DOMAIN;
			}
			
			$w_length = strlen($this->workstation);
			$w_offset = $w_length ? $offset : 0;
			if ($w_length) {
				$offset += $w_length;
				$flags |= self::FLAG_WORKSTATION;
			}
			
			$this->msg1 = base64_encode(
				"NTLMSSP\0".
				"\x01\x00\x00\x00". // Type 1 Indicator
				pack('V',$flags).
				pack('vvV',$d_length,$d_length,$d_offset).
				pack('vvV',$w_length,$w_length,$w_offset).
//				"\x00\x00\x00\x0f". // OS Version ???
				$this->workstation.
				$this->domain
			);
		}
		return $this->msg1;
	}
	
	protected function TypeMsg3($ntlm_response) {
		if (!$this->msg3) {
			//Handel the server Type 2 Message
			$msg2 = base64_decode($ntlm_response);
			if (version_compare(phpversion(), '5.5.0', '<')) {
				$format = 'a8ID/Vtype/vtarget_length/vtarget_space/Vtarget_offset/Vflags/a8challenge/a8context/vtargetinfo_length/vtargetinfo_space/Vtargetinfo_offset/cOS_major/cOS_minor/vOS_build';
			} else {
				$format = 'A8ID/Vtype/vtarget_length/vtarget_space/Vtarget_offset/Vflags/A8challenge/A8context/vtargetinfo_length/vtargetinfo_space/Vtargetinfo_offset/cOS_major/cOS_minor/vOS_build';
			}
			$headers = unpack($format, $msg2);
			if ($headers['ID'] != 'NTLMSSP') {
				throw new ConnectionException('Incorrect NTLM Type 2 Message');
				return false;
			}
			$headers['challenge'] = substr($msg2,24,8);
//			$headers['challenge'] = str_pad($headers['challenge'],8,"\0");
			
			//Build Type 3 Message
			$flags  = self::FLAG_UNICODE | self::FLAG_NTLM | self::FLAG_REQ_TARGET;
			$offset = 64;
			$challenge = $headers['challenge'];
			
			$target = self::ToUnicode($this->domain);
			$target_length  = strlen($target);
			$target_offset  = $offset;
			$offset += $target_length;
			
			$user = self::ToUnicode($this->user);
			$user_length = strlen($user);
			$user_offset  = $user_length ? $offset : 0;
			$offset += $user_length;
			
			$workstation = self::ToUnicode($this->workstation);
			$workstation_length = strlen($workstation);
			$workstation_offset = $workstation_length ? $offset : 0;
			$offset += $workstation_length;
			
			$lm = ''; // self::DESencrypt(str_pad(self::LMhash($this->password),21,"\0"), $challenge);
			$lm_length = strlen($lm);
			$lm_offset = $lm_length ? $offset : 0;
			$offset += $lm_length;
			
			$password = self::ToUnicode($this->password);
//			$ntlm = self::DESencrypt(str_pad(mhash(MHASH_MD4,$password,true),21,"\0"), $challenge);
			$ntlm = self::DESencrypt(str_pad(hash('md4',$password,true),21,"\0"), $challenge);
			$ntlm_length = strlen($ntlm);
			$ntlm_offset = $ntlm_length ? $offset : 0;
			$offset += $ntlm_length;
			
			$session = '';
			$session_length = strlen($session);
			$session_offset = $session_length ? $offset : 0;
			$offset += $session_length;
			
			$this->msg3 = base64_encode(
				"NTLMSSP\0".
				"\x03\x00\x00\x00".
				pack('vvV',$lm_length,$lm_length,$lm_offset).
				pack('vvV',$ntlm_length,$ntlm_length,$ntlm_offset).
				pack('vvV',$target_length,$target_length,$target_offset).
				pack('vvV',$user_length,$user_length,$user_offset).
				pack('vvV',$workstation_length,$workstation_length,$workstation_offset).
				pack('vvV',$session_length,$session_length,$session_offset).
				pack('V',$flags).
				$target.
				$user.
				$workstation.
				$lm.
				$ntlm
			);
		}
		return $this->msg3;
	}
	
	protected static function LMhash($str) {
		$string = strtoupper(substr($str,0,14));
		return self::DESencrypt($str);
	}
	
	protected static function DESencrypt($str, $challenge='KGS!@#$%') {
		$is = mcrypt_get_iv_size(MCRYPT_DES, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($is, MCRYPT_RAND);
		
		$des = '';
		$l = strlen($str);
		$str = str_pad($str,ceil($l/7)*7,"\0");
		for ($i=0; $i<$l; $i+=7) {
			$bin = '';
			for ($p=0; $p<7; ++$p) {
				$bin .= str_pad(decbin(ord($str[$i+$p])),8,'0',STR_PAD_LEFT);
			}
			
			$key = '';
			for ($p=0; $p<56; $p+=7) {
				$s = substr($bin,$p,7);
				$key .= chr(bindec($s.((substr_count($s,'1') % 2) ? '0' : '1')));
			}
			
			$des .= mcrypt_encrypt(MCRYPT_DES, $key, $challenge, MCRYPT_MODE_ECB, $iv);
		}
		return $des;
	}
	
	protected static function ToUnicode($ascii) {
		return mb_convert_encoding($ascii,'UTF-16LE','auto');
		$str = '';
		for ($a=0; $a<strlen($ascii); ++$a) { $str .= substr($ascii,$a,1)."\0"; }
		return $str;
	}
}

/**
 * Exceptions thrown from Connection
 */
class ConnectionException extends Exception { }
