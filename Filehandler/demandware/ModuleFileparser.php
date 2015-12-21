<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../XMLFileparser.php');

class ModuleFileparser extends XMLFileparser {
	
	function __construct($file, $structureInit, $mode, $environment) {
		parent::__construct($file, $structureInit, $mode, $environment);
	}
	
	/**
	 *
	 * @param XMLNode $node		-	The xml node to parse
	 */
	function extractIncludes($node){
		
		if ($node->nodeName == 'ismodule') {
			$includePath = cleanupFilename($node->attr('template'), 'isml');
			$name = 'is' . trim($node->attr('name'));
			
			$this->addIncludeFile('modules', $name . '|' . $includePath);
		}
		
	}
	
	
	/**
	 *	@param String $sub			-	The subarray key of the includes array
	 *	@param String $includePath	-	The path of the included element. Filepath, Filekey or the like
	 */
	function addIncludeFile($sub, $param) {
		if ($param){
			
			$params = explode('|', $param);
			$name = $params[0];
			$template = $params[1];
			
			if (! array_key_exists($sub, $this->dataMap[self::$EXTRACT_MODES['INCLUDES']]))  $this->dataMap[self::$EXTRACT_MODES['INCLUDES']][$sub] = array();
			
			if (array_key_exists($name, $this->dataMap[self::$EXTRACT_MODES['INCLUDES']][$sub])){
				$this->io->error( 'The module definition ' . $name . ' already exists -> override!', 'ModuleFileparser');
				$this->dataMap[self::$EXTRACT_MODES['INCLUDES']][$sub][$name]['template'] = $template;
			} else {
				$this->dataMap[self::$EXTRACT_MODES['INCLUDES']][$sub][$name] = array( 'count' => 0, 'template' => $template);
			}
		}
	}

}

?>