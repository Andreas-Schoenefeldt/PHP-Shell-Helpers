<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'/FileStructureElement.php');

class Folder extends FileStructureElement {
	
	var $children = array();
	var $orderedChildren = array();
	var $childCount = 0;
	
	function __construct($path) {
		parent::__construct($path, 'folder');
	}
	
	
	function hasChild($name) {
		return array_key_exists($name, $this->children);
	}
	
	function addFolder($name) {
		$folder = new Folder($this->fileSystemLocation . '/' . $name);
		$this->addChild($name,  $folder);
	}
	
	function addChild($name, $fileSystemElement) {
		$this->children[$name] = $fileSystemElement;
		$this->orderedChildren[] = $fileSystemElement;
		$this->childCount++;
	}
	
	function getChild($index) {
		
		if (is_integer($index)) {
			if ($index < count($this->orderedChildren)) {
				return $this->orderedChildren[$index];
			}
			
		} else {
			if (array_key_exists($index, $this->children)) {
				return $this->children[$index];
			}
			
		}
		
		return null;
	}
	
	/**
	 * Returns the first child matching the given pathstructure of this folder
	 */
	function getDeepChild($relPath){
		$relPath = (is_array($relPath)) ? $relPath : explode('/', $relPath);
		
		$next = array_shift($relPath);
		while (! $next && count($relPath) > 0) {
			$next = array_shift($relPath);
		}
		
		$child = $this->getChild($next);
		
		if (! $child) {
			return $child;
		} else {
			return (count($relPath) == 0) ?  $child : $child->getDeepChild($relPath);
		}
	}

}

?>