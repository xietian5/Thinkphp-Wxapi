<?php 

namespace Wxs;

/**
* 缓存类，保存token信息
  此处默认源为thinkphp的模块对象，若使用其他源，需修改代码
*/
class WxCache
{
	
	function __construct($source)
	{
		$this->source = $source;
	}

	function getTokenValue($token_name) {
		$token = $this->source->where(array('token_name'=>$token_name))->find();

		if ($token && time() - strtotime($token['create_date']) < 7200) {
			return $token['token_value'];
		} else {
			return ;
		}
	}

	function setTokenValue($token_name, $token_value) {
		$token = $this->source->where(array('token_name'=>$token_name))->find();

		if ($token) {
			$this->source->data(array('token_value'=>$token_value, 'create_date'=>date('Y-m-d H:i:s')))->where(array('token_name'=>$token_name))->save();
		} else {
			$this->source->data(array('token_name'=>$token_name,'token_value'=>$token_value, 'create_date'=>date('Y-m-d H:i:s')))->add();
		}
	}
}