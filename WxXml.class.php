<?php
namespace Wxs;
/**
* 
*/
class WxXml {
   
  var $arrOutput = array(); 
  var $parser;
  var $current_tag;
  var $current_data;

  function arrayToXml($arr){
    $xml = "<xml>";
    foreach ($arr as $key=>$val)
    {
        if (!is_array($val)){
            $xml.="<".$key.">".$val."</".$key.">";
        }else{
             $xml.="<".$key."><![CDATA[".$val[0]."]]></".$key.">";
        }
    }
    $xml.="</xml>";
    return $xml;
  }
 
   
  function parse($xml) {
 
    // standard XML parse object setup
   
    $this->parser = xml_parser_create ();
    xml_set_object($this->parser,$this);
    xml_set_element_handler($this->parser, "tagOpen", "tagClosed");
     
    xml_set_character_data_handler($this->parser, "tagData");
 
    if(!xml_parse($this->parser, $xml)) {
      die(sprintf("XML error: %s at line %d",
        xml_error_string(xml_get_error_code($this->parser)),
        xml_get_current_line_number($this->parser)));
    }
         
    xml_parser_free($this->parser);
     
    return $this->arrOutput;
  }
 
  function tagOpen($parser, $name, $attrs) {
     
    $this->current_tag = $name;
    // print_r($attrs);
  }
 
  function tagData($parser, $tagData) {   
    $this->current_data = $tagData;
  }
 
   
  function tagClosed($parser, $name) {
 
    if ($this->current_tag == $name) {
        $name = strtolower($name);
        $this->arrOutput[$name] = $this->current_data;
    }
  }
 
}
 
