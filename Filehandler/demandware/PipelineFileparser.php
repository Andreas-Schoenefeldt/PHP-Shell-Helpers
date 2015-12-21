<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../XMLFileparser.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'DemandwareFormHandler.php');

class PipelineFileparser extends XMLFileparser {
	
	function __construct($file, $structureInit, $mode, $environment) {
		parent::__construct($file, $structureInit, $mode, $environment);
	}
	
	/**
	 *
	 * @param XMLNode $node		-	The xml node to parse
	 */
	function extractIncludes($node){
		
		$formHandler = new DemandwareFormHandler($this);
		
		switch ($node->nodeName) {
			case 'template':
				$includePath = cleanupFilename($node->getAttribute('name'), 'isml');
				$this->addIncludeFile('templates', $includePath);
				break;
			case 'pipelet-node':
				// script include
				if ($child = $node->getChildWithAttributeValue('key', 'ScriptFile', 'config-property')) {
					
					$names = explode(':', $child->getAttribute('value'));
					
					$includePath = ((count($names) == 1)  ? cleanupFilename($names[0], 'ds') : cleanupFilename($names[1], 'ds'));
					$this->addIncludeFile('scripts', $includePath);
				}
				
				// template include
				if ($node->attr('pipelet-name') == 'SendMail' && $child = $node->getChildWithAttributeValue('key', 'MailTemplate', 'key-binding')) {
					$includePath =  cleanupFilename($child->getAttribute('alias'), 'isml');
					$this->addIncludeFile('templates', $includePath);
				}
				
				break;
		}
		
		// form include
		$formHandler->checkPipelineNodeForForms($node);
		
	}
	
	function extractKeys($node) {
		
		switch ($node->nodeName) {
			case 'key-binding':
				$val = $node->getAttribute('alias');
				if ($val != 'null') {
					$val = str_replace('&quot;', '"', $val);	
					$this->processTranslationKeyString($val, $node->lineNumber);
				}
				break;
		}
	}

}

?>