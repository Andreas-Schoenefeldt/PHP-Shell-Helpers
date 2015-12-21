<?php

class FileStructureElement {
	
	var $name;
	var $type;
	
	var $fileSystemLocation;
	
	var $isFile;
	var $isFolder;
	
	function __construct($path, $type){
		$this->type = $type;
		$this->fileSystemLocation = $path;
		
		$path_parts = pathinfo($path);
		
		switch ($type) {
			default:
				$this->name = $path_parts['basename'];
				$this->isFile = true;
				$this->isFolder = false;
				break;
			case 'folder':
				$relPath = explode('/', $path);
				$this->name = $relPath[count($relPath) - 1];
				$this->isFile = false;
				$this->isFolder = true;
				break;
		}
	}
	
}

?>