<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'StreamReader.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'XMLNode.php');

define('EVENT_NODE_OPEN', 'ev_open');
define('EVENT_NODE_CLOSE', 'ev_close');

class XMLStreamReader extends StreamReader {
	
	var $currentString = '';
	
	function __construct($filename){
		parent::__construct($filename);
	}
	
	
	// xml node function
	function parseEvent(){
	
		$tag = $this->getTag();
		
		if ($tag) {
			if ($tag->hasOpeningTag) $this->eventHeap[] = new ReaderEvent($this->currentLineNumber, $tag, EVENT_NODE_OPEN);
			if ($tag->hasClosingTag) $this->eventHeap[] = new ReaderEvent($this->currentLineNumber, $tag, EVENT_NODE_CLOSE);
		}
	}
	
	// parer function
	function getTag(){
		$part = $this->currentString; 
		
		if(strlen($part == 0)) $part = $this->getLine();
		
		while($part !== false){
			
			$openPos = stripos($part, '<');
			$closePos = stripos($part, '>');
			
			if (is_int($closePos) && is_int($openPos)){
				$nodeStr = substr($part, $openPos + 1, $closePos - $openPos - 1);
				
				// d($nodeStr);
				
				$part = substr($part, $closePos + 1);
				$openPos = stripos($part, '<');
				
				while ($part && ! is_int($openPos)) {
					$add = $this->getLine();
				
					if ($add !== false) {
						$part = $part . $add;
					} else {
						$part = null;
					}
					
					$openPos = stripos($part, '<');
				}
				
				$text = trim(substr($part, 0, $openPos)); // if there is a text, it belongs to this node
				
				// d('t: '. $text);
				
				$part = substr($part, $openPos);
				$this->currentString = $part;
				
				// we have the node, lets create a proper object and get out of here.
				$node = $this->parseNode($nodeStr);
				if ($text) $node->addText($text);
				return $node;
				
			} else {
				$add = $this->getLine();
				
				if ($add !== false) {
					$part = $part . $add;
				} else {
					$part = null;
				}
			}
		}
	}
	
	
	
	// parse node (singele node, opening node, closing node)?
	// will return the created node
	// @param String $type	The creation mode, can be normal, nodeWithNode, nodeInAttribute or nodInNode
	function parseNode($nodeString, $type = 'normal'){
		
		// check if it is a closing node
		if (substr($nodeString, 0,1) == '/') {
			$node = $this->parseNameAndAttributes(substr($nodeString, 1), 'close', $type);
		
		// check if it is a single node
		} else if (substr($nodeString, -1) == '/') {
			
			$node = $this->parseNameAndAttributes(substr($nodeString, 0, -1) , 'single', $type);
		
		// add the opening node otherwise	
		} else {
			
			$node = $this->parseNameAndAttributes($nodeString, 'open', $type);
			
		}
		
		return $node;
		
	}
	
	/**
	 * Function to add a node, maybe also closing in an instance. Returns the node
	 *
	 * @param  String $nodeType can be: whitespace (empty node), tag, text, comment
	 * @param String $type	The creation mode, can be normal or nodInNode
	 */
	function addNode($name, $mode, $nodeType, $attributes = array(), $commentType = 'none', $text = '', $type = 'normal'){
		
		// d('add ' .$name . ', mode ' . $mode . ', nodeType ' . $nodeType. ', type ' . $type);
		
		// if the node is only allowded to be a singele node, we assume a single node and go one like this.
		// if (in_array($name, $this->forcedSingleNodes)) $mode = 'single';
		// if (in_array($name, $this->forcedOpenNodes)) $mode = 'open';
		
		// d($mode);
		
		$node = $this->createNode($name, $mode, $nodeType, $attributes, $commentType, $text);
		
		return $node;
	}
	
	
	/**
	 * Caled by add node. The node Factory function. In this function is no check for forced single nodes, so please be carefull to call it only with validated values
	 */
	function createNode($name, $mode, $nodeType, $attributes = array(), $commentType = 'none', $text = ''){
		
		$node = new XMLNode($name, $nodeType, null, $attributes);
		
		switch($mode){
			case 'single':
				$node->singleNode = true;
				break;
			case 'close':
				$node->hasOpeningTag = false;
				break;
			case  'open':
				$node->hasClosingTag = false;
				break;
		} 
		
		if ($text != '') $node->addText($text);
		$node->lineNumber = $this->currentLineNumber;
		
		return $node;
	}
	
	/**
	 * Takes a String and adds a node with the found attributes
	 *
	 * @param String $creationmode   Mode of the node creation. Can be 'open', 'single'
	 * @param String $type	The creation mode, can be normal, nodeWithNode or nodInNode, nodeInAttribute
	 */
	function parseNameAndAttributes($linepart, $creationmode, $type = 'normal'){
		$oPos = strpos($linepart, '<');
		$cPos = strpos($linepart, '>');
		
		if ($oPos !== false || $cPos !== false ) {
			$this->io->fatal( '"' . $linepart .  '" is no normalised node string! Line: ' .$this->currentLineNumber);
		}
		
		$nameAndAttributes = explode(' ', $linepart, 2);
		$attr = (count($nameAndAttributes) > 1) ? $this->parseAttributes($nameAndAttributes[1]) : array();
		
		
		return $this->addNode($nameAndAttributes[0], $creationmode, 'tag', $attr, 'none', '', $type);
	}
	
	// creates a list pf attribute text nodes
	function parseAttributes($attributeString){
		$attributes = array();
		
		preg_match_all('/(?P<name>[\w-]*?)\s*?=\s*?["]\s*?(?P<value>.*?)\s*?["]/', $attributeString, $matches, PREG_PATTERN_ORDER);
		
		for ($i = 0; $i < count($matches['name']); $i++){
			// normal textnode
			
			$node = $this->createNode("", "single", "text", array(), 'none', $matches['value'][$i]);		
			$attributes[$matches['name'][$i]] = $node;
		}
		
		return $attributes;
	}
	
	
	
	
}