<?php
// Warning: this php5 version is (probably) not actively maintained. (unlike the php7 version)
// declare(strict_types=1);
function hhb_tohtml($str) {
	return htmlentities ( $str, ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE | ENT_DISALLOWED, 'UTF-8', true );
}
function hhb_mustbe($type, $variable) {
	// should it be UnexpectedValueException or InvalidArgumentException?
	// going with UnexpectedValueException for now...
	if ($type === 'ip') {
		$dbg = filter_var ( $variable, FILTER_VALIDATE_IP );
		if ($dbg === false) {
			throw new UnexpectedValueException ( 'variable is NOT a valid ip address!' );
		}
		
		return $variable;
	} else if ($type === 'ipv4') {
		$dbg = filter_var ( $variable, FILTER_VALIDATE_IP, array (
				'flags' => FILTER_FLAG_IPV4,
				'options' => array (
						'default' => false 
				) 
		) );
		if ($dbg === false) {
			throw new UnexpectedValueException ( 'variable is NOT a valid ipv4 address!' );
		}
		
		return $variable;
	} else if ($type === 'ipv6') {
		$dbg = filter_var ( $variable, FILTER_VALIDATE_IP, array (
				'flags' => FILTER_FLAG_IPV6,
				'options' => array (
						'default' => false 
				) 
		) );
		if ($dbg === false) {
			throw new UnexpectedValueException ( 'variable is NOT a valid ipv6 address!' );
		}
		return $variable;
	}
	
	$actual_type = gettype ( $variable );
	if ($actual_type === 'unknown type') {
		// i dont know how this can happen, but it is documented as a possible return value of gettype...
		throw new Exception ( 'could not determine the type of the variable!' );
	}
	if ($actual_type === 'object') {
		if (! is_a ( $variable, $type )) {
			$dbg = get_class ( $variable );
			throw new UnexpectedValueException ( 'variable is an object which does NOT implement class: ' . $type . '. it is of class: ' . $dbg );
		}
		return $variable;
	}
	if ($actual_type === 'resource') {
		$dbg = get_resource_type ( $variable );
		if ($dbg !== $type) {
			throw new UnexpectedValueException ( 'variable is a resource, which is NOT of type: ' . $type . '. it is of type: ' . $dbg );
		}
		return $variable;
	}
	
	// now a few special cases
	if ($type === 'bool') {
		$parsed_type = 'boolean';
	} else if ($type === 'int') {
		$parsed_type = 'integer';
	} else if ($type === 'float') {
		$parsed_type = 'double';
	} else if ($type === 'null') {
		$parsed_type = 'NULL';
	} else {
		$parsed_type = $type;
	}
	
	if ($parsed_type !== $actual_type && $type !== $actual_type) {
		throw new UnexpectedValueException ( 'variable is NOT of type: ' . $type . '. it is of type: ' . $actual_type );
	}
	// ok, variable passed all tests.
	return $variable;
}
function hhb_var_dump() {
	// informative wrapper for var_dump
	// <changelog>
	// version 5 ( 1372510379573 )
	// v5, fixed warnings on PHP < 5.0.2 (PHP_EOL not defined),
	// also we can use xdebug_var_dump when available now. tested working with 5.0.0 to 5.5.0beta2 (thanks to http://viper-7.com and http://3v4l.org )
	// and fixed a (corner-case) bug with "0" (empty() considders string("0") to be empty, this caused a bug in sourcecode analyze)
	// v4, now (tries to) tell you the source code that lead to the variables
	// v3, HHB_VAR_DUMP_START and HHB_VAR_DUMP_END .
	// v2, now compat with.. PHP5.0 + i think? tested down to 5.2.17 (previously only 5.4.0+ worked)
	// </changelog>
	// <settings>
	$settings = array ();
	$PHP_EOL = "\n";
	if (defined ( 'PHP_EOL' )) { // for PHP >=5.0.2 ...
		$PHP_EOL = PHP_EOL;
	}
	
	$settings ['debug_hhb_var_dump'] = false; // if true, may throw exceptions on errors..
	$settings ['use_xdebug_var_dump'] = true; // try to use xdebug_var_dump (instead of var_dump) if available?
	$settings ['analyze_sourcecode'] = true; // false to disable the source code analyze stuff.
	                                         // (it will fallback to making $settings['analyze_sourcecode']=false, if it fail to analyze the code, anyway..)
	$settings ['hhb_var_dump_prepend'] = 'HHB_VAR_DUMP_START' . $PHP_EOL;
	$settings ['hhb_var_dump_append'] = 'HHB_VAR_DUMP_END' . $PHP_EOL;
	// </settings>
	
	$settings ['use_xdebug_var_dump'] = ($settings ['use_xdebug_var_dump'] && is_callable ( "xdebug_var_dump" ));
	$argv = func_get_args ();
	$argc = count ( $argv, COUNT_NORMAL );
	
	if (version_compare ( PHP_VERSION, '5.4.0', '>=' )) {
		$bt = debug_backtrace ( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );
	} else if (version_compare ( PHP_VERSION, '5.3.6', '>=' )) {
		$bt = debug_backtrace ( DEBUG_BACKTRACE_IGNORE_ARGS );
	} else if (version_compare ( PHP_VERSION, '5.2.5', '>=' )) {
		$bt = debug_backtrace ( false );
	} else {
		$bt = debug_backtrace ();
	}
	;
	$analyze_sourcecode = $settings ['analyze_sourcecode'];
	// later, $analyze_sourcecode will be compared with $config['analyze_sourcecode']
	// to determine if the reason was an error analyzing, or if it was disabled..
	$bt = $bt [0];
	// <analyzeSourceCode>
	if ($analyze_sourcecode) {
		$argvSourceCode = array (
				0 => 'ignore [0]...' 
		);
		try {
			if (version_compare ( PHP_VERSION, '5.2.2', '<' )) {
				throw new Exception ( "PHP version is <5.2.2 .. see token_get_all changelog.." );
			}
			;
			$xsource = file_get_contents ( $bt ['file'] );
			if (empty ( $xsource )) {
				throw new Exception ( 'cant get the source of ' . $bt ['file'] );
			}
			;
			$xsource .= "\n<" . '?' . 'php ignore_this_hhb_var_dump_workaround();'; // workaround, making sure that at least 1 token is an array, and has the $tok[2] >= line of hhb_var_dump
			$xTokenArray = token_get_all ( $xsource );
			// <trim$xTokenArray>
			$tmpstr = '';
			$tmpUnsetKeyArray = array ();
			ForEach ( $xTokenArray as $xKey => $xToken ) {
				if (is_array ( $xToken )) {
					if (! array_key_exists ( 1, $xToken )) {
						throw new LogicException ( 'Impossible situation? $xToken is_array, but does not have $xToken[1] ...' );
					}
					$tmpstr = trim ( $xToken [1] );
					if (empty ( $tmpstr ) && $tmpstr !== '0' /*string("0") is considered "empty" -.-*/ ) {
						$tmpUnsetKeyArray [] = $xKey;
						continue;
					}
					;
					switch ($xToken [0]) {
						case T_COMMENT :
						case T_DOC_COMMENT : // T_ML_COMMENT in PHP4 -.-
						case T_INLINE_HTML :
							{
								$tmpUnsetKeyArray [] = $xKey;
								continue;
							}
							;
						default :
							{
								continue;
							}
					}
				} else if (is_string ( $xToken )) {
					$tmpstr = trim ( $xToken );
					if (empty ( $tmpstr ) && $tmpstr !== '0' /*string("0") is considered "empty" -.-*/ ) {
						$tmpUnsetKeyArray [] = $xKey;
					}
					;
					continue;
				} else {
					// should be unreachable..
					// failed both is_array() and is_string() ???
					throw new LogicException ( 'Impossible! $xToken fails both is_array() and is_string() !! .. ' );
				}
				;
			}
			;
			ForEach ( $tmpUnsetKeyArray as $toUnset ) {
				unset ( $xTokenArray [$toUnset] );
			}
			;
			$xTokenArray = array_values ( $xTokenArray ); // fixing the keys..
			                                              // die(var_dump('die(var_dump(...)) in '.__FILE__.':'.__LINE__,'before:',count(token_get_all($xsource),COUNT_NORMAL),'after',count($xTokenArray,COUNT_NORMAL)));
			unset ( $tmpstr, $xKey, $xToken, $toUnset, $tmpUnsetKeyArray );
			// </trim$xTokenArray>
			$firstInterestingLineTokenKey = - 1;
			$lastInterestingLineTokenKey = - 1;
			// <find$lastInterestingLineTokenKey>
			ForEach ( $xTokenArray as $xKey => $xToken ) {
				if (! is_array ( $xToken ) || ! array_key_exists ( 2, $xToken ) || ! is_integer ( $xToken [2] ) || $xToken [2] < $bt ['line'])
					continue;
				$tmpkey = $xKey; // we don't got what we want yet..
				while ( true ) {
					if (! array_key_exists ( $tmpkey, $xTokenArray )) {
						throw new Exception ( '1unable to find $lastInterestingLineTokenKey !' );
					}
					;
					if ($xTokenArray [$tmpkey] === ';') {
						// var_dump(__LINE__.":SUCCESS WITH",$tmpkey,$xTokenArray[$tmpkey]);
						$lastInterestingLineTokenKey = $tmpkey;
						break;
					}
					// var_dump(__LINE__.":FAIL WITH ",$tmpkey,$xTokenArray[$tmpkey]);
					
					// if $xTokenArray has >=PHP_INT_MAX keys, we don't want an infinite loop, do we? ;p
					// i wonder how much memory that would require though.. over-engineering, err, time-wasting, ftw?
					if ($tmpkey >= PHP_INT_MAX) {
						throw new Exception ( '2unable to find $lastIntperestingLineTokenKey ! (PHP_INT_MAX reached without finding ";"...)' );
					}
					;
					++ $tmpkey;
				}
				break;
			}
			;
			if ($lastInterestingLineTokenKey <= - 1) {
				throw new Exception ( '3unable to find $lastInterestingLineTokenKey !' );
			}
			;
			unset ( $xKey, $xToken, $tmpkey );
			// </find$lastInterestingLineTokenKey>
			// <find$firstInterestingLineTokenKey>
			// now work ourselves backwards from $lastInterestingLineTokenKey to the first token where $xTokenArray[$tmpi][1] == "hhb_var_dump"
			// i doubt this is fool-proof but.. cant think of a better way (in userland, anyway) atm..
			$tmpi = $lastInterestingLineTokenKey;
			do {
				if (array_key_exists ( $tmpi, $xTokenArray ) && is_array ( $xTokenArray [$tmpi] ) && array_key_exists ( 1, $xTokenArray [$tmpi] ) && is_string ( $xTokenArray [$tmpi] [1] ) && strcasecmp ( $xTokenArray [$tmpi] [1], $bt ['function'] ) === 0) {
					// var_dump(__LINE__."SUCCESS WITH",$tmpi,$xTokenArray[$tmpi]);
					if (! array_key_exists ( $tmpi + 2, $xTokenArray )) { // +2 because [0] is (or should be) "hhb_var_dump" and [1] is (or should be) "("
						throw new Exception ( '1unable to find the $firstInterestingLineTokenKey...' );
					}
					;
					$firstInterestingLineTokenKey = $tmpi + 2;
					break;
					/* */
				}
				;
				// var_dump(__LINE__."FAIL WITH ",$tmpi,$xTokenArray[$tmpi]);
				-- $tmpi;
			} while ( - 1 < $tmpi );
			// die(var_dump('die(var_dump(...)) in '.__FILE__.':'.__LINE__,$tmpi));
			if ($firstInterestingLineTokenKey <= - 1) {
				throw new Exception ( '2unable to find the $firstInterestingLineTokenKey...' );
			}
			;
			unset ( $tmpi );
			// Note: $lastInterestingLineTokeyKey is likely to contain more stuff than only the stuff we want..
			// </find$firstInterestingLineTokenKey>
			// <rebuildInterestingSourceCode>
			// ok, now we have $firstInterestingLineTokenKey and $lastInterestingLineTokenKey....
			$interestingTokensArray = array_slice ( $xTokenArray, $firstInterestingLineTokenKey, (($lastInterestingLineTokenKey - $firstInterestingLineTokenKey) + 1) );
			unset ( $addUntil, $tmpi, $tmpstr, $tmpi, $argvsourcestr, $tmpkey, $xTokenKey, $xToken );
			$addUntil = array ();
			$tmpi = 0;
			$tmpstr = "";
			$tmpkey = "";
			$argvsourcestr = "";
			// $argvSourceCode[X]='source code..';
			ForEach ( $interestingTokensArray as $xTokenKey => $xToken ) {
				if (is_array ( $xToken )) {
					$tmpstr = $xToken [1];
					// var_dump($xToken[1]);
				} else if (is_string ( $xToken )) {
					$tmpstr = $xToken;
					// var_dump($xToken);
				} else {
					/* should never reach this */
					throw new LogicException ( 'Impossible situation? $xToken fails is_array() and fails is_string() ...' );
				}
				;
				$argvsourcestr .= $tmpstr;
				
				if ($xToken === '(') {
					$addUntil [] = ')';
					continue;
				} else if ($xToken === '[') {
					$addUntil [] = ']';
					continue;
				}
				;
				
				if ($xToken === ')' || $xToken === ']') {
					if (false === ($tmpkey = array_search ( $xToken, $addUntil, false ))) {
						$argvSourceCode [] = substr ( $argvsourcestr, 0, - 1 ); // -1 is to strip the ")"
						if (count ( $argvSourceCode, COUNT_NORMAL ) - 1 === $argc) /*-1 because $argvSourceCode[0] is bullshit*/ {
							break;
							/* We read em all! :D (.. i hope) */
						}
						;
						/* else... oh crap */
						throw new Exception ( 'failed to read source code of (what i think is) argv[' . count ( $argvSourceCode, COUNT_NORMAL ) . '] ! sorry..' );
					}
					unset ( $addUntil [$tmpkey] );
					continue;
				}
				
				if (empty ( $addUntil ) && $xToken === ',') {
					$argvSourceCode [] = substr ( $argvsourcestr, 0, - 1 ); // -1 is to strip the comma
					$argvsourcestr = "";
				}
				;
			}
			;
			// die(var_dump('die(var_dump(...)) in '.__FILE__.':'.__LINE__,
			// $firstInterestingLineTokenKey,$lastInterestingLineTokenKey,$interestingTokensArray,$tmpstr
			// $argvSourceCode));
			if (count ( $argvSourceCode, COUNT_NORMAL ) - 1 != $argc) /*-1 because $argvSourceCode[0] is bullshit*/ {
				throw new Exception ( 'failed to read source code of all the arguments! (and idk which ones i missed)! sorry..' );
			}
			;
			// </rebuildInterestingSourceCode>
		} catch ( Exception $ex ) {
			$argvSourceCode = array (); // clear it
			                            // TODO: failed to read source code
			                            // die("TODO N STUFF..".__FILE__.__LINE__);
			$analyze_sourcecode = false; // ERROR..
			if ($settings ['debug_hhb_var_dump']) {
				throw $ex;
			} else {
				/* exception ignored, continue as normal without $analyze_sourcecode */
			}
			;
		}
		unset ( $xsource, $xToken, $xTokenArray, $firstInterestingLineTokenKey, $lastInterestingLineTokenKey, $xTokenKey, $tmpi, $tmpkey, $argvsourcestr );
	}
	;
	// </analyzeSourceCode>
	$msg = $settings ['hhb_var_dump_prepend'];
	if ($analyze_sourcecode != $settings ['analyze_sourcecode']) {
		$msg .= ' (PS: some error analyzing source code)' . $PHP_EOL;
	}
	;
	$msg .= 'in "' . $bt ['file'] . '": on line "' . $bt ['line'] . '": ' . $argc . ' variable' . ($argc === 1 ? '' : 's') . $PHP_EOL; // because over-engineering ftw?
	if ($analyze_sourcecode) {
		$msg .= ' hhb_var_dump(';
		$msg .= implode ( ",", array_slice ( $argvSourceCode, 1 ) ); // $argvSourceCode[0] is bullshit.
		$msg .= ')' . $PHP_EOL;
	}
	// array_unshift($bt,$msg);
	echo $msg;
	$i = 0;
	foreach ( $argv as &$val ) {
		echo 'argv[' . ++ $i . ']';
		if ($analyze_sourcecode) {
			echo ' >>>' . $argvSourceCode [$i] . '<<<';
		}
		echo ':';
		
		if ($settings ['use_xdebug_var_dump']) {
			xdebug_var_dump ( $val );
		} else {
			var_dump ( $val );
		}
		;
	}
	
	echo $settings ['hhb_var_dump_append'];
	// call_user_func_array("var_dump",$args);
}
function hhb_return_var_dump() { // :string //works like var_dump, but returns a string instead of printing it.
	$args = func_get_args (); // for <5.3.0 support ...
	ob_start ();
	call_user_func_array ( 'var_dump', $args );
	return ob_get_clean ();
}

;
function hhb_bin2readable($data, $min_text_len = 3, $readable_min = 0x40, $readable_max = 0x7E) { // :string
                                                                                                  // TODO: better output..
	$ret = "";
	$strbuf = "";
	$i = 0;
	for($i = 0; $i < strlen ( $data ); ++ $i) {
		if ($min_text_len > 0 && ord ( $data [$i] ) >= $readable_min && ord ( $data [$i] ) <= $readable_max) {
			$strbuf .= $data [$i];
			continue;
		}
		if (strlen ( $strbuf ) >= $min_text_len && $min_text_len > 0) {
			$ret .= " " . $strbuf . " ";
		} elseif (strlen ( $strbuf ) > 0 && $min_text_len > 0) {
			$ret .= bin2hex ( $strbuf );
		}
		$strbuf = "";
		$ret .= bin2hex ( $data [$i] );
	}
	if (strlen ( $strbuf ) >= $min_text_len && $min_text_len > 0) {
		$ret .= " " . $strbuf . " ";
	} elseif (strlen ( $strbuf ) > 0 && $min_text_len > 0) {
		$ret .= bin2hex ( $strbuf );
	}
	$strbuf = "";
	return $ret;
}
function hhb_init() {
	static $firstrun = true;
	if ($firstrun !== true) {
		return;
	}
	$firstrun = false;
	error_reporting ( E_ALL );
	set_error_handler ( "hhb_exception_error_handler" );
	// ini_set("log_errors",'On');
	// ini_set("display_errors",'On');
	// ini_set("log_errors_max_len",'0');
	// ini_set("error_prepend_string",'<error>');
	// ini_set("error_append_string",'</error>'.PHP_EOL);
	// ini_set("error_log",__DIR__.DIRECTORY_SEPARATOR.'error_log.php.txt');
	assert_options ( ASSERT_ACTIVE, 1 );
	assert_options ( ASSERT_WARNING, 0 );
	assert_options ( ASSERT_QUIET_EVAL, 1 );
	assert_options ( ASSERT_CALLBACK, 'hhb_assert_handler' );
}
function hhb_exception_error_handler($errno, $errstr, $errfile, $errline) {
	if (! (error_reporting () & $errno)) {
		// This error code is not included in error_reporting
		return;
	}
	throw new ErrorException ( $errstr, 0, $errno, $errfile, $errline );
}
function hhb_assert_handler($file, $line, $code, $desc = null) {
	$errstr = 'Assertion failed at ' . $file . ':' . $line . ' ' . $desc . ' code: ' . $code;
	throw new ErrorException ( $errstr, 0, 1, $file, $line );
}
function hhb_combine_filepaths( /*...*/ ) { // :string
	$args = func_get_args ();
	if (count ( $args ) == 0) {
		return "";
	}
	
	$ret = "";
	$i = 0;
	foreach ( $args as $arg ) {
		++ $i;
		if ($i != 1) {
			$ret .= DIRECTORY_SEPARATOR;
		}
		$ret .= str_replace ( (DIRECTORY_SEPARATOR === '/' ? '\\' : '/'), DIRECTORY_SEPARATOR, $arg ) . DIRECTORY_SEPARATOR;
	}
	
	while ( false !== stripos ( $ret, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR ) ) {
		$ret = str_replace ( DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $ret );
	}
	
	if (strlen ( $ret ) < 2) {
		return $ret; // edge case: a single DIRECTORY_SEPARATOR empty
	}
	
	if ($ret [strlen ( $ret ) - 1] === DIRECTORY_SEPARATOR) {
		$ret = substr ( $ret, 0, - 1 );
	}
	
	return $ret;
}
class hhb_curl {
	protected $curlh;
	protected $curloptions = [ ];
	protected $response_body_file_handle; // CURLOPT_FILE
	protected $response_headers_file_handle; // CURLOPT_WRITEHEADER
	protected $request_body_file_handle; // CURLOPT_INFILE
	protected $stderr_file_handle; // CURLOPT_STDERR
	protected function truncateFileHandles() {
		$trun = ftruncate ( $this->response_body_file_handle, 0 );
		assert ( true === $trun );
		$trun = ftruncate ( $this->response_headers_file_handle, 0 );
		assert ( true === $trun );
		// $trun = ftruncate ( $this->request_body_file_handle, 0 );
		// assert ( true === $trun );
		$trun = ftruncate ( $this->stderr_file_handle, 0 );
		assert ( true === $trun );
		return /*true*/;
	}
	function _getCurlHandle()/*: curlresource*/ {
		return $this->curlh;
	}
	function _replaceCurl($newcurl, $closeold = true) {
		if (! is_resource ( $newcurl )) {
			throw new InvalidArgumentsException ( 'parameter 1 must be a curl resource!' );
		}
		
		if (get_resource_type ( $newcurl ) !== 'curl') {
			throw new InvalidArgumentsException ( 'parameter 1 must be a curl resource!' );
		}
		
		if ($closeold) {
			curl_close ( $this->curlh );
		}
		
		$this->curlh = $newcurl;
		$this->_prepare_curl ();
	}
	static function init($url = null, $insecureAndComfortableByDefault = false) { // : hhb_curl {
		return new hhb_curl ( $url, $insecureAndComfortableByDefault );
	}
	function __construct($url = null, $insecureAndComfortableByDefault = false) {
		$this->curlh = curl_init ( '' ); // why empty string? PHP Fatal error: Uncaught TypeError: curl_init() expects parameter 1 to be string, null given
		if (! $this->curlh) {
			throw new RuntimeException ( 'curl_init failed!' );
		}
		
		if ($url !== null) {
			$this->_setopt ( CURLOPT_URL, $url );
		}
		
		$fhandles = [ ];
		$tmph = NULL;
		for($i = 0; $i < 4; ++ $i) {
			$tmph = tmpfile ();
			if ($tmph === false) {
				// for($ii = 0; $ii < $i; ++ $ii) {
				// // @fclose($fhandles[$ii]);//yay, potentially overwriting last error to fuck your debugging efforts!
				// }
				throw new RuntimeException ( 'tmpfile() failed to create 4 file handles!' );
			}
			$fhandles [] = $tmph;
		}
		
		unset ( $tmph );
		$this->response_body_file_handle = $fhandles [0]; // CURLOPT_FILE
		$this->response_headers_file_handle = $fhandles [1]; // CURLOPT_WRITEHEADER
		$this->request_body_file_handle = $fhandles [2]; // CURLOPT_INFILE
		$this->stderr_file_handle = $fhandles [3]; // CURLOPT_STDERR
		unset ( $fhandles );
		$this->_prepare_curl ();
		if ($insecureAndComfortableByDefault) {
			$this->_setComfortableOptions ();
		}
	}
	function __destruct() {
		curl_close ( $this->curlh );
		fclose ( $this->response_body_file_handle ); // CURLOPT_FILE
		fclose ( $this->response_headers_file_handle ); // CURLOPT_WRITEHEADER
		fclose ( $this->request_body_file_handle ); // CURLOPT_INFILE
		fclose ( $this->stderr_file_handle ); // CURLOPT_STDERR
	}
	function _setComfortableOptions() {
		$this->setopt_array ( array (
				CURLOPT_AUTOREFERER => true,
				CURLOPT_BINARYTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTPGET => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_CONNECTTIMEOUT => 4,
				CURLOPT_TIMEOUT => 8,
				CURLOPT_COOKIEFILE => "", // <<makes curl save/load cookies across requests..
				CURLOPT_ENCODING => "", // << makes curl post all supported encodings, gzip/deflate/etc, makes transfers faster
				CURLOPT_USERAGENT => 'hhb_curl_php; curl/' . $this->version () ['version'] . ' (' . $this->version () ['host'] . '); php/' . PHP_VERSION 
		) ); //
		return $this;
	}
	function errno() { // : int {
		return curl_errno ( $this->curlh );
	}
	function error() { // : string {
		return curl_error ( $this->curlh );
	}
	function escape($str) { // : string {
		return curl_escape ( $this->curlh, $str );
	}
	function unescape($str) { // : string {
		return curl_unescape ( $this->curlh, $str );
	}
	function exec($url = null) { // : bool {
		$this->truncateFileHandles ();
		// WARNING: some weird error where curl will fill up the file again with 00's when the file has been truncated
		// until it is the same size as it was before truncating, then keep appending...
		// hopefully this _prepare_curl() call will fix that.. (seen on debian 8 on btrfs with curl/7.38.0)
		$this->_prepare_curl ();
		if (is_string ( $url ) && strlen ( $url ) > 0) {
			$this->setopt ( CURLOPT_URL, $url );
		}
		
		curl_exec ( $this->curlh );
		if ($this->errno ()) {
			throw new RuntimeException ( 'curl_exec failed. errno: ' . var_export ( $this->errno (), true ) . ' error: ' . var_export ( $this->error (), true ) );
		}
		
		return $this;
	}
	function file_create($filename, $mimetype = null, $postname = null) { // : CURLFile {
		return curl_file_create ( $filename, $mimetype, $postname );
	}
	function getinfo($opt) {
		return curl_getinfo ( $this->curlh, $opt );
	}
	function pause($bitmask) { // : int {
		return curl_pause ( $this->curlh, $bitmask );
	}
	function reset() {
		curl_reset ( $this->curlh );
		$this->curloptions = [ ];
		$this->_prepare_curl ();
		return $this;
	}
	function setopt_array(array $options) { // : self {
		foreach ( $options as $option => $value ) {
			$this->setopt ( $option, $value );
		}
		return $this;
	}
	function getResponseBody() { // : string {
		return file_get_contents ( stream_get_meta_data ( $this->response_body_file_handle ) ['uri'] );
	}
	function getResponseHeaders() { // : array {
		$text = file_get_contents ( stream_get_meta_data ( $this->response_headers_file_handle ) ['uri'] );
		// ...
		return $this->splitHeaders ( $text );
	}
	function getResponsesHeaders() { // : array {
	                                 // var_dump($this->getStdErr());die();
	                                 // CONSIDER https://bugs.php.net/bug.php?id=65348
		$Cr = "\x0d";
		$Lf = "\x0a";
		$CrLf = "\x0d\x0a";
		$stderr = $this->getStdErr ();
		$responses = [ ];
		
		while ( FALSE !== ($startPos = strpos ( $stderr, $Lf . '<' )) ) {
			$stderr = substr ( $stderr, $startPos + strlen ( $Lf ) );
			$endPos = strpos ( $stderr, $CrLf . "<\x20" . $CrLf );
			if ($endPos === false) {
				// ofc, curl has ths quirk where the specific message "* HTTP error before end of send, stop sending" gets appended with LF instead of the usual CRLF for other messages...
				$endPos = strpos ( $stderr, $Lf . "<\x20" . $CrLf );
			}
			
			// var_dump(bin2hex(substr($stderr,279,30)),$endPos);die("HEX");
			// var_dump($stderr,$endPos);die("PAIN");
			assert ( $endPos !== FALSE ); // should always be more after this with CURLOPT_VERBOSE.. (connection left intact / connecton dropped /whatever)
			$headers = substr ( $stderr, 0, $endPos );
			// $headerscpy=$headers;
			$stderr = substr ( $stderr, $endPos + strlen ( $CrLf . $CrLf ) );
			$headers = preg_split ( "/((\r?\n)|(\r\n?))/", $headers ); // i can NOT explode($CrLf,$headers); because sometimes, in the middle of recieving headers, it will spout stuff like "\n* Added cookie reg_ext_ref="deleted" for domain facebook.com, path /, expire 1457503459"
			                                                           // if(strpos($headerscpy,"report-uri=")!==false){
			                                                           // //var_dump($headerscpy);die("DIEDS");
			                                                           // var_dump($headers);
			                                                           // //var_dump($this->getStdErr());die("DIEDS");
			                                                           // }
			foreach ( $headers as $key => &$val ) {
				$val = trim ( $val );
				if (! strlen ( $val )) {
					unset ( $headers [$key] );
					continue;
				}
				
				if ($val [0] !== '<') {
					// static $r=0;++$r;var_dump('removing',$val);if($r>1)die();
					unset ( $headers [$key] ); // sometimes, in the middle of recieving headers, it will spout stuff like "\n* Added cookie reg_ext_ref="deleted" for domain facebook.com, path /, expire 1457503459"
					continue;
				}
				
				$val = trim ( substr ( $val, 1 ) );
			}
			
			unset ( $val ); // references can be scary..
			$responses [] = $headers;
		}
		
		unset ( $headers, $key, $val, $endPos, $startPos );
		return $responses;
	}
	
	// we COULD have a getResponsesCookies too...
	function getResponseCookies() { // : array {
		$headers = $this->getResponsesHeaders ();
		$headers_merged = array ();
		foreach ( $headers as $headers2 ) {
			foreach ( $headers2 as $header ) {
				$headers_merged [] = $header;
			}
		}
		
		return $this->parseCookies ( $headers_merged );
	}
	function getRequestBody() { // : string {
		return file_get_contents ( stream_get_meta_data ( $this->request_body_file_handle ) ['uri'] );
	}
	
	// gets the headers of the LAST request..
	function getRequestHeaders() { // : array {
		$requestsHeaders = $this->getRequestsHeaders ();
		$requestCount = count ( $requestsHeaders );
		if ($requestCount === 0) {
			return array ();
		}
		return $requestsHeaders [$requestCount - 1];
	}
	
	// array(0=>array(request1_headers),1=>array(requst2_headers),2=>array(request3_headers))~
	function getRequestsHeaders() { // : array {
	                                // CONSIDER https://bugs.php.net/bug.php?id=65348
		$Cr = "\x0d";
		$Lf = "\x0a";
		$CrLf = "\x0d\x0a";
		$stderr = $this->getStdErr ();
		$requests = [ ];
		while ( FALSE !== ($startPos = strpos ( $stderr, $Lf . '>' )) ) {
			$stderr = substr ( $stderr, $startPos + strlen ( $Lf . '>' ) );
			$endPos = strpos ( $stderr, $CrLf . $CrLf );
			if ($endPos === false) {
				// ofc, curl has ths quirk where the specific message "* HTTP error before end of send, stop sending" gets appended with LF instead of the usual CRLF for other messages...
				$endPos = strpos ( $stderr, $Lf . $CrLf );
			}
			
			assert ( $endPos !== FALSE ); // should always be more after this with CURLOPT_VERBOSE.. (connection left intact / connecton dropped /whatever)
			$headers = substr ( $stderr, 0, $endPos );
			$stderr = substr ( $stderr, $endPos + strlen ( $CrLf . $CrLf ) );
			$headers = explode ( $CrLf, $headers );
			
			foreach ( $headers as $key => &$val ) {
				$val = trim ( $val );
				if (! strlen ( $val )) {
					unset ( $headers [$key] );
				}
			}
			
			unset ( $val ); // references can be scary..
			$requests [] = $headers;
		}
		
		unset ( $headers, $key, $val, $endPos, $startPos );
		return $requests;
	}
	function getRequestCookies() { // : array {
		return $this->parseCookies ( $this->getRequestHeaders () );
	}
	function getStdErr() { // : string {
		return file_get_contents ( stream_get_meta_data ( $this->stderr_file_handle ) ['uri'] );
	}
	function getStdOut() { // : string {
	                       // alias..
		return $this->getResponseBody ();
	}
	protected function splitHeaders($headerstring) { // : array {
		$headers = preg_split ( "/((\r?\n)|(\r\n?))/", $headerstring );
		foreach ( $headers as $key => $val ) {
			if (! strlen ( trim ( $val ) )) {
				unset ( $headers [$key] );
			}
		}
		
		return $headers;
	}
	protected function parseCookies($headers) { // : array {
		$returnCookies = [ ];
		$grabCookieName = function ($str, &$len) {
			$len = 0;
			$ret = "";
			$i = 0;
			for($i = 0; $i < strlen ( $str ); ++ $i) {
				++ $len;
				if ($str [$i] === ' ') {
					continue;
				}
				
				if ($str [$i] === '=' || $str [$i] === ';') {
					-- $len;
					break;
				}
				
				$ret .= $str [$i];
			}
			
			return urldecode ( $ret );
		};
		
		foreach ( $headers as $header ) {
			// Set-Cookie: crlfcoookielol=crlf+is%0D%0A+and+newline+is+%0D%0A+and+semicolon+is%3B+and+not+sure+what+else
			/*
			 * Set-Cookie:ci_spill=a%3A4%3A%7Bs%3A10%3A%22session_id%22%3Bs%3A32%3A%22305d3d67b8016ca9661c3b032d4319df%22%3Bs%3A10%3A%22ip_address%22%3Bs%3A14%3A%2285.164.158.128%22%3Bs%3A10%3A%22user_agent%22%3Bs%3A109%3A%22Mozilla%2F5.0+%28Windows+NT+6.1%3B+WOW64%29+AppleWebKit%2F537.36+%28KHTML%2C+like+Gecko%29+Chrome%2F43.0.2357.132+Safari%2F537.36%22%3Bs%3A13%3A%22last_activity%22%3Bi%3A1436874639%3B%7Dcab1dd09f4eca466660e8a767856d013; expires=Tue, 14-Jul-2015 13:50:39 GMT; path=/
			 * Set-Cookie: sessionToken=abc123; Expires=Wed, 09 Jun 2021 10:18:14 GMT;
			 * //Cookie names cannot contain any of the following '=,; \t\r\n\013\014'
			 * //
			 */
			if (stripos ( $header, "Set-Cookie:" ) !== 0) {
				continue;
				/* */
			}
			
			$header = trim ( substr ( $header, strlen ( "Set-Cookie:" ) ) );
			$len = 0;
			while ( strlen ( $header ) > 0 ) {
				$cookiename = $grabCookieName ( $header, $len );
				$returnCookies [$cookiename] = '';
				$header = substr ( $header, $len );
				if (strlen ( $header ) < 1) {
					break;
				}
				if ($header [0] === '=') {
					$header = substr ( $header, 1 );
				}
				$thepos = strpos ( $header, ';' );
				if ($thepos === false) { // last cookie in this Set-Cookie.
					$returnCookies [$cookiename] = urldecode ( $header );
					break;
				}
				$returnCookies [$cookiename] = urldecode ( substr ( $header, 0, $thepos ) );
				$header = trim ( substr ( $header, $thepos + 1 ) ); // also remove the ;
			}
		}
		
		unset ( $header, $cookiename, $thepos );
		return $returnCookies;
	}
	function setopt($option, $value) { // : self {
		switch ($option) {
			case CURLOPT_VERBOSE :
				{
					trigger_error ( 'you should NOT change CURLOPT_VERBOSE. use getStdErr() instead. we are working around https://bugs.php.net/bug.php?id=65348 using CURLOPT_VERBOSE.', E_USER_WARNING );
					break;
				}
			case CURLOPT_RETURNTRANSFER :
				{
					trigger_error ( 'you should NOT use CURLOPT_RETURNTRANSFER. use getResponseBody() instead. expect problems now.', E_USER_WARNING );
					break;
				}
			case CURLOPT_FILE :
				{
					trigger_error ( 'you should NOT use CURLOPT_FILE. use getResponseBody() instead. expect problems now.', E_USER_WARNING );
					break;
				}
			case CURLOPT_WRITEHEADER :
				{
					trigger_error ( 'you should NOT use CURLOPT_WRITEHEADER. use getResponseHeaders() instead. expect problems now.', E_USER_WARNING );
					break;
				}
			case CURLOPT_INFILE :
				{
					trigger_error ( 'you should NOT use CURLOPT_INFILE. use setRequestBody() instead. expect problems now.', E_USER_WARNING );
					break;
				}
			case CURLOPT_STDERR :
				{
					trigger_error ( 'you should NOT use CURLOPT_STDERR. use getStdErr() instead. expect problems now.', E_USER_WARNING );
					break;
				}
			case CURLOPT_HEADER :
				{
					trigger_error ( 'you NOT use CURLOPT_HEADER. use  getResponsesHeaders() instead. expect problems now. we are working around https://bugs.php.net/bug.php?id=65348 using CURLOPT_VERBOSE, which is, until the bug is fixed, is incompatible with CURLOPT_HEADER.', E_USER_WARNING );
					break;
				}
			case CURLINFO_HEADER_OUT :
				{
					trigger_error ( 'you should NOT use CURLINFO_HEADER_OUT. use  getRequestHeaders() instead. expect problems now.', E_USER_WARNING );
					break;
				}
			
			default :
				{
				}
		}
		
		return $this->_setopt ( $option, $value );
	}
	private function _setopt($option, $value) { // : self {
		$ret = curl_setopt ( $this->curlh, $option, $value );
		if (! $ret) {
			throw new InvalidArgumentException ( 'curl_setopt failed. errno: ' . $this->errno () . '. error: ' . $this->error () . '. option: ' . var_export ( $this->_curlopt_name ( $option ), true ) . ' (' . var_export ( $option, true ) . '). value: ' . var_export ( $value, true ) );
		}
		$this->curloptions [$option] = $value;
		return $this;
	}
	function getopt($option, &$isset = NULL) {
		if (array_key_exists ( $option, $this->curloptions )) {
			$isset = true;
			return $this->curloptions [$option];
		} else {
			$isset = false;
			return NULL;
		}
	}
	function strerror($errornum) { // : string {
		return curl_strerror ( $errornum );
	}
	function version($age = CURLVERSION_NOW) { // : array {
		return curl_version ( $age );
	}
	private function _prepare_curl() {
		$this->truncateFileHandles ();
		$this->_setopt ( CURLOPT_FILE, $this->response_body_file_handle ); // CURLOPT_FILE
		$this->_setopt ( CURLOPT_WRITEHEADER, $this->response_headers_file_handle ); // CURLOPT_WRITEHEADER
		$this->_setopt ( CURLOPT_INFILE, $this->request_body_file_handle ); // CURLOPT_INFILE
		$this->_setopt ( CURLOPT_STDERR, $this->stderr_file_handle ); // CURLOPT_STDERR
		$this->_setopt ( CURLOPT_VERBOSE, true );
	}
	function _curlopt_name($option)/*:mixed(string|false)*/{
		// thanks to TML for the get_defined_constants trick..
		// <TML> If you had some specific reason for doing it with your current approach (which is, to me, approaching the problem completely backwards - "I dug a hole! How do I get out!"), it seems that your entire function there could be replaced with: return array_flip(get_defined_constants(true)['curl']);
		$curldefs = array_flip ( get_defined_constants ( true ) ['curl'] );
		if (isset ( $curldefs [$option] )) {
			return $curldefs [$option];
		} else {
			return false;
		}
	}
	function _curlopt_number($option)/*:mixed(int|false)*/{
		// thanks to TML for the get_defined_constants trick..
		$curldefs = get_defined_constants ( true ) ['curl'];
		if (isset ( $curldefs [$option] )) {
			return $curldefs [$option];
		} else {
			return false;
		}
	}
}
class hhb_bcmath {
	public $scale = 200;
	public function __construct($scale = 200) {
		$this->scale = $scale;
	}
	public function add($left_operand, $right_operand, $scale = NULL) { // : string {
		$scale = ($scale != null) ? $scale : $this->scale;
		$ret = bcadd ( $left_operand, $right_operand, $scale );
		return $this->bctrim ( $ret );
	}
	public function comp($left_operand, $right_operand, $scale = NULL) // :int
{
		$scale = ($scale != null) ? $scale : $this->scale;
		$ret = bccomp ( $left_operand, $right_operand, $scale );
		return $ret;
	}
	public function div($left_operand, $right_operand, $scale = NULL) { // : string {
		$scale = ($scale != null) ? $scale : $this->scale;
		$right_operand = $this->bctrim ( trim ( $right_operand ) );
		if ($right_operand === '0') {
			throw new DivisionByZeroError ();
		}
		$ret = bcdiv ( $left_operand, $right_operand, $scale );
		return $this->bctrim ( $ret );
	}
	public function mod($left_operand, $modulus) { // : string {
		$scale = ($scale != null) ? $scale : $this->scale;
		$modulus = $this->bctrim ( trim ( $modulus ) );
		if ($modulus === '0') {
			// if there was a ModulusByZero error, i would use it
			throw new DivisionByZeroError ();
		}
		
		$ret = bcmod ( $left_operand, $modulus );
		return $this->bctrim ( $ret );
	}
	public function mul($left_operand, $right_operand, $scale = NULL) { // : string {
		$scale = ($scale != null) ? $scale : $this->scale;
		$ret = bcmul ( $left_operand, $right_operand, $scale );
		return $this->bctrim ( $ret );
	}
	public function pow($left_operand, $right_operand, $scale = NULL) { // : string {
		$scale = ($scale != null) ? $scale : $this->scale;
		$ret = bcpow ( $left_operand, $right_operand, $scale );
		return $this->bctrim ( $ret );
	}
	public function powmod($left_operand, $right_operand, $modulus, $scale = NULL) { // : string {
		$scale = ($scale != null) ? $scale : $this->scale;
		$modulus = $this->bctrim ( trim ( $modulus ) );
		if ($modulus === '0') {
			// if there was a ModulusByZero error, i would use it
			throw new DivisionByZeroError ();
		}
		$ret = bcpowmod ( $left_operand, $modulus, $modulus, $scale );
		return $this->bctrim ( $ret );
	}
	public function scale($scale) { // :bool{
		$this->scale = $scale;
		return true;
	}
	public function sqrt($operand, $scale = NULL) {
		$scale = ($scale != null) ? $scale : $this->scale;
		if (bccomp ( $operand, '-1' ) !== - 1) {
			throw new RangeException ( 'tried to get the square root of number below zero!' );
		}
		$ret = bcsqrt ( $left_operand, $scale );
		return $this->bctrim ( $ret );
	}
	public function sub($left_operand, $right_operand, $scale = NULL) { // : string {
		$scale = ($scale != null) ? $scale : $this->scale;
		$ret = bcsub ( $left_operand, $right_operand, $scale );
		return $this->bctrim ( $ret );
	}
	public static function bctrim($str) { // : string {
		$str = trim ( $str );
		if (false === strpos ( $str, '.' )) {
			return $str;
		}
		$str = rtrim ( $str, '0' );
		if ($str [strlen ( $str ) - 1] === '.') {
			$str = substr ( $str, 0, - 1 );
		}
		return $str;
	}
}
