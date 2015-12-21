<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../CmdIO.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'XMLNode.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Debug/libDebug.php');

class PseudoText {
	var $io;
	
	var $doc; // the document node of the xml document. Can also handle xml files, that are not dependent on one element
	var $currentNode;
	var $currentLineNumber;
	var $filepath;
	var $filename;
	
	// config
	var $environment;
	
	// var
	var $fp; // the filepointer
	
	function __construct($filename, $environment, $structureInit){
		
		$this->environment = $environment;
		
		$this->io = new CmdIO();
		
		$this->doc = new XMLNode('document');
		$this->currentNode = $this->doc;
		
		$this->filepath = $filename;
		$fileparts = explode('/', $filename);
		$this->filename = $fileparts[count($fileparts) - 1];
		
		if (file_exists($filename)){
			$this->fp = fopen($filename, "r");
			$this->currentLineNumber = 0; // linecounter
			
			// parse the file
			$this->convertTextToNodes();
		}
	}
	
	
	function convertTextToNodes(){
		
		$part = $this->getLine();
		while($part){
			$this->addText($part);
			$part = $this->getLine();
		}
	}
	
	function getLine($addWhitespaceNodes = true){
		
		while ($line = fgets($this->fp, 4096)) {
			$this->currentLineNumber++;
			$str = trim($line);
				
			// finding all the tags
			if ($str) {
				return $line;
			} else {
				// add a single node for the whitespace
				if($addWhitespaceNodes) $this->addNode('', 'single', 'whitespace');
			}
			
		}
		
		return false;
		
	}
	
	
	function addText($text){
		
		if (trim($text)) {
			$this->addNode("", "single", "text", array(), 'none', $text);
			return true;
		}
		return false;
	}
	
	/**
	 * Function to add a node, maybe also closing in an instance. Returns the node
	 *
	 * @param  String $nodeType can be: whitespace (empty node), tag, text, comment
	 * @param String $type	The creation mode, can be normal or nodInNode
	 */
	function addNode($name, $mode, $nodeType, $attributes = array(), $commentType = 'none', $text = '', $type = 'normal'){
		
		$node = $this->createNode($name, $mode, $nodeType, $attributes, $commentType, $text);
		$this->currentNode->addChild($node);
		$node->nodeParent = $this->currentNode;
		
		return $node;
	}
	
	/**
	 * Caled by add node. The node Factory function. In this function is no check for forced single nodes, so please be carefull to call it only with validated values
	 */
	function createNode($name, $mode, $nodeType, $attributes = array(), $commentType = 'none', $text = ''){
		
		$node = new XMLNode($name, $nodeType, null, $attributes);
		if ($mode == 'single') $node->singleNode = true;
		if ($text != '') $node->addText($text);
		$node->lineNumber = $this->currentLineNumber;
		
		return ($node);
	}
	
	
	
	
	/**
	 * A function to apply a function to all nodes
	 **/
	function crawlAllNodes($function) {
		// take the call, add the textnodes as ignore children nodes and pass it to the node
		return $this->doc->crawlNode($function);
	}
	
	function printDoc(){
		$this->doc->printNode($this->io);
	}
	
	function fatal($message){
		fclose($this->fp);
		$this->io->fatal($message, get_class($this));
	}
	
}