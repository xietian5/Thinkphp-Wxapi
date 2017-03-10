<?php 

namespace Wxs;

/*
	微信请求类， 处理请求数据
*/
class WxRequest
{
	
	function __construct($_request)
	{
		$this->request = $_request;
		$this->content = file_get_contents("php://input");
		$this->xml_parser = new WxXml();
		$this->data = array();
		$this->rule = array();
		$this->response = '';
		$this->initData();
	}


	function getRequest() {
		return $this->request;
	}



	function getRequestType() {
		if (isset($this->getRequest()['echostr'])){
			return 'init';
		}
	}

	/*
		$arr = array('msg'=>array(
									"text"=>array("default"=>"halo"),
									"image"=>array(),
									"voice"=>array(),
									"video"=>array(),
									"shortvideo"=>array(),
									"location"=>array(),
									"link"=>array()

								),
					'event'=>array(
									"subscribe"=>array(),				//扫码 ， 未关注的关注后
									"SCAN"=>array(),					//扫码， 已关注
									"LOCATION"=>array(),				//用户同意上报位置，每次进入发送
									"CLICK"=>array(),				//菜单事件， click 对应菜单的类型
									"VIEW"=>array(),				//菜单事件， view
						))

	*/

	function setRule($arr) {

		
		$this->rule = $arr;
	}

	function getResponse() {
		$msgType = $this->getMsgType();
		if (!empty($msgType)) {

			$rule = $this->rule['msg'][$msgType];
			$this->response = $this->getTextMsg($rule['default']);
			foreach ($rule as $key => $value) {
				$content = $this->data['content'];
				if ($msgType == 'event') $content = $this->data['event'];
				if (in_array($content, explode(' ', $key))) {
					if (is_array($value)) {
						$this->response = $this->getImageMsg($value['media_id']);
					} else {
						$this->response = $this->getTextMsg($value);
					}
					break;
				}
			}

			// if (isset($rule[$this->data['content']])) {
			// 	$this->response = $this->getTextMsg($rule[$this->data['content']]);
			// } else {
			// 	$this->response = $this->getTextMsg($rule['default']);
			// }
		}
		return $this->response;
	}


	/*
		将数据转换成数组
	*/
	function initData() {
		$type = $this -> getContentType();
		switch ($type) {
			case 'xml':
				$this->data = $this->xml_parser->parse($this->content);
				break;
			case 'json':
				$this->data = json_decode($this->content, true);
				break;
			
			default:
				# code...
				break;
		}
	} 

	function getData() {
		return $this->data;
	}
	/*
		判断数据类型 xml / json
	*/

	function getContentType() {
		$xml_parser = xml_parser_create();
        if(xml_parse($xml_parser,$this->content,true)){
            xml_parser_free($xml_parser);
            return 'xml';
        };
        if (json_decode($data)) {
		    return 'json';
		};
	}

	function getMsgType() {
		return $this->data['msgtype'];
	}

	function getEventType() {
		return $this->data['event'];
	}
	/*
		 <xml>
		 <ToUserName><![CDATA[toUser]]></ToUserName>
		 <FromUserName><![CDATA[fromUser]]></FromUserName> 
		 <CreateTime>1348831860</CreateTime>
		 <MsgType><![CDATA[text]]></MsgType>
		 <Content><![CDATA[this is a test]]></Content>
		 <MsgId>1234567890123456</MsgId>
		 </xml>

	*/
	function getTextMsg($content) {
		$msg = array('ToUserName'=>array($this->data['fromusername']),
						'FromUserName'=>array($this->data['tousername']),
						'CreateTime'=>time(),
						'MsgType'=>array('text'),
						'Content'=>array($content),
						);
		return $this->xml_parser->arrayToXml($msg);
	}


	/*
		<xml>
		<ToUserName><![CDATA[toUser]]></ToUserName>
		<FromUserName><![CDATA[fromUser]]></FromUserName>
		<CreateTime>12345678</CreateTime>
		<MsgType><![CDATA[image]]></MsgType>
		<Image>
		<MediaId><![CDATA[media_id]]></MediaId>
		</Image>
		</xml>

	*/

	function getImageMsg($media_id) {
		$msg = "<xml>
				<ToUserName><![CDATA[%s]]></ToUserName>
				<FromUserName><![CDATA[%s]]></FromUserName>
				<CreateTime>%d</CreateTime>
				<MsgType><![CDATA[image]]></MsgType>
				<Image>
				<MediaId><![CDATA[%s]]></MediaId>
				</Image>
				</xml>";
		return sprintf($msg, $this->data['fromusername'], $this->data['tousername'], time(), $media_id);
	}



	
}