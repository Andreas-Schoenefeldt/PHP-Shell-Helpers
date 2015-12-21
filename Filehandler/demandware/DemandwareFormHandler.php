<?php
/**
 *	Static class which capsuls functioonality for handling form includes and the like
 *
 *
 *
 */
class DemandwareFormHandler {
	
	var $fileparser;
	
	var $pipelineFormPipelets = array(
		'UpdateObjectWithForm' => 'Form',
		'InvalidateFormElement' => 'FormElement',
		'ClearFormElement' => 'FormElement',
		'SetCustomerPassword' => 'Password',
		'GetCustomer' => 'Login'
	);
	
	function __construct($fileparser) {
		$this->fileparser = $fileparser;
	}
	
	function checkPipelineNodeForForms($node) {
		
		switch ($node->nodeName) {
			case 'pipelet-node':
				
				$relevantChilds = array();
				
				if ( array_key_exists($node->attr('pipelet-name'), $this->pipelineFormPipelets) && $child = $node->getChildWithAttributeValue('key', $this->pipelineFormPipelets[$node->attr('pipelet-name')], 'key-binding')) {
					$relevantChilds = array($child);
				} else {
					$relevantChilds = $node->find('key-binding');
				}
				
				for ($i = 0; $i < count($relevantChilds); $i++) {
					$this->addValidForm($relevantChilds[$i]->attr('alias'));
				}
				
			case 'decision-node':
				$this->addValidForm($node->attr('condition-key'));
				break;
		}
	}
	
	function checkISMLNodeForForms($node){
		
		$attributeID = false;
		
		switch ($node->nodeName) {
			case 'isaddressform':
				$attributeID = 'form';
				break;
			case 'form':
				$attributeID = 'id';
				break;
			case 'textarea':
			case 'input':
			case 'button':
				$attributeID = 'name';
			case 'isinputfield':
				$attributeID = 'formfield';
				break;
		}
		
		if ($attributeID) {
			$this->addValidForm($this->normaliseFormString($node->attr($attributeID)));
		}
	}
	
	function checkFormNodeForForms($node){
		switch ($node->nodeName) {
			case 'include':
					$nameParts = explode('.', $node->attr('name'));
					$this->fileparser->addIncludeFile('forms', $nameParts[0]);
				break;
		}
	}
	
	function normaliseFormString($string){
		return trim(str_replace(array('${', '}', 'pdict.'), '', $string));
	}
	
	function addValidForm($formIdentifier) {
		$formparts = explode('.', $formIdentifier);
		if ($formparts[0] == 'CurrentForms') {
			$this->fileparser->addIncludeFile('forms', $formparts[1]);
		}
	}



}

?>