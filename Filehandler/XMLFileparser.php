<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'Fileparser.php');


/* -----------------------------------------------------------------------
 * 
 * ----------------------------------------------------------------------- */
class XMLFileparser extends Fileparser {
	
	// config
	var $forcedSingleNodes = array();
	var $textNodes = array();
	var $ignoreAttributeNodes = array();
	var $commentClosingNodes = array();
	
	/* --------------------
	 * Constructor
	 *
	 * @param String $structureInit	- 
	 *
	 * array(
	 *		"parsingRegex" => array( , , ),			Array of Regular expressions for finding translateble parts in the file
	 *		"forcedSingleNodes" => array(, , , ),	Nodes which will be handled as single nodes, no matter a / was found or not
	 *		"textNodes" => array( , , ) 			Nodes which content will be inserted as text. No further parsing is going on
	 *		"ignoreAttributeNodes" => array( , , )  Nodes where never an attribute will be translated
	 * )
	 * 
	 * @param String $filename		- The absolute or relative Path to the file
	 */
	function __construct($file, $structureInit, $mode, $environment)  {
		
		$this->forcedSingleNodes = $structureInit['forcedSingleNodes'];
		$this->textNodes = $structureInit['textNodes'];
		$this->ignoreAttributeNodes = $structureInit['ignoreAttributeNodes'];
		
		// sort the closing nodes after key string length
		uksort($structureInit['commentClosingNodes'], function($a, $b){
			return strlen($a) < strlen($b);
		});
		
		$this->commentClosingNodes = $structureInit['commentClosingNodes'];
	
		
		parent::__construct($file, $structureInit, $mode, $environment, 'PseudoXML');
		
	}
	
	
	function getHTMLOpeningTag($node){
		$str = '';
		
		if ($node->hasOpeningTag) {
		
				$str = "<" . $node->nodeName;
					
				// attributes
				foreach ($node->attributes as $key => $value) {
					// only translate attributes that could possibly have user relevant text inside
					if ($value->nodeType == 'text') {
						if ( in_array($key, $this->structureInit['translatebleAttributesList']) && ( in_array( $node->getAttribute('type'), $this->structureInit['translatebleInputTypeList']) || $node->nodeName != 'input' )) {
							$tVal = $this->getTranslationString($value->text);
						} else {
							$tVal = $value->text;
						}
					} else {
						$tVal = $this->toHtml($value);
					}
					
					$str .= ' '.$key.'="'.$tVal.'"';
				}
				
				// node in nodes
				for ($i = 0; $i < count($node->nodeInNodes); $i++) {
					$str .= ' '. $this->toHtml($node->nodeInNodes[$i], '', false);
				}
				
				
				// close the node (single)
				if ($node->singleNode) {
					$str .= '/';
				}
				
				if (array_key_exists( $node->nodeName, $this->commentClosingNodes)){
					// close the node (special node like <!--)
					$str .= ' ';
				} else {
					// close the node (xml standard)
					$str .= '>';
				}
		}
		
		return $str;
	}
	
	function getHTMLClosingTag($node, $newLineBefore){
		
		$str = '';
		if ($node->nodeName != 'document' && $node->hasClosingTag){
			if (! $node->singleNode) {
				if (array_key_exists( $node->nodeName, $this->commentClosingNodes)) {
					// special comment close
					$add = ($newLineBefore) ? '' : ' ';
					$str .= $add . $this->commentClosingNodes[$node->nodeName];
				} else {
					// xml standard close
					$str .= '</' . $node->nodeName . ">";	
				}
			}
		}
		
		return $str;
	}
	
	function toHtml($node, $offset = "", $addoffset = true){
		
		$str = ($addoffset) ? $offset : '';
		
		
		switch ($node->nodeType){
			
			case 'whitespace':
				break;
			case 'comment':
			case 'tag':
				
				$nl = (! $node->distanceFromLeaveSmallerAs(3) || ! $node->hasOnlyOneChildTillLeave()) && $node->nodeName != 'option';
				
				if ($node->nodeName != 'document'){
					$str .= $this->getHTMLOpeningTag($node);
					
					if ($nl) $str .= "\n";
				}
				
				for ($i = 0; $i < count($node->children); $i++){
					
					// check if the indend has to stay
					$offsetChild = (in_array($node->nodeName, $this->textNodes) || $node->nodeName == 'document' ||  in_array($node->children[$i]->nodeName, $this->structureInit['noIndentNodes'])) ? $offset : $offset."\t";
					
					$str .= $this->toHtml($node->children[$i], $offsetChild, $nl);
					
					if ($nl) $str .= "\n";
				}
				
				if ($node->nodeName != 'document'){
				
					$add = ($nl) ? $offset : '';
					
					$str .= $add . $this->getHTMLClosingTag($node, $nl);
				}
				
				break;
			case 'text':
				$str .= ($node->nodeParent && $node->nodeParent->translatable) ?  $this->getTranslationString($node->text) : $node->text;
				break;
			
		}
		
		return $str;
	}
	
	// a wrapper to get the translation string in order to make it possible to override the function in child classes
	function getTranslationString($text) {
		return $this->resourceFileHandler->getTranslationString($text, $this->file->fileSystemLocation);
	}
}

?>