<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Fileparser.php');

class DSFileparser extends Fileparser {
	
	function __construct($file, $structureInit, $mode, $environment) {
		parent::__construct($file, $structureInit, $mode, $environment);
	}
	
	/**
	 *
	 * @param XMLNode $node		-	The xml node to parse
	 */
	function extractIncludes($node){
		
		$names = $this->getScriptIncludeFile($node->text);
		if (count($names)){
			for ($k = 0; $k < count($names); $k++){
				$name = explode(':', $names[$k]);
				$includePath = ((count($name) == 1)  ? cleanupFilename($name[0], 'ds') : cleanupFilename($name[1], 'ds'));
				$this->addIncludeFile('scripts', $includePath);
			}
		}
		
	}
	
	function extractKeys($node) {
		$this->processTranslationKeyString($node->text, $node->lineNumber);
	}
	
	function extractCSS($node) {}

}

?>