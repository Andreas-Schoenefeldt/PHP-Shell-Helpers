<?php
require_once(str_replace('//','/',dirname(__FILE__).'/') .'PseudoText.php');

// this class comnverts a file into php xmlNode Objects
// only usable for smaller files, use the xmlStreamReader for large ones

class PseudoXML extends PseudoText {
	
	var $currentIndent; // the indent of the line. Is used for the textnode mode
	var $textIndent; // the base text indent
	var $textBlockStartNumber; // the amount of text, that was added
	
	var $expressionMap = array();
	var $currentExpressionKey = 0;
	
	var $textNodeList = array(); // nodes which contents will be parsed as text, no matter what
	var $forcedSingleNodes = array(); // nodes which never have a closing tag
	var $forcedOpenNodes = array(); // nodes, that never can be single nodes
	var $commentClosingNodes = array(); // this defines the closing nodes with array('tagname' => 'full closing string' ) Is required to be sorted after key length
	var $allowedNodeInNodes = array(); // if node in nodes are allowed, they should be listed here
	var $logicalForkNodes = array(); // list of if statement nodes, which are allowed
	
	function __construct($filename, $environment = "XML", $structureInit = array()){
		
		if ($structureInit['textNodes']) $this->textNodeList = $structureInit['textNodes'];
		if ($structureInit['forcedSingleNodes']) $this->forcedSingleNodes = $structureInit['forcedSingleNodes'];
		if ($structureInit['forcedOpenNodes']) $this->forcedOpenNodes = $structureInit['forcedOpenNodes'];
		if ($structureInit['commentClosingNodes']) $this->commentClosingNodes = $structureInit['commentClosingNodes']; // Is required to be sorted after key length
		if ($structureInit['allowedNodeInNodes']) $this->allowedNodeInNodes = $structureInit['allowedNodeInNodes'];
		if ($structureInit['logicalForkNodes']) $this->logicalForkNodes = $structureInit['logicalForkNodes'];
		
		parent::__construct($filename, $environment, $structureInit);
	}
	
	
	function getLine($addWhitespaceNodes = true){
		
		while ($line = fgets($this->fp, 4096)) {
			// d($this->currentLineNumber);
			$this->currentLineNumber++;
			$str = trim($line);
				
			// finding all the tags
			if ($str) {
				$ws = substr($line, 0, strpos($line, $str));
				$this->currentIndent = $ws;
				
				// first cleanup to catch logical expressions
				$str =  str_replace("\n", '', $this->cleanupLine($line));
				
				// second start the parser
				return $str;
			} else {
				// add a single node for the whitespace
				if($addWhitespaceNodes) $this->addNode('', 'single', 'whitespace');
			}
			
		}
		
		return false;
		
	}
	
	/**
	 * The function to get the document line by line and convert the textfile to a node array
	 * Main parser function for the file
	 */
	function convertTextToNodes(){
		
		$part = $this->getLine();
		
		// this function will dynamicaly get more strings, if needed
		
		$type = 'normal';
		
		while($part){
			
			$closePos = stripos($part, '>');
			$newOpenPos = stripos($part, '<');
			
			// d($this->currentLineNumber . ' ' . $type . ': - ' . $part);
			// d($this->currentNode);
			
			if(!$this->currentNode->closed){
				if(!is_int($newOpenPos) && is_int($closePos) || $closePos < $newOpenPos){ 
					
					$parts = explode('>', $part, 2);
					
					$this->currentNode->addAttributes($this->parseAttributes($parts[0]));
					$this->currentNode->closed = true;
					
					// d("finished node " . $this->currentNode->nodeName);
					
					if ($this->currentNode->singleNode) {
						$this->closeNode($this->currentNode->nodeName);
					}
					
					$type = 'normal';
					$part = $parts[1];
				} else {
					// we have another node in node, parse the string as attributes and continue with the node
					$type = 'nodeInNode';
					
					$parts = explode('<', $part, 2);
					
					// everything before the node in node is a attribute. The nodename was already parsed
					$attributes = $this->parseAttributes($parts[0]);
					$part = $parts[1];
				}
			}
			
			if($type != 'nodeInNode'){
				// no comments or simpe text in nodes. Only attributes
				
				// d($part . '|');
				// check against comments, if we have one, add it as comment and get the next node
				$part = $this->checkAndAddTextAndComments($part); // $part is a tag without the first < ! Will be something like: div style="">om</div>
				
			}
			
			if ($part !== false) {
			
				// d($type . ' - ' . $part);
							
				// $this->printDoc();
				// d($nodeLine . '|');
				
				// find first >
				
				$rawNode = $this->getRawNode($part);
				
				// d($this->currentLineNumber . ' ' . $type . ': - ' . $rawNode);
				//$this->io->readStdInn();
				
				$closePos = stripos($rawNode, '>');
				$newOpenPos = stripos($rawNode, '<');
					
				// check if there is < inbetween
				if( is_int($newOpenPos) && $newOpenPos < $closePos) {
					// yes: node in node
					$parts = explode('<', $rawNode, 2);
					
					$this->parseNode(trim($parts[0]), 'nodeWithNode');
					$part = '<' . $parts[1];
					
					$type = 'nodeInNode';
					
				} else {
					// no: parse node or node in node
					
					$secParts = explode('>', $rawNode, 2);
					$this->parseNode($secParts[0], $type);
					$type = 'normal';
					
					$part = $secParts[1];
				}
			}
			
			if (! $part) $part = $this->getLine();
			
				// have we reached the end of file?
				if ($part === false) {
						fclose($this->fp);
						return;
				}
			
		}
	}
	
	
	/** Replaces all logical expressions with an unproblematic replacement string
	 *
	 */
	function cleanupLine($line){
		preg_match_all('/(?P<expression>\${.*?})/', $line, $matches, PREG_PATTERN_ORDER); // jsp expressions
		preg_match_all('/(?P<expression>=\[.*?\]=)/', $line, $matches2, PREG_PATTERN_ORDER); // internal expressions, just in case somone is using this by exident
		
		$matches = array_merge($matches['expression'], $matches2['expression']);
		
		for ($i = 0; $i < count($matches); $i++){
			
			$match = $matches[$i];
			$replaceKey = "=[" . $this->currentExpressionKey . "]=";
			$line = str_replace($match, $replaceKey, $line);
			$this->currentExpressionKey++;
			
			$this->expressionMap[$replaceKey] = $match;
			
		}
		
		return $line;
		
	}
	
	/** Puts the logical expressions back again */
	function translateBack($string){
		// first try the node in attributes
		preg_match_all('/(?P<replace>-\[.*?\]-)/', $string, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches['replace']); $i++){
			$match = $matches['replace'][$i];
			$string = str_replace($match, $this->expressionMap[$match], $string);
		}
		
		// second try the expressions
		preg_match_all('/(?P<replace>=\[.*?\]=)/', $string, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches['replace']); $i++){
			$match = $matches['replace'][$i];
			$string = str_replace($match, $this->expressionMap[$match], $string);
		}
		
		// d('TB: '. $string);
		return $string;
	}
	
	/**
	 *	this function will get lines until the closing > of the starting node. It will also escape node in attributes
	 *	it is assuming to get someting like: div title="" .. >om</div> or the like. Do not path a node string with a starting <
	 *
	 *	Is assuming, we have a real node, not a comment
	 *	
	 */
	function getRawNode($nodestart, $until = '>') {
		
		if (strlen($until) > 1) {
			$this->io->fatal('Abord case for raw nodes for strings with len > 1 is not jet implemented','PseudoXML');
		}
		
		$result = '';
		$open = false;
		$nodeLevel = 0;
		
		$start = 0;
		$parse = false;
		$finish = false;
		$pointerEnd = 0; // to carry the $i in the next while loop
		
		// d($nodestart);
		
		// parse lines, as long as it is not finished
		while(!$finish) {
		
			// now escape all the nodes in attributes
			for ($i = $pointerEnd; $i < strlen($nodestart); $i++) {
				
				switch($nodestart[$i]){
					case '"':
						
						// d($i . ' - ' . $nodeLevel);
						// d($parse);
						
						if ($nodeLevel == 0) {
							if ($open === false){
								$open = $i + 1;
							} else {
								if ($parse) {
									// this is a close, process
									$match = substr($nodestart, $open, $i - $open);
									
									// d($match);
									
									$replaceKey = "-[" . $this->currentExpressionKey . "]-";
									$result .= substr($nodestart, $start, $open - $start) . $replaceKey;
									
									// d($result);
									
									$this->currentExpressionKey++;
									$this->expressionMap[$replaceKey] = $match;
									
									// reset of starter, just in case there is another node in attribute
									$start = $i;
									$parse = false;
								}
								
								$open = false;
							} 
						}
						break;
					case '<':
						$nodeLevel++;
						if ($open !== false) {
							$parse = true;
						} 
						break;
					case '>':
						$nodeLevel--;
						break;
				}
				
				if ($nodeLevel < 0) {
					$result .= substr($nodestart, $start);
					$finish = true;
					
					 //d($result);
					
					break; // close the loop here and go on with the next while
				}
				
				
			}
			$pointerEnd = $i;
			
			if (!$finish) {
				// ok, we are not finished with this node. Get the next line
				$add = $this->getLine(false);
				
				if ($add === false) {
					d($nodestart);
					$this->io->fatal('Invalid Markup. Closing Tag not found in line ' . $this->currentLineNumber);
				}
				
				$nodestart .= ' ' . trim($add); // the single whitespace to avoid eg divhref=" -> div href="
			}
		}
		
		return $result; // we will return a line with no node in attributes and a whole node
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
			
			// normal text mode	
			} else {
				
				// find pos of <
				$startParts = explode('<', $nodestart, 2);
				$this->addText($startParts[0]);
				
				if (count($startParts) > 1) {
				
					$nodestart = $startParts[1];
					$try = false; // default reset
						
					foreach ($this->commentClosingNodes as $nodeName => $nodeClosingString){
						if (substr($nodestart, 0, strlen($nodeName)) == $nodeName) {
							
							$this->addNode($nodeName, 'open', 'comment');
							
							$part = trim(substr($nodestart, strlen($nodeName)));
							$try = true;
							break;
						}
					}
					
					if ($try) {
						// we have a comment, now handle it
						return $this->handleComment($part, $nodeName, $nodeClosingString);
						
					} 
				} else {
					// this was only a text line, try the next node
					return $this->checkAndAddTextAndComments();
					
				}
			}
		}	
		
		return $nodestart;
	}
	
	/**
	 *	Function, which will parse as long, as the appropriate comment closing string is found
	 */
	function handleComment($part, $nodeName, $commentClosingString){
		
		// d('handle comment: ' . $part .', name ' . $nodeName . ', closedBy ' .$commentClosingString);
		
		if ($part === false) return false;
		
		$closePos = strpos($part, $commentClosingString);
		
		// check if there is a new opening comment node before the closing
		$newOpening = false;
		foreach ($this->commentClosingNodes as $cNodeName => $nodeClosingString){
			$oPos = strpos($part, '<' . $cNodeName);
			$newOpenBeforeEnd = $closePos !== false && $oPos !== false && $oPos < $closePos;
			$newOpenInLine = $oPos !== false && $closePos === false;
			
			if( $newOpenBeforeEnd || $newOpenInLine) {
				$newOpening = true;
				break;
			} 
		}
		
		
		if (!$newOpening && $closePos !== false ) {
			// add as text and go on with the parsing
			$parts = explode($commentClosingString, $part, 2);
			$this->addText($parts[0]);
			$this->closeNode($nodeName);
			
			return $this->checkAndAddTextAndComments(trim($parts[1]));
			
		} else if ($newOpening) {
			
			$parts = explode('<', $part, 2);
			$this->addText($parts[0]);
			
			return $this->checkAndAddTextAndComments('<' . $parts[1]);
			
		} else {
			$this->addText($part);
			
			// no end node, go on with the next line
			return $this->handleComment($this->getLine(), $nodeName, $commentClosingString);
		}
		
	}
	
	
	// parse node (singele node, opening node, closing node)?
	// will return the created node
	// @param String $type	The creation mode, can be normal, nodeWithNode, nodeInAttribute or nodInNode
	function parseNode($nodeString, $type = 'normal'){
		
		// check if it is a closing node
		if (substr($nodeString, 0,1) == '/') {
			$node = $this->currentNode;
			$this->closeNode(substr($nodeString, 1));
		
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
	 * Takes a String and adds a node with the found attributes
	 *
	 * @param String $creationmode   Mode of the node creation. Can be 'open', 'single'
	 * @param String $type	The creation mode, can be normal, nodeWithNode or nodInNode, nodeInAttribute
	 */
	function parseNameAndAttributes($linepart, $creationmode, $type = 'normal'){
		$oPos = strpos($linepart, '<');
		$cPos = strpos($linepart, '>');
		
		if ($oPos !== false || $cPos !== false ) {
			$this->fatal( '"' . $linepart .  '" is no normalised node string! Line: ' .$this->currentLineNumber);
		}
		
		$nameAndAttributes = explode(' ', $linepart, 2);
		$attr = (count($nameAndAttributes) > 1) ? $this->parseAttributes($nameAndAttributes[1]) : array();
		
		if ($type == 'nodeInAttribute'){
			return $this->createNode($nameAndAttributes[0], "single", 'tag', $attr);
		} else {
			return $this->addNode($nameAndAttributes[0], $creationmode, 'tag', $attr, 'none', '', $type);
		}
	}
	
	
	function parseAttributes($attributeString){
		$attributes = array();
		
		preg_match_all('/(?P<name>[\w-]*?)\s*?=\s*?["]\s*?(?P<value>.*?)\s*?["]/', $attributeString, $matches, PREG_PATTERN_ORDER);
		
		for ($i = 0; $i < count($matches['name']); $i++){
			
			$node = false;
			
			// take care of single node in node
			if (array_key_exists($matches['value'][$i], $this->expressionMap)) {
				
				$value = trim($this->expressionMap[$matches['value'][$i]]);
				
				// do we have a single singlenode in node? <if><else/></if> does not count
				if((substr($value, 0, 1)) == '<' &&  substr($value, -1) == '>' && substr_count($value, '>') < 2){
					$node = $this->parseNode( substr($value, 1, -1) ,'nodeInAttribute');
				}
			}
			
			if ($node === false) {
				// normal textnode
				$node = $this->createNode("", "single", "text", array(), 'none', $this->translateBack($matches['value'][$i]));
			}
			
			
			$attributes[$matches['name'][$i]] = $node;
		}
		
		return $attributes;
	}
	
	
	
	// retruns true, if the current node tag is part of the $textNodeList
	function parserInTextMode(){
		return (in_array($this->currentNode->nodeName, $this->textNodeList)) ? true : false;
	}
	
	function addText($text){
		
		if (trim($text)) {
			// find out if we have to deal with indentation
			$indent = ($this->parserInTextMode() && $this->textBlockStartNumber < $this->currentLineNumber) ?  substr($this->currentIndent, strlen($this->textIndent)) : '' ;
			$this->addNode("", "single", "text", array(), 'none', $indent . $this->translateBack(trim($text)));
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
		
		// d('add ' .$name . ', mode ' . $mode . ', nodeType ' . $nodeType. ', type ' . $type);
		
		if ($this->currentNode->closed == false && $type !=  'nodeInNode') {
			$this->fatal('Unclosed Node was not closed. Check your markup in line ' . $this->currentLineNumber . '.', 'PseudoXML');
		}
		
		// if the node is only allowded to be a singele node, we assume a single node and go one like this.
		if (in_array($name, $this->forcedSingleNodes)) $mode = 'single';
		if (in_array($name, $this->forcedOpenNodes)) $mode = 'open';
		
		// d($mode);
		
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
		
		// set the textnode indent, if the parser is currently in textmode
		if ($nodeType != 'text' && $nodeType != 'whitespace' && $this->parserInTextMode()) {
			$this->textIndent = $this->currentIndent;
			$this->textBlockStartNumber = $this->currentLineNumber;
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
								$this->fatal('Not the right closing tag: '.$this->currentNode->nodeName.' expected. (' . $this->filename . ')');
						}
				}
			
			
		}
	}
	
}

?>