<?php 

namespace Wxs;

/*
 微信公众号入口

*/
 class Wx
 {
 	function __construct($source = NULL) {
		$this->logger = new WxLog();
		$this->cache = $source ? new WxCache($source):$source;
		$this->appid = C('appid');
		$this->pay_appid = 'wxb9e5eb43a8db7fa7';
		$this->appsecret = C('appsecret');
		$this->token = C('token');
		$this->code = '';
		$this->mch_id = C('mch_id');
		$this->notify_url = C('notify_url');
		$this->prepay_id = '';
		$this->debug = 'false';
		$this->rule = C('rule');
	}

	function setCacheSourse($source) {
		$this->cache = new WxCache($source);
	}

	/*code 换取网页access_token凭证*/
	function setCode($code) {
		$this->code = $code;
	}

	function getCode() {
		if (empty($this->code)) {
			if (!I('get.code', false)) {
				$redirect_uri = urlencode(WxUtils::getCurUrl());
	            $url_auth = sprintf(C('url_auth'), $this->appid, $redirect_uri, 'snsapi_userinfo', 'interest');
	            redirect($url_auth);	
			} else {
				$this->setCode(I('get.code'));
			}	
		}

		return $this->code;
	}

	/*
	获得调用公众号接口凭证，应全局缓存
	返回 	{
				"access_token":"ACCESS_TOKEN",
				"expires_in":7200
			}
	*/
	function getAccessToken() {
		if (isset($this->cache) && $access_token = $this->cache->getTokenValue('access_token')) {
			return $access_token;
		}
		$url = sprintf(C('url_get_access_token'), $this->appid, $this->appsecret);

		if ($result = WxUtils::curl_get($url)) {
			$result = json_decode($result, true);
			if (array_key_exists('access_token', $result)) {
				if (isset($this->cache)) {
					$this->cache->setTokenValue('access_token', $result['access_token']);
				}
				return $result['access_token'];
			} else {
				die($result['errmsg']);
			}
		}
	}

	/*
	获取jsapi-ticket, 应全局缓存
	返回{
			"errcode":0,
			"errmsg":"ok",
			"ticket":"TICKET",
			"expires_in":7200
		}
	*/
	function getJsapiTicket() {
		if (isset($this->cache) && $access_token = $this->cache->getTokenValue('jsapi_ticket')) {
			return $access_token;
		}
		$url = sprintf(C('url_jsapi'), $this->getAccessToken());
		if ($result = WxUtils::curl_get($url)) {
			$result = json_decode($result, true);
			if ($result['errcode'] == 0) {
				if (isset($this->cache)) {
					$this->cache->setTokenValue('jsapi_ticket', $result['ticket']);
				}
				return $result['ticket'];
			} else {
				die($result['errmsg']);
			}
		}
	}

	/*
	获取卡卷 ticket, 应全局缓存
	返回
	*/
	function getJsapiCardTicket() {
		if (isset($this->cache) && $access_token = $this->cache->getTokenValue('jsapi_card_ticket')) {
			return $access_token;
		}
		$url = sprintf(C('url_jsapi_card'), $this->getAccessToken());

		if ($result = WxUtils::curl_get($url)) {
			$result = json_decode($result, true);
			if ($result['errcode'] == 0) {
				if (isset($this->cache)) {
					$this->cache->setTokenValue('jsapi_card_ticket', $result['ticket']);
				}
				return $result['ticket'];
			} else {
				die($result['errmsg']);
			}
		}
	}
	/*获取jsapi.config 参数
		wx.config({
		    debug: true, // 开启调试模式,调用的所有api的返回值会在客户端alert出来，若要查看传入的参数，可以在pc端打开，参数信息会通过log打出，仅在pc端时才会打印。
		    appId: '', // 必填，公众号的唯一标识
		    timestamp: , // 必填，生成签名的时间戳
		    nonceStr: '', // 必填，生成签名的随机串
		    signature: '',// 必填，签名，见附录1
		    jsApiList: [] // 必填，需要使用的JS接口列表，所有JS接口列表见附录2
		});
	参与签名的字段包括noncestr（随机字符串）, 有效的jsapi_ticket, timestamp（时间戳）, url（当前网页的URL，不包含#及其后面部分
	*/
	function getJsapiConfig() {
		$config = array();
		$config['noncestr'] = WxUtils::getRandStr();
		$config['jsapi_ticket'] = $this->getJsapiTicket();
		$config['timestamp'] = time();
		$config['url'] = WxUtils::getCurUrl();
		$config['signature'] = WxUtils::getJsSign($config);
		$config['appid'] = $this->appid;
		$config['debug'] = $this->debug;

		return $config;

	}
// card_type:团购卷：GROUPON；代金券：CASH；折扣券：DISCOUNT；礼品券：GIFT；优惠券：GENERAL_COUPON
	function getJsapiCardConfig() {
		$config = array();
		$config['noncestr'] = WxUtils::getRandStr();
		$config['api_ticket'] = $this->getJsapiCardTicket();
		$config['timestamp'] = time();
		$config['appid'] = $this->appid;
		// $config['card_type'] = 'GROUPON';
		$config['cardsign'] = WxUtils::getJsCardSign($config);
		

		return $config;
	}
	
	/*解析request，主要接受消息*/
	function parse($request)
	{
		// echo $request->getResponse();
		$this->logger->logInput();
		switch ($request->getRequestType()) {
			case 'init':
				$this->checkSignature($request);
				break;
			
			default:
				$request->setRule($this->rule);
				die($request->getResponse());
				break;
		}

	}
	/*服务器设置验证*/
	private function checkSignature($request)
	{
		$req = $request->getRequest();
        $signature = $req["signature"];
        $timestamp = $req["timestamp"];
        $nonce = $req["nonce"];	
	        		
		$tmpArr = array($this->token, $timestamp, $nonce);
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );

		$this->logger->logResponse($tmpStr.'::'.$this->token.'::'.$signature);

		if( $tmpStr == $signature ){

			die($req['echostr']);
		}else{
			
			// $this->logger->logResponse($req['echostr']);
			// print_r($request->getRequest());
			// echo $tmpStr;
			die('验证未通过');
		}
	}

	/* 检查支付通知数据签名*/

	function checkWxPayNotifySignature() {
		$data = $this-> getMessageArray();
		$sign = $data['sign'];
		if (empty($sign)) {
			die('没有签名');
		};

		unset($data['sign']);

		return $sign == WxUtils::getPaySign($data);


	}

	/*
	发送到服务器上的xml包
	返回数组
	*/
	function getMessageArray() {
		$content = file_get_contents("php://input");
		// $this->logger->log($content);
		$xml_parser = new WxXml();
		$result = $xml_parser->parse($content);
		// $this->logger->log(implode(':', array_keys($result)));
		// $this->logger->log(implode(':', $result));
		return $result;
	}


	/*获取成功返回xml
		return_code: SUCCESS/FAIL;
		return_msg: 返回信息
	*/

	function getNotifyReturnXml($return_code='SUCCESS', $return_msg='') {
		$data = array('return_code'=>array($return_code),
						'return_msg'=>array($return_msg));
		$xml_parser = new WxXml();
		return $xml_parser->arrayToXml($data);
	}

	/*创建菜单api*/
	function createMenu($arr) {


		$access_token = $this->getAccessToken();

		$url = sprintf(C('url_post_menu'), $access_token);

		if ($result = WxUtils::curl_post($url, $arr, 'json')) {

			$result = json_decode($result, true);

			if ($result['errcode'] == 0) {
				die('create menu success');
			} else {
				echo json_encode($arr, JSON_UNESCAPED_UNICODE);
				die($result['errmsg']);
			}

		};


	}



/*获取用户信息
参数access_token	网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同
	openid			用户的唯一标识
	lang			返回国家地区语言版本，zh_CN 简体，zh_TW 繁体，en 英语
返回 {
		   "openid":" OPENID",
		   "nickname": NICKNAME,
		   "sex":"1",
		   "province":"PROVINCE"
		   "city":"CITY",
		   "country":"COUNTRY",
		    "headimgurl":    "http://wx.qlogo.cn/mmopen/g3MonUZtNHkdmzicIlibx6iaFqAc56vxLSUfpb6n5WKSYVY0ChQKkiaJSgQ1dZuTOgvLLrhJbERQQ4eMsv84eavHiaiceqxibJxCfHe/46", 
			"privilege":[
			"PRIVILEGE1"
			"PRIVILEGE2"
		    ],
		    "unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL"
		}
*/
	function getUserInfo() {
		$auth_data = $this->getWebAccessToken();
		$url = sprintf(C('url_user_info'), $auth_data['access_token'], $auth_data['openid']);

		if ($result = WxUtils::curl_get($url)) {
			
			$result = json_decode($result, true);
			if (array_key_exists('openid', $result)) {

				return $result;
			} else {
				die($result['errmsg']);
			}
		}
	}
/*获取网页授权access token
参数：	appid	是	公众号的唯一标识
		secret	是	公众号的appsecret
		code	是	填写第一步获取的code参数
		grant_type	是	填写为authorization_code
返回： {
		   "access_token":"ACCESS_TOKEN",
		   "expires_in":7200,
		   "refresh_token":"REFRESH_TOKEN",
		   "openid":"OPENID",
		   "scope":"SCOPE"
		}

*/
	function getWebAccessToken() {

		$url = sprintf(C('url_url_access_token'), C('appid'), C('appsecret'), $this->getCode());
		if ($result = WxUtils::curl_get($url)) {
			$result = json_decode($result, true);
			if (array_key_exists('access_token', $result)) {
				return $result;
			} else {
				die($result['errmsg']);
			}
		}
	}
/* 刷新access token
参数 	appid	是	公众号的唯一标识
		grant_type	是	填写为refresh_token
		refresh_token	是	填写通过access_token获取到的refresh_token参数
返回 {
		   "access_token":"ACCESS_TOKEN",
		   "expires_in":7200,
		   "refresh_token":"REFRESH_TOKEN",
		   "openid":"OPENID",
		   "scope":"SCOPE"
		}

*/
	function refreshToken() {
		$URL = sprintf(C('url_url_refresh_token'), C('appid'), $this->getWebAccessToken()['refresh_token']);

		if ($result = WxUtils::curl_get($url)) {
			$result = json_decode($result, true);
			if (array_key_exists('access_token', $result)) {
				return $result;
			} else {
				die($result['errmsg']);
			}
		}

	}

	function getUserOpenId() {

		if (!I('get.code', false)) {
			$redirect_uri = urlencode(C('host').__SELF__);
            $url_auth = sprintf(C('url_auth'), C('appid'), $redirect_uri, 'snsapi_base', 'interest');
            redirect($url_auth);	
		} else {
			$this->setCode(I('get.code'));
			return $this->getWebAccessToken()['openid'];
		}	

	}

	/* WxPay
		post: <xml>
			   <appid>wx2421b1c4370ec43b</appid>
			   <attach>支付测试</attach>
			   <body>JSAPI支付测试</body>
			   <mch_id>10000100</mch_id>
			   <detail><![CDATA[{ "goods_detail":[ { "goods_id":"iphone6s_16G", "wxpay_goods_id":"1001", "goods_name":"iPhone6s 16G", "quantity":1, "price":528800, "goods_category":"123456", "body":"苹果手机" }, { "goods_id":"iphone6s_32G", "wxpay_goods_id":"1002", "goods_name":"iPhone6s 32G", "quantity":1, "price":608800, "goods_category":"123789", "body":"苹果手机" } ] }]]></detail>
			   <nonce_str>1add1a30ac87aa2db72f57a2375d8fec</nonce_str>
			   <notify_url>http://wxpay.wxutil.com/pub_v2/pay/notify.v2.php</notify_url>
			   <openid>oUpF8uMuAJO_M2pxb1Q9zNjWeS6o</openid>
			   <out_trade_no>1415659990</out_trade_no>
			   <spbill_create_ip>14.23.150.211</spbill_create_ip>
			   <total_fee>1</total_fee>
			   <trade_type>JSAPI</trade_type>
			   <sign>0CB01533B8C1EF103065174F50BCA001</sign>
			</xml>

		返回： <xml>
				   <return_code><![CDATA[SUCCESS]]></return_code>
				   <return_msg><![CDATA[OK]]></return_msg>
				   <appid><![CDATA[wx2421b1c4370ec43b]]></appid>
				   <mch_id><![CDATA[10000100]]></mch_id>
				   <nonce_str><![CDATA[IITRi8Iabbblz1Jc]]></nonce_str>
				   <openid><![CDATA[oUpF8uMuAJO_M2pxb1Q9zNjWeS6o]]></openid>
				   <sign><![CDATA[7921E432F65EB8ED0CE9755F0E86D72F]]></sign>
				   <result_code><![CDATA[SUCCESS]]></result_code>
				   <prepay_id><![CDATA[wx201411101639507cbf6ffd8b0779950874]]></prepay_id>
				   <trade_type><![CDATA[JSAPI]]></trade_type>
				</xml>

		@param $order 数组中应包括 out_trad_no, total_fee, body, spbill_create_ip
				若不提供$order ,则返回之前获取的prepayid或空值
	*/

	function getPrepayId($order=array()) {
		if (empty($order)) {
			return $this->prepay_id;
		}

		$order['appid'] = $this->appid;
		$order['mch_id'] = $this->mch_id;			// 商户号
		$order['nonce_str'] = WxUtils::getRandStr(); 		// 随机字符串
		// $order['body'] = '充值';			// 商品简单描述
		//$order['detail'] = '';			// 商品详情，json, 可选
		// $order['out_trade_no'] = '123';	//商户订单号
		// $order['total_fee'] = 1;		// 总金额， 单位为分
		// $order['spbill_create_ip'] = '';// 终端ip
		$order['notify_url'] = $this->notify_url;		// 通知地址 回调
		$order['trade_type'] = 'JSAPI';
		$order['sign'] = WxUtils::getPaySign($order);			// 签名



		$url = C('url_preorder');

		if ($result = WxUtils::curl_post($url, $order, 'xml')) {
			// echo $result;
			$xmlparser = new WxXml();
			$result = $xmlparser->parse($result);
			if ($result['return_code'] == 'SUCCESS') {
				$this->prepay_id = $result['prepay_id'];
				return $result['prepay_id'];
			} else {
				die($result['return_msg']);
			}
		}


	}

	/*
		获取微信支付参数
		   "appId" ： "wx2421b1c4370ec43b",     //公众号名称，由商户传入     
           "timeStamp"：" 1395712654",         //时间戳，自1970年以来的秒数     
           "nonceStr" ： "e61463f8efa94090b1f366cccfbbb444", //随机串     
           "package" ： "prepay_id=u802345jgfjsdfgsdg888",     
           "signType" ： "MD5",         //微信签名方式：     
           "paySign" ： "70EA570631E4BB79628FBCA90534C63FF7FADD89" //微信签名 
	*/

	function getPrepayConfig($order) {
		$config = array();

		$config['appId'] = $this->appid;
		$config['timeStamp'] = time();
		$config['nonceStr'] = WxUtils::getRandStr();
		$config['package'] = 'prepay_id='.$this->getPrepayId($order);
		$config['signType'] = 'MD5';
		$config['paySign'] = WxUtils::getPaySign($config);

		return $config;

	}




	/*
		*****************************************************WxCard*******************************************************************
	*/

	/*
		创建卡卷

		POST  {
			    "card": {
			        "card_type": "DISCOUNT",
			        "groupon": {
			            "base_info": {
			                "logo_url": "http://www.interestcoffee.com.cn/Public/home/images/logo.png",
			                "brand_name": "英趣咖啡",
			                "code_type": "CODE_TYPE_TEXT",
			                "title": "七折",
			                "sub_title": "周末狂欢必备",
			                "color": "Color010",
			                "notice": "使用时向服务员出示此券",
			                "service_phone": "020-88888888",
			                "description": "不可与其他优惠同享",
			                "date_info": {
			                    "type": "DATE_TYPE_FIX_TIME_RANGE",
			                    "begin_timestamp": 1488857364,
			                    "end_timestamp": 1488957364
			                },
			                "sku": {
			                    "quantity": 500000
			                },
			                "get_limit": 3,
			                "use_custom_code": false,
			                "bind_openid": false,
			                "can_share": true,
			                "can_give_friend": true,
			                "source": "英趣咖啡"
			            },
			            "discount": 30
			        }
			    }
			}

		return card_id
	*/

	function createCard($arr) {

		$url = sprintf(C('url_create_card'), $this->getAccessToken());

		if ($result = WxUtils::curl_post($url, $arr, 'json')) {

			$result = json_decode($result, true);
			if ($result['errcode'] == 0) {
				return $result['card_id'];
			} else {
				die($result['errmsg']);
			}
		}

	}

	/*
		投放
		二维码
		返回 {
			 "errcode": 0,
			 "errmsg": "ok",
			 "ticket":      "gQHB8DoAAAAAAAAAASxodHRwOi8vd2VpeGluLnFxLmNvbS9xL0JIV3lhX3psZmlvSDZmWGVMMTZvAAIEsNnKVQMEIAMAAA==",//获取ticket后需调用换取二维码接口获取二维码图片，详情见字段说明。
			 "expire_seconds": 1800,
			 "url": "http://weixin.qq.com/q/BHWya_zlfioH6fXeL16o ",
			 "show_qrcode_url": " https://mp.weixin.qq.com/cgi-bin/showqrcode?  ticket=gQH98DoAAAAAAAAAASxodHRwOi8vd2VpeGluLnFxLmNvbS9xL0czVzRlSWpsamlyM2plWTNKVktvAAIE6SfgVQMEgDPhAQ%3D%3D"
			 }
	*/
	function createCardQr($arr) {
		$url = sprintf(C('url_qr_card'), $this->getAccessToken());
		if ($result = WxUtils::curl_post($url, $arr, 'json')) {

			$result = json_decode($result, true);
			if ($result['errcode'] == 0) {
				return $result;
			} else {
				die($result['errmsg']);
			}
		}
	}


	/*
		获取卡卷状态
		{
		   "card_id" : "card_id_123+",
		   "code" : "123456789",
		   "check_consume" : true
		}
		 {
		"errcode":0,
		"errmsg":"ok",
		"openid":"oFS7Fjl0WsZ9AMZqrI80nbIq8xrA",
		"card":{
		"card_id":"pFS7Fjg8kV1IdDz01r4SQwMkuCKc",
		"begin_time": 1404205036,
		"end_time": 1404205036
		"can_consume":"true"
		  }
		}

		当前code对应卡券的状态， NORMAL 正常 CONSUMED 已核销 EXPIRE 已过期 GIFTING 转赠中
GIFT_TIMEOUT 转赠超时 DELETE 已删除，UNAVAILABLE 已失效； code未被添加或被转赠领取的情况则统一报错：invalid serial code
	*/
	function getCard($card_id, $code) {
		$url = sprintf(C('url_get_card'), $this->getAccessToken());
		$arr = array("card_id"=>$card_id, "code"=>$code, "check_consume"=>true);
		if ($result = WxUtils::curl_post($url, $arr, 'json')) {

			$result = json_decode($result, true);
			if ($result['errcode'] == 0) {
				return true;
			} else {
				die($result['errmsg']);
			}
		}
	}

	/*
		获取卡卷信息
		输入 card_id
		POST 	{
				  "card_id":"pFS7Fjg8kV1IdDz01r4SQwMkuCKc"
				}
		返回  array
	*/

	function getCardInfo($card_id) {
		$url = sprintf(C('url_get_card_info'), $this->getAccessToken());
		$arr = array("card_id" => $card_id);
		if ($result = WxUtils::curl_post($url, $arr, 'json')) {

					$result = json_decode($result, true);
					if ($result['errcode'] == 0) {
						return $result['card'];
					} else {
						die($result['errmsg']);
					}
				}

	}


	/*
		解码
		返回
		 {
		  "errcode":0,
		  "errmsg":"ok",
		  "code":"751234212312"
		  }
	*/

	function decrypt($encrypt_code) {
		$url = sprintf(C('url_decrypt_card'), $this->getAccessToken());
		$data = array("encrypt_code" => $encrypt_code);
		if ($result = WxUtils::curl_post($url, $data, 'json')) {

					$result = json_decode($result, true);
					if ($result['errcode'] == 0) {
						return $result['code'];
					} else {
						die($result['errmsg']);
					}
		}

	}

	/*
		销核
		$code 卡卷码
		返回
		{
		"errcode":0,
		"errmsg":"ok",
		"card":{"card_id":"pFS7Fjg8kV1IdDz01r4SQwMkuCKc"},
		"openid":"oFS7Fjl0WsZ9AMZqrI80nbIq8xrA"
		}
	*/

	function consumeCard($code){
		$url = sprintf(C('url_consume_card'), $this->getAccessToken());
		$data = array("code" => $code);
		if ($result = WxUtils::curl_post($url, $data, 'json')) {

					$result = json_decode($result, true);
					if ($result['errcode'] == 0) {
						return $result;
					} else {
						die($result['errmsg']);
					}
		}
	}

	/*
		***********************************************消息***********************************************************
	*/

	/*
		获取客服列表
	*/
	function getKfList() {
		$url = 'https://api.weixin.qq.com/cgi-bin/customservice/getkflist?access_token='.$this->getAccessToken();
		if ($result = WxUtils::curl_get($url)) {
			$result = json_decode($result, true);
			if ($result['errcode'] == 0) {
				return $result;
			} else {
				die($result['errmsg']);
			}
		}
	}
	/*
		$openid touser openid
		$msg 发送消息
		{
		    "touser":"OPENID",
		    "msgtype":"text",
		    "text":
		    {
		         "content":"Hello World"
		    }
		}
	*/
	function sendMessage($openid, $msg) {
		$url = sprintf(C('url_custom_send'), $this->getAccessToken());
		$arr = array("touser" => $openid,
					"msgtype" => 'text',
					"text" => array("content" => $msg));

		if ($result = WxUtils::curl_post($url, $arr, 'json')) {
			$result = json_decode($result, true);
			if ($result['errcode'] == 0) {
				return $result;
			} else {
				die($result['errmsg']);
			}
		}
	}


 }