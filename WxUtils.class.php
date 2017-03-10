<?php 

namespace Wxs;
/**
* 
*/
class WxUtils
{
	
	static function curl_post($url, $post = array(), $type = 'array', array $options = array()) 
	{
		if ($type == 'array') {
			$post = http_build_query($post);
		} else if ($type == 'json') {
			$post = json_encode($post, JSON_UNESCAPED_UNICODE);
		} else if ($type == 'xml') {
			if (is_array($post)) {
				$xmlparser = new WxXml();
				$post = $xmlparser->arrayToXml($post);
			}
			
		} else {

		}
		$default = array(
			CURLOPT_POST => 1,
			CURLOPT_HEADER => 0,
			CURLOPT_URL  => $url,
			CURLOPT_FRESH_CONNECT => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FORBID_REUSE => 1,
			CURLOPT_SSL_VERIFYPEER => FALSE,
			CURLOPT_TIMEOUT => 4,
			CURLOPT_POSTFIELDS => $post

		);

		$ch = curl_init();
		curl_setopt_array($ch, ($options + $default));
		if (!$result = curl_exec($ch)) {
			trigger_error(curl_error($ch));
		}

		curl_close($ch);

		return $result;


	}

	static function curl_get($url, array $get = array(), array $options = array())
	{

		$default = array(
			CURLOPT_URL => $url.(strpos($url, '?') === FALSE?'?':'').http_build_query($get),
			CURLOPT_HEADER => 0,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_SSL_VERIFYPEER => FALSE,
			CURLOPT_TIMEOUT =>4
		);

		$ch = curl_init();
		curl_setopt_array($ch, ($options + $default));
		if (!$result = curl_exec($ch)) {
			trigger_error(curl_error($ch));
		}

		curl_close($ch);

		return $result;
	}
	/*微信支付生成签名*/
	static function getPaySign($arr) {
		$sign = '';
		$keys = array_keys($arr);
		sort($keys, SORT_STRING);
		foreach ($keys as $key) {
			if (!empty($arr[$key])) {
				$sign .= "$key=$arr[$key]&";
			}
		}
		// $key = '';
		$sign .= "key=Interest376757341206486320589928";
		$sign = md5($sign);
		$sign = strtoupper($sign);

		return $sign;
	}

	/*调用jsapi生成签名
	参与签名的字段包括noncestr（随机字符串）, 有效的jsapi_ticket, timestamp（时间戳）, url（当前网页的URL，不包含#及其后面部分
	*/
	static function getJsSign($arr) {
		$sign = '';
		$keys = array_keys($arr);
		$blocks = array();
		sort($keys, SORT_STRING);
		foreach ($keys as $key) {
			if (!empty($arr[$key])) {
				$blocks[] = $key.'='.$arr[$key];
			}
		}
		$sign = implode('&', $blocks);

		$sign = sha1($sign);


		return $sign;
	}
	/*
		jsapi 卡卷 ticket 签名算法
		将 api_ticket、app_id、location_id、timestamp、nonce_str、card_id、card_type的value值进行字符串的字典序排序。
		将所有参数字符串拼接成一个字符串进行sha1加密，得到cardSign。
	*/
	static function getJsCardSign($arr) {


		sort($arr, SORT_STRING);

		$sign = implode($arr);

		$sign = sha1($sign);


		return $sign;
	}

	/*生成随机字符串*/
	static function getRandStr() {
		$rand = rand(1000, 9999);
		$nonce_str = (string)$rand;

		return $nonce_str;
	}

	/*获取当前网页地址*/
	static function getCurUrl() {
		return C('host').__SELF__;
	}


}