<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../XMLFileparser.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'DemandwareFormHandler.php');

class ISMLFileparser extends XMLFileparser {
	
	var $modules;
	
	function __construct($file, $structureInit, $mode, $environment, $modules = array()) {
		$this->modules = $modules;
		parent::__construct($file, $structureInit, $mode, $environment);
	}
	
	
	/**
	 * Main Function of the Fileparser
	 *
	 */
	function extractData(){
		$obj = $this;
		
		$this->fileNodes->crawlAllNodes(function($node) use ($obj) {
			
			if (in_array($obj::$EXTRACT_MODES['INCLUDES'], $obj->extractMode)) {
				$obj->extractIncludes($node);
			}
			
			if (in_array($obj::$EXTRACT_MODES['KEYS'], $obj->extractMode)) {
				$obj->extractKeys($node);
			}
			
			if (in_array($obj::$EXTRACT_MODES['VALUES'], $obj->extractMode)) {
				$obj->extractValues($node);
			}
			
			if (in_array($obj::$EXTRACT_MODES['CSS'], $obj->extractMode)) {
				$obj->extractCSS($node);
			}
			
			return $obj->processChildren($node); // process the children ?
			
		} );
		
		return $this->getExtractionData();
		
	}
	
	
	function processChildren($node){
		return ! in_array($node->nodeName, $this->structureInit['textNodes']) || $node->translatable;
	}
	
	/**
	 *
	 * @param XMLNode $node		-	The xml node to parse
	 */
	function extractIncludes($node){
		
		$formHandler = new DemandwareFormHandler($this);
		
		switch ($node->nodeName) {
			case 'isdecorate':
			case 'isinclude':
				if ($path = trim($node->getAttribute('template'))) {
					if ($this->isValidFilename($path)) {
						$includePath = cleanupFilename($node->getAttribute('template'), 'isml');
						$this->addIncludeFile('templates', $includePath);
					}
				}
				break;
			case 'isscript':
				for ($i = 0; $i < $node->getChildCount(); $i++){
					$child = $node->getChild($i);
					if ($child->text) {
						
						$names = $this->getScriptIncludeFile($child->text);
						if (count($names)){
							for ($k = 0; $k < count($names); $k++){
								$name = explode(':', $names[$k]);
								$includePath =  ((count($name) == 1)  ? cleanupFilename($name[0], 'ds') : cleanupFilename($name[1], 'ds'));
								$this->addIncludeFile('scripts', $includePath);
							}
						}
					}
				}
				break;
			case 'isset':
				if (strtolower($node->getAttribute('name')) == 'decoratortemplate' && $this->isValidFilename($node->getAttribute('value'))) {
					$includePath = cleanupFilename($node->getAttribute('value') , 'isml');
					$this->addIncludeFile('templates', $includePath);
				}
				break;
			case 'link':
				if ($node->attr('type') == "text/css") {
					preg_match_all('/URLUtils\.staticURL[ ]*?\([ ]*?["\'][ ]*?(?P<path>.*?)[ ]*?["\']/', $node->attr('href'), $matches, PREG_PATTERN_ORDER); //       
					for ($i = 0; $i < count($matches['path']); $i++) {
						$this->addIncludeFile('static', cleanupFilename($matches['path'][$i],'css'));
					}
				}
				break;
		}
		
		// modules check
		if (array_key_exists($node->nodeName, $this->modules)){
			$this->addIncludeFile('templates', $this->modules[$node->nodeName]['template']);
			$this->addIncludeFile('modules', $node->nodeName);
			$this->modules[$node->nodeName]['count']++;
			
		}
		
		// form include
		$formHandler->checkISMLNodeForForms($node);
	}
	
	function extractKeys($node) {
		
		switch ($node->nodeType) {
			case 'text':
				$this->processTranslationKeyString($node->text, $node->lineNumber);
				break;
			case 'tag':
				foreach ($node->attributes as $key => $val) {
					// node->attributes->? Filter out values of normal inputs, take only the buttons
					$this->processTranslationKeyString($node->attr($key),  $node->lineNumber);
				}
				
				if (in_array($node->nodeName, array('script', 'isscript'))) {
					for ($i = 0; $i < $node->getChildCount(); $i++) {
						$this->extractKeys($node->getChild($i));
					}
				}
				
				break;
		}
	}
	
	/**
	 *	Function to retrieve id and class information from a node
	 */
	function extractCSS($node) {
		if ($node->attr('id')) 		$this->testDynamicCssIdent('ids', $node->attr('id'), $node);
		if ($node->attr('class')) 	$this->testDynamicCssIdent('classes', $node->attr('class'), $node);
	}
	
	function testDynamicCssIdent($sub, $value, $node) {
		
		$original = $value;
		$regExWildCart = '.+?';
		
		// first we try to evaluate the ${} statements in order to figure out, if we have a direct print of a functional class
		preg_match_all('/\${(?P<func>.*?)}/i', $value, $matches, PREG_PATTERN_ORDER);
		
		for ($i = 0; $i < count($matches[0]); $i++) {
			
			$match = $matches['func'][$i];
			preg_match_all('/.*?\?[ ]*?["\'](?P<str1>.*?)["\'][ ]*?:[ ]*?["\'](?P<str2>.*?)["\']/i', $match, $hits, PREG_PATTERN_ORDER); // do we have a asignement?
			
			$search = '${' . $match . '}';
			if (count($hits[0]) > 0) {
				
				$str1 = $hits['str1'][0];
				$str2 = $hits['str2'][0];
				
				if ($str1 && $str2) {
					
					$c1 = str_replace($search, $str1, $value); // doublicate the classstring
					$c2 = str_replace($search, $str2, $value);
					
					$value = $c1 . ' ' . $c2;
				} else {
					if ($str1 !== '') {
						$replace = $str1;
					} else {
						$replace = $str2;
					}
					
					$value = str_replace($search, $replace, $value);
				}
			} else {
				$value = str_replace($search, $regExWildCart, $value);
			}
		}
		
		if (strpos($value, '<') !== false){
			$value = preg_replace('/<isprint.*?value=["\'](.*?)["\'].*?>/', '\1', $value); // replacing the value of the print with the whole print
			$value = preg_replace('/<.*?>/i', '', $value); // cleaning isif tags from the String
		}
		
		$singles = explode(' ', $value);
		// add single classes
		for ($i = 0; $i < count($singles); $i++) {
			$single =$singles[$i];
			if (strpos($single, $regExWildCart) !== false) {
				if (strlen($single) > 3) {
					// only add the dynamic class, if it is more then a general wildcard
					
					$parts = explode($regExWildCart, $single);
					$result = '/^'; // matching the beginn of the class
					for ($k = 0; $k < count($parts); $k++){
						if ($k > 0) $result .= $regExWildCart;
						$result .= str_replace( array('[', '\\', '^', '$', '.', '|', '?', '*', '+', '(', ')'), array('\\[', '\\\\', '\\^', '\\$', '\\.', '\\|', '\\?', '\\*', '\\+', '\\(', '\\)'), $parts[$k]);
					}
					$result .= '$/'; // matching the end of the class
					$this->addData($this::$EXTRACT_MODES['CSS'], $sub.'_dynamic' , $result);	
				} else {
					$this->io->warn("Absolute general value for $sub possible: '" . $original . '\' This will be ignored. If it is referenced it in the project css files, please add the possible values manualy to the include_css_'.$sub.' propertie in you .translation.config file.', 'line ' . $node->lineNumber);
				}
			} else if ($single) { // only add a class, if it conzains some chars
				$this->addData($this::$EXTRACT_MODES['CSS'], $sub , $single);	
			}
		}
		
		
	}
	
	function addIncludeFile($sub, $includePath) {
		if ($includePath){
			
			switch ($sub) {
				default:
					parent::addIncludeFile($sub, $includePath);
					break;
				case 'modules':
					if (! array_key_exists($sub, $this->dataMap[self::$EXTRACT_MODES['INCLUDES']]))  $this->dataMap[self::$EXTRACT_MODES['INCLUDES']][$sub] = array();
					
					if (array_key_exists($includePath, $this->dataMap[self::$EXTRACT_MODES['INCLUDES']][$sub])){
						$this->dataMap[self::$EXTRACT_MODES['INCLUDES']][$sub][$includePath]['count']++; 
					} else {
						$this->dataMap[self::$EXTRACT_MODES['INCLUDES']][$sub][$includePath] = array( 'count' => 1, 'template' => $this->modules[$includePath]['template']);
					}
					
					break;
			}	
		}	
	}
	
	/**
	 *	Function which will return the text of a text node. If we have a combined string like: text <b>more text</b>
	 *	The function will try to change the nodestructure in a way, that Resource.msgf can be used
	 *	The function is based on $this->structureInit('inlineNodes')
	 *
	 *	example: <b>Questions?</b> Please visit the <a href="${URLUtils.url('CustomerService-Show')}" class="red">help section</a> for comprehensive order information or <a href="${URLUtils.url('CustomerService-ContactUs')}" class="red">contact us</a> 24 hours a day.
	 *	result: ${'<b>' + Resource.msg('?', '?', null) + '<b>'} Please visit the ${<a href="' + URLUtils.url('CustomerService-Show') + '" class="red">' + Resource.msg('?', '?', null) + '</a>'} for comprehensive order information or ${'...' + '...'} 24 hours a day.
	 *	
	 *	@param XMLNode $node		-> the child node
	 *	
	 */
	function tryNodeValueTransformation($node) {
		
		if ($node->text || in_array($node->nodeName, $this->structureInit['inlineNodes'])) {
			
			$relevantChilds = $node->getInlineSiblingsFromThisNode($this->structureInit['inlineNodes']);
			$count = count($relevantChilds);
			if ( $count > 1) {
				require_once(str_replace('//','/',dirname(__FILE__).'/') .'../XMLNode.php');
				
				$combinedValue = '';
				$useTextNode = true;
				$valid = false;
				
				for ($i = 0; $i < $count; $i++) {
					if ($i > 0) $combinedValue .= ' ';
					$combinedValue .= $this->getInlineTextExpression($relevantChilds[$i]);
					
					$valid = $valid || $this->processTranslatableText($relevantChilds[$i]->text, $relevantChilds[$i]->lineNumber);
					$useTextNode = $useTextNode && ($relevantChilds[$i]->text || $relevantChilds[$i]->nodeName == 'isprint');
					
				}
				
				if ($valid) {
					$val = $this->getTranslationStringForValue($combinedValue, $node->lineNumber);
					
					if ($useTextNode) { // use a single textnode
						$replaceNode = new XMLNode('', 'text');
						$replaceNode->text = $val;
					} else { // use a isreplace to escape the tags
						$replaceNode = new XMLNode('isprint', "tag", $node->getParent());
						$replaceNode->addAttributes(array('encoding' => 'off', 'value' => $val));
					}
					$replaceNode->singleNode = true;
					$node->getParent()->replaceNChildrenWithNode($relevantChilds[0], count($relevantChilds), $replaceNode);
					
					return true;
				}
			}	
		}
		
		if ($node->text) {
			return $node->text;
		}
		
		return false;
	}
	
	function getTranslationStringForValue($value, $lineNumber, $inText = true) {
		$this->processTranslationValueString($value, $lineNumber);
		
		if ($this->dataMap[self::$EXTRACT_MODES['VALUES']][$value]['translate']) {
			$this->resourceFileHandler->registerTranslationValue($value, $this->dataMap[self::$EXTRACT_MODES['VALUES']][$value]);
			$result = $this->resourceFileHandler->getTranslationString($value, $this->file->fileSystemLocation, $inText);
			// $this->dataMap[self::$EXTRACT_MODES['VALUES']][$value]['value']
		} else {
			$result = "'" . $value . "'";
		}
		
		return $result;
	}
	
	function getInlineTextExpression($node, $firstLevel = true){
		if ($node->text) {
			if ($firstLevel) {
				$result = $node->text;
			} else {
				$result = $this->getTranslationStringForValue($node->text, $node->lineNumber, false);
				
			}
		} else {
			$result = '';
			if ($firstLevel) $result = '${';
			
			$opening = ($node->nodeName == 'isprint') ? str_replace(array('${', '}'), '', $node->attr('value')) : "'" . str_replace(array('${', '}'), array("' + ", " + '"), $this->getHTMLOpeningTag($node)) . "' + ";
			$result .= $opening;
			
			for ($i = 0; $i < $node->getChildCount(); $i++) {
				$result .= $this->getInlineTextExpression($node->getChild($i), false);
			}
			
			$closing = ($node->nodeName == 'isprint') ? '' : " + '" . $this->getHTMLClosingTag($node, false) . "'";
			$result .= $closing;
			
			if ($firstLevel) {
				$result .= '}';
				$result = str_replace("' + '", '', $result); // removing things like '<a>' + '<b>' to '<a><b>'
			}
		}
		
		return $result;
	}
	
	function extractValues($node) {
		
		$value = $this->tryNodeValueTransformation($node);
		$count = ($value) ? 1 : count($this->structureInit['translatebleAttributesList']);
		
		if ($value !== true) {
			switch($node->nodeName) { // value is only true, if we had a sucessful key replacement
				default:
					for ($loop = 0; $loop < $count; $loop++){
									
						if ($value === false) {
							if ( $node->hasAttribute($this->structureInit['translatebleAttributesList'][$loop]) && ( in_array( $node->getAttribute('type'), $this->structureInit['translatebleInputTypeList']) || $node->nodeName != 'input' )) {
								$value = $node->getAttribute($this->structureInit['translatebleAttributesList'][$loop]);
							}
						}			
											
						if ($value) {
							$this->processTranslationValueString($value, $node->lineNumber);
						}
					}
					break;
				case 'isset':
					if (in_array($node->attr('name'), $this->structureInit['translatebleAttributesList'])) {
						$this->processTranslationValueString($node->attr('value'), $node->lineNumber);
					}
					break;
			}	
		}
	}

}

?>