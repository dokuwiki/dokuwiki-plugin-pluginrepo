<?php

/*
Function : xml_encode,xml_decode
Author : Shingwa Six
Source code from http://blog.waaile.com/array-to-xml
*/

function xml_encode($value, $tag = "root") { 
  if( !is_array($value) 
		&& !is_string($value)
		&& !is_bool($value)
		&& !is_numeric($value)
		&& !is_object($value) ) {
			return false;
	}
	function x2str($xml,$key) {
		if (!is_array($xml) && !is_object($xml)) {
			return "<$key>".htmlspecialchars($xml)."</$key>";      
		}
		$xml_str="";
		foreach ($xml as $k=>$v) {   
			if(is_numeric($k)) {
				$k = "_".$k;
			}
			$xml_str.=x2str($v,$k);       
		}    
		return "<$key>$xml_str</$key>";  
	}
	return simplexml_load_string(x2str($value,$tag))->asXml();
}

function xml_decode($xml) {
	if(!is_string($xml)) {
		return false;
	}
	$xml = @simplexml_load_string($xml);
	return $xml;
}
?>
