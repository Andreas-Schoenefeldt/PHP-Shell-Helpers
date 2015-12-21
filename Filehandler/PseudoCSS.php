<?php
require_once(str_replace('//','/',dirname(__FILE__).'/') .'PseudoText.php');

class PseudoCSS extends PseudoText{
	
	var $expressionMap = array();
	var $currentExpressionKey = 0;
	
	function __construct($filename, $environment, $structureInit){
		
		
		$this->commentClosingNodes = array('/*' => '*/');
		$this->textNodeList = array('/*');
		$this->forcedSingleNodes = array('childof');
		$this->forcedOpenNodes = array();
		
		parent::__construct($filename, $environment, $structureInit);
	}
	
	
	/**
	 * The function to get the document line by line and convert the textfile to a node array
	 * Main parser function for the file
	 */
	function convertTextToNodes(){
		
		$part = $this->getLine();
		
		// this function will dynamicaly get more strings, if needed
		while($part){
			
			$part = $this->parseMediaQuery($part);
			
			$part = $this->checkAndAddTextAndComments($part); // $part is a rule startig string without 
			
			// d($this->currentLineNumber . ': ' . $part);
			
			$part = $this->getRawRule($part);
			
			// d($this->currentLineNumber . ': ' . $rawRule);
			
			if ($part) {
				$part = $this->parseRule($part);
				$part = (! $part) ? $this->getLine() : $part;
			}
		}
	}
	
	
	/**
	 *	this function will get lines until the closing } of the Rule. 
	 *
	 *	Is assuming, we have a real node, not a comment
	 *
	 *	it will return somting like .om, .mani > .peme {hung: 2px;} 
	 *	
	 */
	function getRawRule($nodestart, $until = '}') {
		
		if (strlen($until) > 1) {
			throw new Exception('Abord case for raw nodes for strings with len > 1 is not jet implemented');
		}
		
		if (! $nodestart) $nodestart = $this->getLine();
		
		if ($nodestart) {
		
			$result = trim($nodestart);
			$finish = false;
			
			// parse lines, as long as it is not finished
			while(!$finish) {
				
				if (strpos($result, '}') !== false){
					$finish = true;
				}
				
				if (!$finish) {
					// ok, we are not finished with this node. Get the next line
					$add = $this->getLine(false);
					
					if ($add === false) {
						$this->printDoc();
						throw new Exception('Invalid Sytax. Closing } not found in line ' . $this->currentLineNumber);
					}
					
					$result .= ' ' . trim($add); // the single whitespace to avoid eg divhref=" -> div href="
				}
			}
			
			return $result; // we will return a whole rule
		}
		
		return $nodestart; // will return false
	}
	
	
	// check against comments, if we have one, add it as comment and get the next node
	// returns a starting node without the first < like: div class="clear">om</div>
	function checkAndAddTextAndComments($nodestart = false){
		
		if (! $nodestart) $nodestart = $this->getLine();
		
		// check if we reched the eof
		if ($nodestart !== false) {
		
			// check if we are still inside a comment
			if(array_key_exists($this->currentNode->nodeName, $this->commentClosingNodes)){
				
				$find = $this->commentClosingNodes[$this->currentNode->nodeName];
				
				if(substr(trim($nodestart), 0, strlen($find)) == $find) {
					// this is the end node, close it
					$this->closeNode($this->currentNode->nodeName);
					
					$nodestart = substr(trim($nodestart), strlen($find));
					if (! $nodestart) $nodestart = $this->getLine();
				}
				
			}
			
			// d($this->currentLineNumber . ': ' . $this->currentNode->nodeName . ' ' . $nodestart);
			// $this->io->readStdInn();
			
			
			// check if we are in textmode but not in comment mode
			if ($this->parserInTextMode() && ! array_key_exists($this->currentNode->nodeName, $this->commentClosingNodes)){
				
				
				throw new Exception('Was not here before- Implement Me ' . $nodestart);
				
				$end = '</' .  $this->currentNode->nodeName;
				
				if (strpos($nodestart, $end) !== false) {
					
					$parts = explode($end, $nodestart, 2);
					$this->addText($parts[0]);
					
					// return the finishing node for further processing
					return  '/' .  $this->currentNode->nodeName . $parts[1];
					
				} else {
					// this was only a text line, try the next one
					$this->addText($nodestart);
					return $this->checkAndAddTextAndComments();
				}
			
			// normal mode	
			} else {
				
				// find pos of /*
				$nodeName = "/*";
				
				$commentStart = strpos($nodestart, $nodeName);
				$ruleBodyStart = strpos($nodestart, '{');
				
				$ruleBodyEnd = strpos($nodestart, '}');
				// we hava a potential rule
				if ($commentStart !== false && ($ruleBodyEnd === false || $ruleBodyEnd > $commentStart)) {
					
					if ($commentStart === 0 || $ruleBodyStart === false) {
						// normal comment
						
						$startParts = explode($nodeName, $nodestart, 2);
						$this->addNode($nodeName, 'open', 'comment');
						
						return $this->handleComment($startParts[1], $nodeName, $this->commentClosingNodes[$nodeName]);
					} else {
						if ($commentStart < $ruleBodyStart) {
							$this->printDoc();
							throw new Exception('Line ' . $this->currentLineNumber . ': Can\'t Handle this string: ' . $nodestart);
						}
					}
					
					
				}
			}
		}	
		
		return $nodestart;
	}
	
	/**
	 *	Function, which will parse as long, as the appropriate comment closing string is found
	 */
	function handleComment($part, $nodeName, $commentClosingString){
		
		if ($part === false) return false;
		
		$closePos = strpos($part, $commentClosingString);
		
		if ($closePos !== false ) {
			// add as text and go on with the parsing
			$parts = explode($commentClosingString, $part, 2);
			$this->addText($parts[0]);
			$this->closeNode($nodeName);
			
			return $this->checkAndAddTextAndComments(trim($parts[1]));
			
		} else {
			$this->addText($part);
			// no end node, go on with the next line
			return $this->handleComment($this->getLine(), $nodeName, $commentClosingString);
		}
		
	}
	
	/**
	 *	Function which will transform @MEDIA .. { into a medi-query node
	 */
	function parseMediaQuery($nodeString){
		
		$mediapos = stripos($nodeString, '@');
		
		if ($mediapos !== false && substr($nodeString, $mediapos, 10) != '@font-face') { // exceptions
			
			if ($mediapos > 0) {
				throw new Exception("Don't know how to parse this media query: $nodeString");
			}
			
			$parts = explode('{', $nodeString, 2);
			
			$chunks = explode(' ',  trim(substr($parts[0], 1)), 2); // without the first @
			
			$this->addNode('media-query', 'open', 'tag', array('media' => $chunks[1], 'type' => $chunks[0]));
			
			$nodeString = trim($parts[1]);
		}
		
		if (substr($nodeString, 0, 1) == '}') {
			$this->closeNode('media-query');
			$nodeString = trim(substr($nodeString, 1));
			if (! $nodeString) $nodeString = $this->getLine();
		}
		
		return $nodeString;
	}
	
	// parse node (singele node, opening node, closing node)?
	// will return the created node
	// @param String $type	The creation mode, can be normal, nodeWithNode, nodeInAttribute or nodInNode
	function parseRule($nodeString){
		
		$parts = explode('{', $nodeString, 2);
		
		if (count($parts) < 2 ) {
			$this->printDoc();
			throw new Exception("line " . $this->currentLineNumber .": This is a invalid rule: $nodeString");
		}
		
		$rule = $this->addNode('rule', 'open', 'tag'); // open rule
		
		$head = trim($parts[0]);
		
		// handle the head
		$this->addNode('head', 'open', 'tag');
		
		$headParts = explode(',', $head);
		for ($i = 0; $i < count($headParts); $i++) {
			$this->addNode('identifier', 'open', 'tag');
			
			$this->parseIdents(trim($headParts[$i]));
			
			$this->closeNode('identifier');
		}
		
		$this->closeNode('head'); // close head
		
		
		$bodyParts = explode('}', $parts[1], 2);
		
		$this->addNode('body', 'open', 'tag');
		$body = $this->parseBody(trim($bodyParts[0]));
		$this->closeNode('body'); // close body
		
		$this->closeNode('rule'); // close rule
		
		return trim($bodyParts[1]);
	}
	
	/**
	 *	A function which is parsing the body of a css rule. Will convert font-style: italic; into <property><name>font-style</name><value>italic</value></porperty>
	 *
	 */
	function parseBody($bodyString){
		
		$bodyString = $this->cleanupLine($bodyString);
		
		$properties = explode(';', $bodyString);
		
		for ($i = 0; $i < count($properties); $i++) {
			$prop = trim($properties[$i]);
			if ($prop) {
				$parts = explode(':', $prop);
				if (count($parts) < 2){
					if (substr($parts[0], 0, 2) == '=[') {
						$this->addNode('/*', 'open', 'comment');
						
						// this is a comment
						$raw = $this->translateBack($parts[0]);
						$this->addText(trim(substr($raw, 2, strlen($raw) - 4)));
						
						$this->closeNode('/*'); 
					} else {
						throw new Exception("Wrong CSS Propertie in line " . $this->currentLineNumber . ": " . $prop);
					}
				} else {
					$this->addNode('property', 'open', 'tag');
					
					$this->addNode('name', 'open', 'tag');
					$name = trim($parts[0]);
					$this->addText($name);
					$this->closeNode('name'); // close name
					
					
					if ($name == 'font' || $name == 'font-family') {
						d($this->currentLineNumber . ' - ' . $parts[1] );
					}
					
					
					$this->addNode('value', 'open', 'tag');
					$this->addText(trim($parts[1]));
					$this->closeNode('value'); // close value
					
					$this->closeNode('property'); // close property
				}
			}
		}
	}
	
	/**
	 *	Function which will parse a single identifierstring
	 *
	 */
	function parseIdents($indentString) {
		
		$index = 0;
		$start = 0;
		$closed = false;
		
		$list = array();
		
		// eg input[type=button].smallbutton:hover
		
		while ($index < strlen($indentString)) {
			$char = substr($indentString, $index, 1);
			
			switch($char) {
				default:
					if ($closed) {
						$closed = false;
						$start = $index;
					}
					break;
				case ' ':
					if (!$closed) {
						$closed = true;
						$list[] = $this->getRuleDescription(substr($indentString, $start, $index - $start));
						$list = $this->addIndentNodes($list);
						$start = $index;
					}
					break;
				case '@':
				case '.':
				case '#':
					if ($closed) {
						$closed = false;
						$start = $index;
					} else { // combined class
						$list[] = $this->getRuleDescription(substr($indentString, $start, $index - $start));
						$start = $index;
					}
					break;
				case ':':
					$list[] = $this->getRuleDescription(substr($indentString, $start, $index - $start));
					$start = $index;
					$closed = false;
					// special case ::
					break;
				case '>':
					if (!$closed) {
						$list[] = $this->getRuleDescription(substr($indentString, $start, $index - $start));
						$list = $this->addIndentNodes($list);
					}
					$start = $index;
					$list[] = $this->getRuleDescription(substr($indentString, $start, 1));
					$list = $this->addIndentNodes($list);
					$closed = true;
					break;
			}
			
			$index++;
		}
		
		// adding the last
		$list[] = $this->getRuleDescription(substr($indentString, $start, $index - $start));
		$this->addIndentNodes($list);
		
		
	}
	
	function addIndentNodes($nodeList) {
		if (count($nodeList) > 1 ) $this->addNode('combined', 'open', 'tag');
					
		for ($l = 0; $l < count($nodeList); $l++) {
			$desc = $nodeList[$l];
			$this->addNode($desc['tagname'], $desc['mode'], 'tag');
			if ($desc['mode'] != 'single') {
				$this->addText($desc['content']);
				$this->closeNode($desc['tagname']);
			}
		}
		
		if (count($nodeList) > 1 ) $this->closeNode('combined');
		
		return array(); // resetting the list
	}
	
	function min($minArray){
		$min = false;
		for ($i = 0; $i < count($minArray); $i++){
			if ($minArray[$i] !== false) {
				if ($min === false) {
					$min = $minArray[$i];
				} else {
					$min = ($minArray[$i] < $min) ? $minArray[$i] : $min;
				}
			}
		}
		
		return $min;
	}
	
	
	function getRuleDescription($string) {
		
		$firstLetter = substr($string, 0, 1);
		$result = array(
			'mode' => 'open',
			'tagname' => 'tag',
			'content' => substr($string, 1)
		);
		
		switch ($firstLetter){
			default:
				$result['content'] = $string;
				break;
			case '.':
				$result['tagname'] = 'class';
				break;
			case '#':
				$result['tagname'] = 'id';
				break;
			case '@':
				$result['tagname'] = 'at';
				break;
			case '>':
				$result['tagname'] = 'has-child';
				$result['mode'] = 'single';
				$result['content'] = '';
				break;
			case ':':
				$result['tagname'] = 'pseudo';
				break;
		}
		
		return $result;
	}
	
	
	// retruns true, if the current node tag is part of the $textNodeList
	function parserInTextMode(){
		return (in_array($this->currentNode->nodeName, $this->textNodeList)) ? true : false;
	}
	
	function addText($text){
		
		if (trim($text)) {
			// find out if we have to deal with indentation
			$indent = '';
			$this->addNode("", "single", "text", array(), 'none', $indent . $this->translateBack($text));
			return true;
		}
		return false;
	}
	
	// to add an array as attributes
	function addAttributes($attributes){
		$this->currentNode->addAttributes($attributes);
	}
	
	
	/**
	 * Function to add a node, maybe also closing in an instance. Returns the node
	 *
	 * @param  String $nodeType can be: whitespace (empty node), tag, text, comment
	 * @param String $type	The creation mode, can be normal or nodInNode
	 */
	function addNode($name, $mode, $nodeType, $attributes = array(), $commentType = 'none', $text = '', $type = 'normal'){
		
		if ($this->currentNode->closed == false && $type !=  'nodeInNode') {
			$this->fatal('Unclosed Node was not closed. Check your markup in line ' . $this->currentLineNumber . '.', 'PseudoXML');
		}
		
		// if the node is only allowded to be a singele node, we assume a single node and go one like this.
		if (in_array($name, $this->forcedSingleNodes)) $mode = 'single';
		if (in_array($name, $this->forcedOpenNodes)) $mode = 'open';
		
		$node = $this->createNode($name, $mode, $nodeType, $attributes, $commentType, $text);
		
		switch ($type) {
			case 'nodeWithNode':
				$node->closed = false;
			case 'normal':
				$this->currentNode->addChild($node);
				break;
			case 'nodeInNode':
				
				if (!  in_array($node->nodeName, $this->allowedNodeInNodes)){
					$this->fatal('The node ' . $node->nodeName . ' in line ' . $this->currentLineNumber . ' is not allowed inside another node. Please check your markup.');
				}
				$this->currentNode->addNodeInNode($node);
				break;
		}
		
		
		$node->nodeParent = $this->currentNode;
		
		// open the node
		if ($mode == 'open' || $type == 'nodeWithNode') {
			$this->currentNode = $node;
		}
		
		return $node;
	}
	
	/**
	 * Caled by add node. The node Factory function. In this function is no check for forced single nodes, so please be carefull to call it only with validated values
	 */
	function createNode($name, $mode, $nodeType, $attributes = array(), $commentType = 'none', $text = ''){
		
		$node = new XMLNode($name, $nodeType, null, $attributes);
		
		if ($mode == 'single') $node->singleNode = true;
		
		// never translate text nodes
		if (in_array($name, $this->textNodeList)) {
			$node->translatable = false;
		}
		
		if ($commentType != 'none') $node->commentType = $commentType;
		if ($text != '') $node->addText($text);
		
		$node->lineNumber = $this->currentLineNumber;
		
		return ($node);
	}
	
	function closeNode($nodeName){
		
		// d($this->currentNode->nodeName .' : ' . $nodeName);
		
		if ($this->currentNode->nodeName == $nodeName) {
				$this->currentNode = $this->currentNode->nodeParent;
		} else {
				if ($this->currentNode->nodeParent->nodeName == $nodeName && !$this->currentNode->hasText() && $this->currentNode->getChildCount() == 0) {
						$this->currentNode->singleNode = true;
						$this->currentNode = $this->currentNode->nodeParent;
				} else {
					
						// opening node in if
						if (in_array($nodeName, $this->logicalForkNodes) && $this->currentNode->hasOffsetParent($nodeName)) {
								
								$this->currentNode->hasClosingTag = false;
								
								$parent = $this->currentNode->getOffsetParent($nodeName);
								$this->currentNode = $parent->nodeParent;
								
						
						// closing node in if
						} else if (in_array($this->currentNode->nodeName, $this->logicalForkNodes)) {
								
								$current = $this->currentNode;
								$this->addNode($nodeName, 'open', 'tag');
								$this->currentNode->hasOpeningTag = false;
								
								$this->currentNode = $current;
																
						} else {
						
								$this->printDoc();
								
								$this->io->cmd_print("line ".$this->currentLineNumber." - ".$nodeName);
								$this->fatal('Not the right closing tag. '.$this->currentNode->nodeName.' expected');
						}
				}
			
			
		}
	}
	
	
	/** Replaces all url expressions with an unproblematic replacement string
	 *
	 */
	function cleanupLine($line){
		preg_match_all('/url[ ]*?\([ ]*?[\'"]?[ ]*?(?P<expression>.*?)[ ]*?[\'"]?[ ]*?\)/', $line, $matches, PREG_PATTERN_ORDER);
		preg_match_all('/(?P<expression>\/\*.*?\*\/)/', $line, $matches2, PREG_PATTERN_ORDER); 
		
		$matches = array_merge($matches[1], $matches2[1]);
		
		for ($i = 0; $i < count($matches); $i++){
			$match = $matches[$i];
			$replaceKey = "=[" . $this->currentExpressionKey . "]=";
			$line = str_replace($match, $replaceKey, $line);
			$this->currentExpressionKey++;
			
			$this->expressionMap[$replaceKey] = $match;
			
		}
		
		return $line;
		
	}
	
	/** Puts complicated expresions back again */
	function translateBack($string){
		
		// try the expressions
		preg_match_all('/(?P<replace>=\[.*?\]=)/', $string, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches['replace']); $i++){
			$match = $matches['replace'][$i];
			$string = str_replace($match, $this->expressionMap[$match], $string);
		}
		 
		return $string;
	}
	
}

?>