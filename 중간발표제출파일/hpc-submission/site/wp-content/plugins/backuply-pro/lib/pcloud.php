<?php

#[\AllowDynamicProperties]
class pcloud{
	public $mode;
	public $access_token;
	public $path;
	public $host;
	public $folderid;
	public $filename = '';
	public $buffer = '';
	public $chunk_size = 5 * 1024 * 1024;
	public $fileid;
	public $offset = 0;
	public $filesize = 0;
	public $download_host = '';
	public $filelist = [];
	private $retry = 0;
	
	function __construct(){
		if(empty($this->download_host) && !empty($GLOBALS['remote_data']['download_host'])){
			$this->download_host = $GLOBALS['remote_data']['download_host'];
		}
	}

	function stream_open($path, $mode, $options, &$opened_path){
		global $error, $backuply;

		$this->mode = $mode;

		$stream = $this->parse_path($path);

		if(empty($stream)){
			$error[] = __('Unable to parse the path', 'backuply');
			return false;
		}

		if(strpos($this->mode, 'r') !== FALSE){
			return true;
		}

		$ret = true;
		if(empty($backuply['status']['init_data'])){
			$ret = $this->create_upload($path);
		}

		return $ret;
	}

	function create_upload($path){
		global $backuply, $error;

		$endpoint = '/upload_create';

		$url = 'https://' . $this->host . $endpoint;
		$headers = array('Authorization: Bearer ' . $this->access_token);

		$resp = $this->__curl($url, $headers, '', 0, '', 0, 'GET');

		if(!empty($resp['error'])){
			$error[] = 'PCloud : '.$resp['error'];
			return false;
		}

		if(empty($resp['result'])){
			$error[] = __('PCloud : The result was empty', 'backuply');
			return false;
		}

		preg_match('/{.*?}$/is', $resp['result'], $matches);
		$result = json_decode($matches[0], true);

		if(!empty($result['result'])){
			$error[] = $result['error'];
			return false;
		}

		if(empty($result['uploadid'])){
			$error[] = __('PCloud: Unable to get upload ID', 'backuply');
			return false;
		}

		$backuply['status']['init_data'] = $result['uploadid'];

		return true;
	}

	// This function is used only to get the info file
	function stream_read($count){
	
		if(empty($count)){
			return false;
		}

		if(empty($this->download_host)){
			$this->download_host = $this->get_download_link();
		}

		if(empty($this->download_host)){
			return false;
		}

		if(empty($this->range_lower_limit)){
			$this->range_lower_limit = 0;
		}

		$this->range_upper_limit = ($this->range_lower_limit + $count) - 1;

		if($this->range_upper_limit >= $this->filesize){
			$this->range_upper_limit = $this->filesize - 1;
		}
		
		$tmp_file = backuply_glob('backups_info') . '/test.tmp';
		$fp = fopen($tmp_file, 'wb+');

		$this->__read($this->download_host, $this->range_lower_limit, $this->range_upper_limit, $fp);

		rewind($fp); // Taking it back to the start
		$resp = fread($fp, $count);
		fclose($fp);

		@unlink($tmp_file); // We can now safely delete the file

		$this->offset = $this->range_upper_limit + 1;
		$this->range_lower_limit = $this->range_upper_limit + 1;

		return $resp;
		
	}

    function stream_write($data){
		global $backuply;

		$this->buffer .= $data;

		// We want the buffer to have atleast 5 MB in size.
		if($this->chunk_size > strlen($this->buffer)){
			return strlen($data);
		}

		$res = $this->upload_write($this->buffer);

		if(empty($res)){
			return false;
		}

		$GLOBALS['start_pos'] += strlen($this->buffer);
		$this->buffer = '';

		return strlen($data);
	}
	
	function upload_write($data){
		global $backuply, $error;

		$endpoint = '/upload_write';

		$url = 'https://' . $this->host . $endpoint;

		$params = [
			'uploadid' => $backuply['status']['init_data'],
			'uploadoffset' => $GLOBALS['start_pos'],
			'access_token' => $this->access_token,
		];

		$url .= '?' . http_build_query($params);
		$resp = $this->__curl($url, '', '', 0, $data, 0, 'PUT');

		if(!empty($resp['error'])){
			$error[] = 'PCloud : '.$resp['error'];
			return false;
		}

		if(empty($resp['result'])){
			$error[] = __('PCloud : The result was empty', 'backuply');
			return false;
		}

		preg_match('/{.*?}$/is', $resp['result'], $matches);
		$result = json_decode($matches[0], true);

		if(!empty($result['result'])){
			if($this->retry < 3 && $result['result'] == '5002'){
				$this->retry += 1;
				backuply_log('Retrying');
				sleep(1); // Error could happen due to the pCloud server not responding.
				return $this->upload_write($data);
			}

			$error[] = $result['error'];
			return false;
		}

		return true;
	}

    function stream_close(){
		global $backuply;
		
		// We do not need to ddo any action if we are just reading the file
		// becuase everything gets done in stream_read only.
		if(strpos($this->mode, 'r') !== FALSE){
			return true;
		}
		
		
		if(!empty($backuply['status']['incomplete_upload'])) {
			return;
		}

		if(!empty($this->buffer)){
			$res = $this->upload_write($this->buffer);
			
			if(empty($res)){
				return;
			}
			
			$GLOBALS['start_pos'] += strlen($this->buffer);
		}

		$endpoint = '/upload_save';
		$url = 'https://'. $this->host . $endpoint;

		$params = [
			'uploadid' => $backuply['status']['init_data'],
			'folderid' => $this->folderid,
			'name' => rawurlencode($this->filename),
			'access_token' => $this->access_token,
		];

		$url .= '?' . http_build_query($params);

		$resp = $this->__curl($url, '', '', 0, '', 0, 'GET');

		if(!empty($resp['error'])){
			$error[] = 'PCloud : '.$resp['error'];
			return false;
		}

		if(empty($resp['result'])){
			$error[] = __('PCloud : The result was empty', 'backuply');
			return false;
		}

		preg_match('/{.*?}$/is', $resp['result'], $matches);
		$result = json_decode($matches[0], true);

		if(!empty($result['result'])){
			$error[] = $result['error'];
			return false;
		}

		return true;
	}

	function unlink($path){
		$stream = $this->parse_path($path);

		if(empty($stream)){
			$error[] = __('Unable to parse the path', 'backuply');
			return false;
		}
		
		$endpoint = '/deletefile';
		$url = 'https://'. $this->host . $endpoint;

		$this->path = trim($this->path, '/');

		$params = [
			'path' => '/backups/'.$this->path,
			'access_token' => $this->access_token,
		];

		$url .= '?' . http_build_query($params);

		$resp = $this->__curl($url, '', '', 0, '', 0, 'GET');

		if(!empty($resp['error'])){
			$error[] = 'PCloud : '.$resp['error'];
			return false;
		}

		if(empty($resp['result'])){
			$error[] = __('PCloud : The result was empty', 'backuply');
			return false;
		}

		preg_match('/{.*?}$/is', $resp['result'], $matches);
		$result = json_decode($matches[0], true);

		if(!empty($result['result'])){
			$error[] = $result['error'];
			return false;
		}
		
		return true;
	}
	
	// In response to file_exists(), is_file(), is_dir() and more
	// check at https://www.php.net/manual/en/streamwrapper.url-stat.php
	function url_stat($path){

		$stream = $this->parse_path($path);

		if(empty($stream)){
			$error[] = __('Unable to parse the path', 'backuply');
			return false;
		}

		$endpoint = '/stat';
		$url = 'https://'. $this->host . $endpoint;
		
		$this->path = trim($this->path, '/');

		$params = [
			'path' => '/backups/'.$this->path,
			'access_token' => $this->access_token,
		];

		$url .= '?' . http_build_query($params);

		$resp = $this->__curl($url, '', '', 0, '', 0, 'GET');

		if(!empty($resp['error'])){
			$error[] = 'PCloud : '.$resp['error'];
			return false;
		}

		if(empty($resp['result'])){
			$error[] = __('PCloud : The result was empty', 'backuply');
			return false;
		}

		preg_match('/{.*?}$/is', $resp['result'], $matches);
		$result = json_decode($matches[0], true);

		if(!empty($result['result'])){
			$error[] = $result['error'];
			return false;
		}

		if(empty($result['metadata'])){
			return;
		}
		
		if(!empty($result['metadata']['isfolder'])){
			$mode = 0040000;	//For DIR
		}else{
			$mode = 0100000;	//For File
		}

		$stat = array(
			'dev' => 0,
			'ino' => 0,
			'mode' => $mode,
			'nlink' => 0,
			'uid' => 0,
			'gid' => 0,
			'rdev' => 0,
			'size' => $result['metadata']['size'],
			'atime' => strtotime($result['metadata']['created']),
			'mtime' => strtotime($result['metadata']['modified']),
			'ctime' => strtotime($result['metadata']['created']),
			'blksize' => 0,
			'blocks' => 0
		);

		return $stat;
		
	}

	// Will create any sub directories inside Backuply if required.
    function mkdir($path, $mode){
		global $error, $backuply_pcloud_folderid;

		$endpoint = '/createfolderifnotexists';
		$stream = $this->parse_path($path);
		
		if(empty($stream)){
			return false;
		}

		if(empty($this->path) || $this->path == '/'){
			$folder_name = 'backups';
		} else {
			$folder_name = trim($this->path, '/');
		}

		$url = 'https://' .$this->host . $endpoint;
		$url .= '?' . http_build_query([
			'folderid' => $backuply_pcloud_folderid,
			'name' => $folder_name
		]);
		
		$headers = array('Authorization: Bearer ' . $this->access_token);

		$resp = $this->__curl($url , $headers, '', 0, '', 0, 'GET');

		if(!empty($resp['error'])){
			$error[] = 'PCloud : '.$resp['error'];
			return false;
		}

		if(empty($resp['result'])){
			$error[] = __('PCloud : The result was empty', 'backuply');
			return false;
		}

		preg_match('/{.*?}$/is', $resp['result'], $matches);
		$result = json_decode($matches[0], true);
		
		if(!empty($result['result'])){
			$error[] = $result['error'];
			return false;
		}
		
		if(isset($result['metadata']) && isset($result['metadata']['folderid'])){
			$backuply_pcloud_folderid = $result['metadata']['folderid'];
		}

		return true;
	}

	// Will create the root Backuply folder if dosent exists.
    function dir_opendir($path, $options){
		global $error, $backuply_pcloud_folderid;

		$endpoint = '/listfolder';
		$stream = $this->parse_path($path);
		
		if(empty($stream)){
			return false;
		}
		
		$this->path = trim($this->path, '/');

		$url = 'https://' .$this->host . $endpoint;
		$url .= '?' . http_build_query([
			'path' => '/backups/' . $this->path,
		]);

		$headers = array('Authorization: Bearer ' . $this->access_token);
		$resp = $this->__curl($url , $headers, '', 0, '', 0, 'GET');

		if(!empty($resp['error'])){
			$error[] = 'PCloud : '.$resp['error'];
			return false;
		}

		if(empty($resp['result'])){
			$error[] = __('PCloud : The result was empty', 'backuply');
			return false;
		}

		preg_match('/{.*?}$/is', $resp['result'], $matches);
		$result = json_decode($matches[0], true);
		
		if(!empty($result['result'])){
			if($result['result'] != 2005){
				$error[] = $result['error'];
			}

			return false;
		}

		if(isset($result['metadata']) && isset($result['metadata']['folderid'])){
			$backuply_pcloud_folderid = $result['metadata']['folderid'];
		}

		if(isset($result['metadata']) && !empty($result['metadata']['contents']) && is_array($result['metadata']['contents'])){
			$this->filelist = $result['metadata']['contents'];
		}

		foreach($this->filelist as $i => $file) {
			$this->filelist[$i] = $file['name'];
		}

		return true;
	}

    function dir_readdir(){
		$key = key($this->filelist);
		if(is_null($key)){
			return false;
		}
		
		$val = $this->filelist[$key];
		unset($this->filelist[$key]);
		return pathinfo($val, PATHINFO_BASENAME);
	}

    function download_file_loop($source, $dest, $startpos = 0){
		global $error;

		$chunk = 2097152; // Size of the file to download 2MB.

		$stream = $this->parse_path($source);

		if(empty($stream)){
			return false;
		}

		if(empty($this->download_host)){
			$this->download_host = $this->get_download_link();
		}

		if(empty($this->download_host)){
			return false;
		}
		
		$file_stats = $this->url_stat($source);
		$this->filesize = !empty($file_stats) ? $file_stats['size'] : 0;
		
		if(empty($this->filesize)){
			return false;
		}

		$range_lower_limit = $startpos;
		$range_upper_limit = ($range_lower_limit + $chunk) - 1;

		$fp = @fopen($dest, 'ab');
		while(!$this->stream_eof()){
			if(time() + 5 >= $GLOBALS['end']){
				$GLOBALS['remote_data']['download_host'] = $this->download_host;
				break;
			}

			if($range_upper_limit >= $this->filesize){
				$range_upper_limit = $this->filesize - 1;
			}
			
			$url = 'https://'. $this->download_host;
			
			$block = $this->__read($url, $range_lower_limit, $range_upper_limit, $fp);

			$this->offset = $range_upper_limit + 1;
			$range_lower_limit = $range_upper_limit + 1;
			$range_upper_limit = ($range_lower_limit + $chunk) - 1;
			
			$percentage = (filesize($dest) / $this->filesize) * 100;
			
			backuply_status_log('<div class="backuply-upload-progress"><span class="backuply-upload-progress-bar" style="width:'.round($percentage).'%;"></span><span class="backuply-upload-size">'.round($percentage).'%</span></div>', 'downloading', 22);
		}
		
		$GLOBALS['l_readbytes'] = filesize($dest);
		fclose($fp);
	}
	
	// Gets the host to download the file
	function get_download_link(){
		global $error;

		$endpoint = '/getfilelink';

		$url = 'https://' .$this->host . $endpoint;
		
		$this->path = trim($this->path, '/');

		$url .= '?' . http_build_query([
			'path' => '/backups/' . $this->path,
			'access_token' => $this->access_token
		]);

		$resp = $this->__curl($url, '', '', 0, '', 0, 'GET');

		if(!empty($resp['error'])){
			$error[] = 'PCloud : '.$resp['error'];
			return false;
		}

		if(empty($resp['result'])){
			$error[] = __('PCloud : The result was empty', 'backuply');
			return false;
		}

		preg_match('/{.*?}$/is', $resp['result'], $matches);
		$result = json_decode($matches[0], true);

		if(!empty($result['result'])){
			$error[] = $result['error'];
			return false;
		}

		if(empty($result['hosts'][0])){
			$error[] = 'Did not got the host to download the file';
			return false;
		}
		
		$this->filesize = $result['size'];
		
		return $result['hosts'][0] . $result['path'];

	}

    function stream_eof(){
		return $this->offset >= $this->filesize;
	}
	
	function __read($download_url, $lower_limit, $upper_limit, $tmp_file = ''){
		global $error;
		
		$headers = array('Range: bytes='.$lower_limit.'-'.$upper_limit);
		
		$resp = $this->__curl($download_url, $headers, '', '', '', $tmp_file, 'GET');
		
		if(!empty($resp['error'])){
			$error[] = $resp['error'];
		}

		return $resp['result'];
	}
	
	function __curl($url, $headers = '', $filepointer = '', $upload_size = 0, $post = '', $download_file = 0, $request_type = 'POST'){
		global $error;
		
		// Set the curl parameters.
		$ch = curl_init($url);
		
		if(!empty($headers)){
			if(empty($download_file)){
				curl_setopt($ch, CURLOPT_HEADER, 1);
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}
		
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_type);
		
		//We are setting this as on some servers, the default HTTP version was taken as 2.0 by curl, causing issue
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		
		if(!empty($filepointer)){
			curl_setopt($ch, CURLOPT_UPLOAD, 1);
			curl_setopt($ch, CURLOPT_INFILE, $filepointer);
			curl_setopt($ch, CURLOPT_INFILESIZE, $upload_size);
		}
		
		if(!empty($post)){
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		//curl_setopt($ch, CURLOPT_VERBOSE, TRUE);

		// Turn off the server and peer verification (TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		if(!empty($download_file)){
			// Note:: CURLOPT_FILE only works if set after CURLOPT_RETURNTRANSFER
			curl_setopt($ch, CURLOPT_FILE, $download_file);
		}
		
		// Get response from the server.
		$resp = array();
		$resp['result'] = curl_exec($ch);
		$resp['error'] = curl_error($ch);

		curl_close($ch);

		return $resp;
	}
	
	function parse_path($path){
		global $error;

		$stream = parse_url($path);

		$this->path = !empty($stream['path']) ? $stream['path'] : '';

		if(empty($stream['host'])){
			$error[] = __('Host to connect to pcloud not found', 'backuply');
			return false;
		}

		// At host we have the host and the folderid if any
		// So we have 4894794-api.pcloud.com
		if(strpos($stream['host'], '-') !== FALSE){
			$host = explode('-', $stream['host']);
			$this->folderid = $host[0];
			$this->host = $host[1];
		} else {
			$this->host = $stream['host'];
		}

		if(empty($stream['user'])){
			$error[] = __('Access Key to connect to pcloud not found', 'backuply');
			return false;
		}

		if(!empty($stream['path'])){
			$this->path = $stream['path'];
			$pathinfo = pathinfo($this->path);
			if(isset($pathinfo['basename'])){
				$this->filename = $pathinfo['basename'];
			}
		}

		$this->access_token = $stream['user'];
		
		return true;
	}
	
	function get_access_token($access_code, $host){
		$url = 'https://api.backuply.com/pcloud/token.php';

		$data = [
			'action' => 'get_access_token',
			'code' => $access_code,
			'host' => esc_url($host)
		];

		$resp = $this->__curl($url, '', '', 0, $data, 0, 'POST');

		if(empty($resp['result'])){
			return '';
		}

		$result = json_decode($resp['result'], true);

		if(!empty($result) && !empty($result['access_token'])){
			return $result['access_token'];
		}

		return '';

	}
}
