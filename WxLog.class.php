<?php 
namespace Wxs;

/*
	日志
*/

class WxLog
{

	function __construct() {
		$this->dir = 'log';
		$this->input_path = 'log/input.log';
		$this->response_path = 'log/response.log';
		if (!file_exists($this->dir)) {
			mkdir($this->dir);
		}
		if (!file_exists($this->input_path)) {
			fclose(fopen($this->input_path));
		}
		if (!file_exists($this->response_path)) {
			fclose(fopen($this->response_path));
		}
		
	}
	
	
	function log($str) {
		file_put_contents($this->input_path, $str."\r\n", FILE_APPEND);
	}

	function logResponse($str) {
			file_put_contents($this->response_path, date('Y-m-d H:i:s').'::'.$str."\r\n", FILE_APPEND);
		}

	function logInput() {
		file_put_contents($this->input_path, date('Y-m-d H:i:s')."\r\n", FILE_APPEND);
		file_put_contents($this->input_path, $_SERVER['REQUEST_URI']."\r\n", FILE_APPEND);
		file_put_contents($this->input_path, file_get_contents("php://input"), FILE_APPEND);
		
	}

}