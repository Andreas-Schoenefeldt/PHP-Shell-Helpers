<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../XMLFileparser.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'DemandwareFormHandler.php');

class FormFileparser extends XMLFileparser {
	var $translatableFormAttributes = array('label', 'missing-error', 'value-error', 'parse-error', 'range-error', 'description');
	
	function __construct($file, $structureInit, $mode, $environment) {
		
		$structureInit['translatebleAttributesList'] = $this->translatableFormAttributes; // override the translatable attributes in order to make the writing of the formas take the tranlsation into account
		parent::__construct($file, $structureInit, $mode, $environment);
	}
	
	/**
	 *
	 * @param XMLNode $node		-	The xml node to parse
	 */
	function extractIncludes($node){
		
		$formHandler = new DemandwareFormHandler($this);
		
		// form include
		$formHandler->checkFormNodeForForms($node);
		
		
	}
	
	function extractKeys($node) {
		
		switch ($node->nodeName) {
			case 'field':
				
				for ($i = 0; $i < count($this->translatableFormAttributes); $i++){
					if($attr = $node->attr($this->translatableFormAttributes[$i])) {
						$this->addTranslationKey($attr, 'forms', $node->lineNumber);
					}
				}
				
				
				
				break;
			case 'option':
				if($attr = $node->attr('label')) {
					$this->addTranslationKey($attr, 'forms', $node->lineNumber);
				}
				break;
		}
	}
	
	
	function extractValues($node) {
		switch ($node->nodeName) {
			case 'field':
				
				for ($i = 0; $i < count($this->translatableFormAttributes); $i++){
					if($value = $node->attr($this->translatableFormAttributes[$i])) {
						if (! $this->loocksLikeTranslationKey($value)) $this->processTranslationValueString($value, $node->lineNumber);
						
						// $this->addTranslationKey($attr, 'forms', $node->lineNumber);
					}
				}
				
				
				
				break;
			case 'option':
				if($value = $node->attr('label')) {
					if (! $this->loocksLikeTranslationKey($value)) $this->processTranslationValueString($value, $node->lineNumber);
					//$this->addTranslationKey($attr, 'forms', $node->lineNumber);
				}
				break;
		}
	}
	
	function getTranslationString($text) {
		$valObj = $this->resourceFileHandler->getKeyByValue($text);
		if ($valObj) {
			
			for ($i = 0; $i < count($valObj); $i++) { // check out if we find a form
				$obj = $valObj[$i];
				if ($obj['namespace'] == 'forms') return $obj['key'];
			}
			
			$this->io->error('There is no translation for ' . $text . ' in the required forms namespace.' );
		}
		
		return $text;
	}

}

?>