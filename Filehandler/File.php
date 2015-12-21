<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'/FileStructureElement.php');

class File extends FileStructureElement {
	
	var $included = 0;
	var $extension = null;
	
	function __construct($path) {
		
		if (is_file($path)) {
			parent::__construct($path, 'file');
			
			$path_parts = pathinfo($path);
			$this->extension = $path_parts['extension'];
			
		} else {
			throw new Exception("$path is no valid file.");
		}
	}

}

?>