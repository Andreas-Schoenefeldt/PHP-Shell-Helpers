<?php


class XMLNode{
	
	var $nodeName; // the nodeName (tag name)
	var $attributes = array(); // assoziative array of the attributes (Attributes are textnodes)
	var $children = array(); // normal array of the children
	var $nodeParent;
	var $nodeInNodes = array(); // array of the node in nodes
	var $id = null;
	
	var $closed = true;
	
	var $lineNumber;
	
	var $singleNode = false; // weather it is a node together with an end tag: <om />
	var $nodeType = null; // defines the nodeType. Can be whitespace, tag, text, comment
	
	var $text = null; // the text of a single textnode
	
	var $translatable = true; // the direct children of this node will not be translated
	
	var $hasOpeningTag = true;
	var $hasClosingTag = true;
	
	
	// attributes are expected as XMLNodes. If a string is passed, a new Textnode will be created
	function __construct($name, $nodeType = "tag", $parent = null, $attributes = array()){
		$this->id = uniqid();
		$this->nodeName = $name;
		$this->nodeType = $nodeType;
		$this->nodeParent = $parent;
		
		$this->addAttributes($attributes);
	}
	
	/**
	 * Function to apply some function at the node and his children
	 **/
	function crawlNode($function, $depth = 0) {
		
		$processChildren = $function($this);
		
		if ($processChildren) {
			for ($i = 0; $i < count($this->children); $i++){
				$this->children[$i]->crawlNode($function, $depth++ );
			}
		}
		
	}
	
	/* -----------------
	Child Functions  
	--------------------- */
	
	function addChild($node){
		$this->children[] = $node;
	}
	
	function removeChildById($childId) {
		for ($i = 0; $i < $this->getChildCount(); $i++) {
			if ($this->getChild($i)->id == $childId) {
				unset($this->children[$i]);
				$this->children = array_values($this->children);
				break;
			}
		}
	}
	
	function addNodeInNode($node){
		$this->nodeInNodes[] = $node;
	}
	
	function getChildCount(){
		return count($this->children);
	}
	
	function getChild($index){
		return $this->children[$index];
	}
	
	function getText(){
		if ($this->nodeType == "text") return $this->text;
		
		$found = false;
		$text = '';
		for ($i = 0; $i < count($this->children); $i++) {
			
			if ($this->children[$i]->nodeType == "text") {
				if ($found) $text .=  "\n";
				$text .= $this->children[$i]->text;
				$found = true;
			} 
		}
		
		return $text;
	}
	
	function addText($text){
		if ($this->nodeType == "text") {
			$this->text = $text;
		} else {
			$node = new XMLNode("", "text", $this);
			$node->addText($text);
			$this->addChild($node);
		}
	}
	
	// inspired by jquery find
	function find($childNodeName){
		$result = array();
		for ($i = 0; $i < $this->getChildCount(); $i++){
			
			$child = $this->getChild($i);
			
			if ($child->nodeName == $childNodeName) {
				$result[] = $child;
			}
			// find works on all children
			$result = array_merge($result, $child->find($childNodeName));
		}
		
		return $result;
	}
	
	function getInlineChildrenFromNode($startChildNode, $inlineNodes){
		$result = array();
		$start = false;
		for ($i = 0; $i < $this->getChildCount(); $i++){
			$child = $this->getChild($i);
			if ($child->id == $startChildNode->id) {
				$start = true;
			}
			
			if ($start && ($child->text || in_array($child->nodeName, $inlineNodes)) && $child->hasOnlyInlineChildren($inlineNodes)) {
				$result[] = $child;
			} else {
				$start = false;
				break;
			}
		}
		
		return $result;
	}
	
	function hasOnlyInlineChildren($inlineNodes) {
		
		$result = ($this->text || in_array($this->nodeName, $inlineNodes));
		
		if ($result) {
			for ($i = 0; $i < $this->getChildCount(); $i++){
				$child = $this->getChild($i);
				$result = $result && $child->hasOnlyInlineChildren($inlineNodes);
				if (! $result) break;
			}
		}
		
		return $result;
	}
	
	/**
	 *	Removes this node;
	 *	
	 */
	function remove(){
		if($this->nodeParent) {
			$this->nodeParent->removeChildById($this->id);
		} else {
			throw new Exception("Don't know how to remove the Root node");
		}
	}
	
	/**
	 * Replaces an specific amount of nodes with a new single node
	 *
	 *	@param 	XMLNode $firstChildToReplace		-	The first child, were the replace starts
	 *	@param	Int		$childlength				-	The amount of childs, which are deleted
	 *	@param	XMLNode	$replaceNode				-	The new child, which is inserted
	 */
	function replaceNChildrenWithNode($firstChildToReplace, $childlength, $replaceNode){
		$newChildren = array();
		
		$omit = false;
		for ($i = 0; $i < $this->getChildCount(); $i++){
			$child = $this->getChild($i);
			if ($child->id == $firstChildToReplace->id) {
				$newChildren[] = $replaceNode;
				$omit = true;
				$childlength--;
			} else {
				$childlength--;
			}
			
			if ($childlength < 0) $omit = false;
			
			if (!$omit) {
				$newChildren[] = $child;
			}
		}
		
		$this->children = $newChildren;
	}
	
	/* -----------------
	Sibling Functions  
	--------------------- */
	
	function getInlineSiblingsFromThisNode($inlineNodes = array()){
		return $this->nodeParent->getInlineChildrenFromNode($this, $inlineNodes);
	}
	
	
	/* -----------------
	Parent Functions  
	--------------------- */
	
	function hasOffsetParent($selector) {
		$selector = $this->processSelector($selector);
		
		
		if ($this->nodeParent) {
			$found = false;
			for ($i = 0; $i < count($selector); $i++) {
				if ($this->nodeParent->nodeName == $selector[$i]) {
					$found = true;
					break;
				}
			}
			
			return ($found) ? $found : $this->nodeParent->getOffsetParent($selector);
		}
		
		return false;
	}
	
	function getOffsetParent($selector) {
		
		$selector = $this->processSelector($selector);
		
		if ($this->nodeParent) {
			$found = false;
			for ($i = 0; $i < count($selector); $i++) {
				if ($this->nodeParent->nodeName == $selector[$i]) {
					$found = $this->nodeParent;
					break;
				}
			}
			
			return ($found) ? $found : $this->nodeParent->getOffsetParent($selector);
		}
		
		return null;
	}
	
	function getParent(){
		return $this->nodeParent;
	}
	
	
	function hasText() {
		if ($this->nodeType == "text") return true;
		
		for ($i = 0; $i < count($this->children); $i++) {
			if ($this->children[$i]->nodeType == "text") return true;
		}
		
		return false;
	}
	
	// Add attributes
	function addAttributes($attributes = array()){
		
		foreach ($attributes as $key => $node){
			if (is_string($node)){
				$attributes[$key] = new XMLNode("", "text");
				$attributes[$key]->addText($node);
			};
			
			$this->attributes[$key] = $attributes[$key];
		}
		
	}
	
	
	function getChildWithAttributeValue($key, $value, $nodeName = false){
		
		for ($i = 0; $i < count($this->children); $i++) {
			$child = $this->children[$i];
			if ($child->getAttribute($key) == $value) {
				if ($nodeName === false || $child->nodeName == $nodeName) return $child;
			}
		}
		
		return null;
	}
	
	/**
	 * Function to return the value of the attribute defined by $key or null instead.
	 */
	function attr($key){return $this->getAttribute($key);}
	function getAttribute($key) {
		$value = null;
		
		if ($this->hasAttribute($key)) {
			if ($this->attributes[$key]->nodeType == 'text'){
				$value = $this->attributes[$key]->text;
			}
		}
		
		return $value;
	}
	
	/**
	 * Return true, if the attribute key exists, false otherwise
	 */
	function hasAttribute($key) {
		return array_key_exists( $key, $this->attributes) ? true : false;
	}
	
	
	function hasOnlyOneChildTillLeave(){
		
		switch (count($this->children)){
			default:
				return false;
				break;
			case 0:
				return true;
				break;
			case 1:
				return $this->children[0]->hasOnlyOneChildTillLeave();
				break;
		}
		
	}
	
	// Will return true or false, if the node is more then $maxDistance away from the leave
	function distanceFromLeaveSmallerAs($maxDistance){
		
		$dist = $this->getDistanceFromLeave(0, $maxDistance);
		
		return ($dist === false) ? $dist : $dist < $maxDistance;
	}
	
	// returns the distance from the current leave to the most far away leave
	// @return mixed wil return false, if a maxdistance was given, the distance otherwise
	function getDistanceFromLeave($currentDistance = 0, $maxDistance = 0) {
		if ($maxDistance > 0 && $currentDistance > $maxDistance) {
			return false;
		} else {
			if (count($this->children) > 0) {
				
				$currentMax = $currentDistance;
				
				for ($i = 0; $i < count($this->children); $i++) {
					$dist = $this->children[$i]->getDistanceFromLeave($currentDistance + 1, $maxDistance);
					if ($dist === false) {
						return $dist;
					} else {
						$currentMax = ($dist > $currentMax) ? $dist : $currentMax;
					}
				}
				
				return $currentMax;
				
			} else {
				return $currentDistance;
			}
		}
	}
	
	/**
	 *	Will convert a string into an readable array.
	 *	so far are allowd nodeNames and , as seperator
	 */
	function processSelector($selector){
		
		if (! is_array($selector)) {
			$parts = explode(',', $selector);
			$selector = array();
			for ($i = 0; $i < count($parts); $i++) {
				$selector[] = trim($parts[$i]);
			}
		}
		
		
		return $selector;
	}
	
	
	
	function toString($extensive = false) {
		$res = '[XMLNode ' . $this->nodeName . '] line ' . $this->lineNumber;
		if ($extensive) {
			$res .= " (Single: {$this->singleNode}, Type: {$this->nodeType})";
		}
		return $res;
	}
	
	
	function printNode($io, $offset = ''){
		
		$add = ($this->nodeType == "text") ? "[".$this->nodeType."] ".$this->text : "";
		
		if (count($this->nodeInNodes) > 0) {
			$add .= ' ( with node in node: ';
			for ($i = 0; $i < count($this->nodeInNodes); $i++){
				$add .= $this->nodeInNodes[$i]->nodeName . ' ';
			}
			$add .= ')';
			
		}
		
		if (! $this->hasOpeningTag) $add .= ' (without open tag)';
		if (! $this->hasClosingTag) $add .= ' (without closing tag)';
		
		$io->cmd_print($offset.$this->nodeName.$add);
		
		for ($i = 0; $i < count($this->children); $i++){
			$this->children[$i]->printNode($io, $offset.'  ');
		}
	}
	
}

?>