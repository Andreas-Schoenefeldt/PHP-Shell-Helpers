<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../CmdIO.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'PseudoXML.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Debug/libDebug.php');


/* -----------------------------------------------------------------------
 * A function to open a file and parse the content into keymaps
 * ----------------------------------------------------------------------- */
class Fileparser {
	
	
	public static $EXTRACT_MODES = array(
		'INCLUDES' => 'includes',
		'KEYS' => 'keys',
		'VALUES' => 'values',
		'CSS' => 'css'
	);
	
	public static $SEARCH_MODES = array(
		'KEYS' => 'keys',
		'KEYS_WITH_VARIABLES' => 'keys_with_variables',
		'VALUES' => 'values',
		'SCRIPTS' => 'scripts'
	);
	
	// variables
	var $io = null;
	
	var $file = null; // the file which will be parsed
	var $fileNodes = null;
	
	var $fileChanged = false;
	
	// Main Data Maps
	var $resourceFileHandler = null; // ResourceFileHandler Class
	
	// config
	var $structureInit = array();
	var $parserRegexArrays = array();
	
	var $environment = array();
	var $mode = array();
	
	var $extractMode = array(); // defines the keys, includes or values, that are parsed. Multiple values possible
	var $dataMap = array();
	
	/* --------------------
	 * Constructor
	 *
	 * @param String $structureInit	- 
	 *
	 * array(
	 *		"parsingRegex" => array( , , ),			Array of Regular expressions for finding translateble parts in the file
	 *		"forcedSingleNodes" => array(, , , ),	Nodes which will be handled as single nodes, no matter a / was found or not
	 *		"textNodes" => array( , , ) 			Nodes which content will be inserted as text. No further parsing is going on
	 *		"ignoreAttributeNodes" => array( , , )  Nodes where never an attribute will be translated
	 * )
	 * 
	 * @param String $filename		- The absolute or relative Path to the file
	 */
	function __construct($file, $structureInit, $mode, $environment, $pseudoFileParser = 'PseudoText')  {
		$this->io = new CmdIO();
		$this->file = $file;
		
		$this->parserRegexArrays = $structureInit['parsingRegex'];
		$this->resourceFileHandler = $structureInit['resourceFileHandler'];
		$this->structureInit = $structureInit;
		
		$this->mode = $mode;
		$this->environment = $environment;
		
		// get the file
		if (file_exists($this->file->fileSystemLocation)){
			
			// parse the file
			$this->fileNodes = new $pseudoFileParser($this->file->fileSystemLocation, $this->environment, $this->structureInit);
			
			// debug: print the node doc, to see if everything is alright
			// $this->fileNodes->printDoc();
			
		} else {
			$this->io->fatal( "File $filename does not exist", get_class($this));
		}
		
	}
	
	// returns a extraction crawler array depending on the extract mode
	function getExtractionCrawlerObj(){
		$obj = array();
		for ($i = 0; $i < count($this->extractMode); $i++) {
			$obj[$this->extractMode[$i]] = array();
		}
		
		return $obj;
	}
	
	// set the extract mode
	function setExtractMode($modeArray) {
		$this->extractMode = $modeArray;
		$this->dataMap = $this->getExtractionCrawlerObj();
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
			
			return $obj->processChildren($node); // process the children ?
			
		} );
		
		return $this->getExtractionData();
		
	}
	
	function getExtractionData($subKey = false){
		if ($subKey) {
			if (array_key_exists($subKey, $this->dataMap)) {
				return $this->dataMap[$subKey];
			} else {
				return array();
			}
		}
		
		return $this->dataMap;
	}
	
	/**
	 *	Returns true by default. Override in Childclasses
	 */
	function processChildren($node) {
		return true;
	}
	
	/**
	 *	Function to retrieve id and class information from a node
	 */
	function extractCSS($node) {
		throw new Exception('not implemented');
	}
	
	function extractKeys($node) {
		throw new Exception('not implemented');
	}
	
	/**
	 *	Function which will add the arguments as subsequential array
	 *	arg[0] > arg[1] > arg [2]
	 *
	 */
	function addData(){
		
		$tree = $this->dataMap;
		
		$access = '';
		$put = '';
		
		for($i=0;$i<func_num_args();$i++) {
            
			if ($i < func_num_args() - 1) {
				
				$access .= "['" . str_replace("'", "\\'", func_get_arg($i)) . "']";
				
				if (! array_key_exists(func_get_arg($i), $tree)) {
					eval("\$this->dataMap" . $access . " = array();");
					$tree[func_get_arg($i)] = array();
				}
				
				$tree = $tree[func_get_arg($i)];
				
			} else {
				
				$put = $access . "['" . str_replace("'", "\\'", func_get_arg($i)) . "']";
			}
        }
		
		
		// add the value dynamicaly
		$code = "
if (array_key_exists('" . str_replace("'", "\\'", func_get_arg(func_num_args() - 1)) . "', \$this->dataMap" . $access .")) {
	\$this->dataMap" . $put . "++;
} else {
	\$this->dataMap" . $put . " = 1;
}
		";
		
		eval($code);
	}
	
	function processTranslationKeyString($string, $linenumber) {
		
		if ($string){
		
			foreach($this->parserRegexArrays[self::$SEARCH_MODES['KEYS']] as $regex) {
				
				preg_match_all($regex, $string, $matches, PREG_PATTERN_ORDER);
						
				for ($i = 0; $i < count($matches['key']); $i++){
					
					$key = $matches['key'][$i];
					$assumed_namespace = (array_key_exists('namespace', $matches)) ? $matches['namespace'][$i] : false;
					
					$this->addTranslationKey($key, $assumed_namespace, $linenumber);
					
				}
				
			}
			
			// process keys with variables
			foreach($this->parserRegexArrays[self::$SEARCH_MODES['KEYS_WITH_VARIABLES']] as $regex) {
				preg_match_all($regex, $string, $matches, PREG_PATTERN_ORDER);
						
				for ($i = 0; $i < count($matches['key']); $i++){
					
					$key = $matches['key'][$i];
					$assumed_namespace = (array_key_exists('namespace', $matches)) ? $matches['namespace'][$i] : false;
					
					$this->addTranslationKey($key, $assumed_namespace, $linenumber, self::$SEARCH_MODES['KEYS_WITH_VARIABLES']);
				}
			}
		}
	}
	
	function addTranslationKey($key, $assumed_namespace, $linenumber, $searchMode = false) {
		
		$extractMode = self::$EXTRACT_MODES['KEYS'];
		$searchMode = (!$searchMode) ?  self::$SEARCH_MODES['KEYS'] : $searchMode;
		if ($searchMode == self::$SEARCH_MODES['KEYS_WITH_VARIABLES'] || $this->resourceFileHandler->resourceKeyExists($key, $assumed_namespace)) { // is it a known key?
			if (! $assumed_namespace) $assumed_namespace = $this->resourceFileHandler->getBestResourceKeyNamespace($key);
			
			if (! array_key_exists($searchMode, $this->dataMap[$extractMode])) $this->dataMap[$extractMode][$searchMode] = array();
			
			// check if initial
			if (array_key_exists($key, $this->dataMap[$extractMode][$searchMode]) && array_key_exists($assumed_namespace, $this->dataMap[$extractMode][$searchMode][$key])) {
				$this->dataMap[$extractMode][$searchMode][$key][$assumed_namespace]['count']++;
				$this->dataMap[$extractMode][$searchMode][$key][$assumed_namespace]['files'][$this->file->fileSystemLocation]['lines'][] = $linenumber;
			} else {
				
				if (! array_key_exists($key, $this->dataMap[$extractMode][$searchMode]))	$this->dataMap[$extractMode][$searchMode][$key] = array();
				
				$_lines = array();
				$_lines[$this->file->fileSystemLocation] = array('lines' => array(0 => $linenumber));
				$this->dataMap[$extractMode][$searchMode][$key][$assumed_namespace] = array('files' => $_lines, 'count' => 1);
			}	
		} else {
			if ($this->loocksLikeTranslationKey($key)) {
				$this->io->error($key, ' line ' .$linenumber .' - Missing Resource Key' );
				
				$_lines = array();
				$_lines[$this->file->fileSystemLocation] = array('lines' => array(0 => $linenumber));
				$stats = array('files' => $_lines, 'count' => 1);
				$this->resourceFileHandler->registerKey($key, $assumed_namespace, $stats);
			} else {
				$this->io->out('> [IGNORE] String in line ' . $linenumber . ' dosn\'t look like a key: ' . $key);
			}
		}
	}
	
	function loocksLikeTranslationKey($key) {
		return substr_count($key, '.') > 0 && substr_count($key, ' ') == 0;
	}
	
	function extractIncludes($node){
		throw new Exception('not implemented');
	}
	
	function processTranslatableText($value, $linenumber, $testOnly = true) {
		$found = false;
		
		// looping throug all the regular expressions
		for ($expressionindex = 0 ; $expressionindex < count($this->parserRegexArrays[self::$SEARCH_MODES['VALUES']]); $expressionindex++) {
			$regex = $this->parserRegexArrays[self::$SEARCH_MODES['VALUES']][$expressionindex];
		
			// first we figure out wheather a parsing make sense or not
			
			preg_match_all($regex, $value, $matches, PREG_PATTERN_ORDER);
			
			$vars = array();
			$processedValue = $value;
			$translate = true;
			
			$filterRegex = "/[a-z]{2,}/i";
			$searchArray = array('&nbsp;', '&bull;', '&quot;', '&gt;', '&lt;', '&laquo;', '*', '(', ')', '[', ']', '<', '>', '-->', '<!--', '-', ':', ',', '.', '!', '?'); // will be part of the resource string, because some languages like to add different amounts of whitespaces
			$replaceArray = '';
			
			$beforeVal = '';
			$afterVal = '';
			
			switch (count($matches[0])){
				case 1:
					if ($matches['after'][0] == '' && $matches['before'][0] == '') {
						// pure variable. do nothing
						$translate = false;	
						break;
					}
					// else: go through the default
				default:
					// > 1
					$processedValue = ''; // reset
					$vars = $matches['var'];
					
					$hasText = false;
					for($i = 0; $i < count($vars); $i++){
						if (! $hasText) {
							// check, that at least some words are there
							$after = str_replace($searchArray, $replaceArray, $matches['after'][$i]);
							$before = str_replace($searchArray, $replaceArray, $matches['before'][$i]);
							
							if (preg_match($filterRegex ,$before) || preg_match($filterRegex ,$after)) {
								$hasText = true;
							}
						}
						$processedValue .= $matches['before'][$i].'{'.($i).'}'.$matches['after'][$i];
					}
					
					if (! $hasText){
						$processedValue = $value;
						$translate = false;
					}
					
					break;
				case 0:
					// normal String
					$rep = trim(str_replace($searchArray, $replaceArray, $value));
					
					if (! preg_match($filterRegex ,$rep)) {
						// was a technical string
						$translate = false;
					}
					
					break;
				
			}
			
			if ($translate && $this->isJSONString($processedValue)) {
				d('> [IGNORE] ' . $processedValue);
				$translate = false;
			}
			
			
			if (! $testOnly) {
				
				$_lines = array();
				$_lines[] = $linenumber;
				
				if (! array_key_exists($value, $this->dataMap[self::$EXTRACT_MODES['VALUES']])) {
					$this->dataMap[self::$EXTRACT_MODES['VALUES']][$value] = array(
						"value" => $processedValue, // the translation value
						'before' => $beforeVal, // String before the translation value
						'after' => $afterVal, // String after the translation value
						'count' => 1,
						'translate' => $translate,
						'files' => array()
					);
				} else {
					$this->dataMap[self::$EXTRACT_MODES['VALUES']][$value]['count']++;
				}
				
				$this->dataMap[self::$EXTRACT_MODES['VALUES']][$value]['files'][$this->file->fileSystemLocation] = array(
					"variables" => $vars,
					"lines" => $_lines,
				);
				
				if ($translate && $this->resourceFileHandler->registerTranslationValue($value, $this->dataMap[self::$EXTRACT_MODES['VALUES']][$value])) {
					$this->io->out('> [FOUND VALUE] ' . $value . ' will be translated in line ' . $linenumber);
					$this->fileChanged = true;
				}
			}
			
			$found = $found || $translate;
			$value = false; // reset
		}
		
		return $found;
	}
	
	function isJSONString($string) {
		$collonExist = substr_count($string, ':') > 0;
		$quoteExist = substr_count($string, '"') >= 2;
		$oBraquetsExist = substr_count($string, '{') > 0;
		$cBraquetsExist = substr_count($string, '}') > 0;
		
		$braquetsExist = $oBraquetsExist || $cBraquetsExist;
		
		return  ($collonExist &&  $quoteExist && $braquetsExist);
	}
	
	// returns if a translation was found or not
	function processTranslationValueString($value, $linenumber){
		
		if (array_key_exists($value, $this->dataMap[self::$EXTRACT_MODES['VALUES']]) && array_key_exists($this->file->fileSystemLocation, $this->dataMap[self::$EXTRACT_MODES['VALUES']][$value])) {
			$this->dataMap[self::$EXTRACT_MODES['VALUES']][$value]['count']++;
			$this->dataMap[self::$EXTRACT_MODES['VALUES']][$value]['files'][$this->file->fileSystemLocation]['lines'][] = $linenumber;
			$found = true;
		} else {
			$found = $this->processTranslatableText($value, $linenumber, false);
		}
		
		return $found;
	}
	
	function extractValues($node) {
		// do nothing on purpose
	}
	
	
	function isValidFilename($relPath) {
		return $relPath && strpos('${', $relPath) === false;
	}
	
	function getScriptIncludeFile($string){
		
		$keys = array();
		if ($string){
		
			foreach($this->parserRegexArrays[self::$SEARCH_MODES['SCRIPTS']] as $regex) {
				
				preg_match_all($regex, $string, $matches, PREG_PATTERN_ORDER);
				
				for ($i = 0; $i < count($matches['path']); $i++){
					$keys[] = $matches['path'][$i];
				}
				
			}
		}
		
		return $keys;
	}
	
	/**
	 *	@param String $sub			-	The subarray key of the includes array
	 *	@param String $includePath	-	The path of the included element. Filepath, Filekey or the like
	 */
	function addIncludeFile($sub, $includePath) {
		if ($includePath){
			
			if (! array_key_exists($sub, $this->dataMap[self::$EXTRACT_MODES['INCLUDES']]))  $this->dataMap[self::$EXTRACT_MODES['INCLUDES']][$sub] = array();
			
			if (array_key_exists($includePath, $this->dataMap[self::$EXTRACT_MODES['INCLUDES']][$sub])){
				$this->dataMap[self::$EXTRACT_MODES['INCLUDES']][$sub][$includePath]++;
			} else {
				$this->dataMap[self::$EXTRACT_MODES['INCLUDES']][$sub][$includePath] = 1;
			}
		}
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	/** function to get a translation key from a specific properties file
	 *
	 * @return String - Returns a object with status and key of the array. array('status' =>, 'value' =>  )
	 *					status meaning:
	 *						-1	delete key in code file
	 *						 0	key not found
	 *						 1	key found
	 *					
	 * 					
	 */
	function getTranslationKey($namespace, $key, $locale = 'default'){
		if (!array_key_exists($namespace, $this->localisationMap)) {
			
			// the key is not a direct file link, first we look into the list, if a link already exists
			if (array_key_exists($namespace, $this->namespaceMap)) {
				// Karmapa Tchenno! change the namespace
				$namespace = $this->namespaceMap[$namespace];
			} else {
				
				// not jet found, now we have to parse all the files. Karmapa tchenno!
				$interaction = array();
				foreach ($this->localisationMap as $ns => $ar) {
					$interaction[] = $ns;
					foreach ($ar as $loc => $values) {
						$this->parseResourceFile($ns, $loc);
						
						//print_r($this->namespaceMap);
						
						// check, if the key was found
						$stats = $this->grabKey($ns, $key, $loc);
						//print_r($stats);
						if ($stats['status'] == 1) {
							return $stats;
						}
						
					}
				}
				
				// the key was not found in all the files - check the basic Namespace
				$b_ns = explode('.', $key);
				$result = array('status' => 0, 'value' => '', 'namespace' => '');
				if (array_key_exists($b_ns[0], $this->namespaceMap)) {
					$result['namespace'] = $this->namespaceMap[$b_ns[0]];
					return $result;
				}
				
				// the key is definetly not defined ask for file creation
				$interaction[] = "? create $namespace.properties";
				$interaction[] = "? do nothing";
				print_r($interaction);
				$answer = $this->io->readStdInn('The File '.$namespace.'.properties does not exist. Add it to a specific properties file or create a new one.');
				$answer = $interaction[intval($answer)];
				
				// add the namespace
				$this->io->cmd_print("> Adding $namespace to $answer.");
				$this->namespaceMap[$namespace] = $answer;
				
				$funct =  substr( $answer, 0,1) == '?';
				
				if ($funct) {
					$this->io->cmd_error("[not implemented]", get_class($this));
					$result['status'] = -1;
					return $result;
				} else {
					$result['namespace'] = $answer;
					return $result;
				}
				
				
					
			}
			
		}
		
		return $this->grabKey($namespace, $key, $locale);
	}
	
	/**
	 * Funktion to get The key from an known file
	 *
	 */
	function grabKey($namespace, $key, $locale = 'default') {
		
		$result = array('status' => 0, 'value' => '', 'namespace' => $namespace);
		
		if (array_key_exists($key,  $this->localisationMap[$namespace][$locale]['keys'])) {
			$result['status'] = 1;
			$result['value'] = $this->localisationMap[$namespace][$locale]['keys'][$key];
		} else if ($this->localisationMap[$namespace][$locale]['imported'] === false){
			
			// Parse the file, because it has not been done before. Karmapa Tchenno!
			$this->parseResourceFile($namespace, $locale);
			
			// try again
			if (array_key_exists($key,  $this->localisationMap[$namespace][$locale]['keys'])) {
				$result['status'] = 1;
				$result['value'] = $this->localisationMap[$namespace][$locale]['keys'][$key];
			} 
		}
		
		return $result;
	}
	
	
	function print_results(){
		
		$this->io->cmd_print("> Keys found:\n");
		foreach ($this->fileMap as $key => $value) {
			$this->io->cmd_print("   {$value['count']}x \t $key ");
		}
		
		$this->io->cmd_print('');
		//$this->io->cmd_print("> Localised Files found:\n");
		//print_r($this->localisationMap);
		
	}
	
	
	
	
	
	
	
	// Printing Functions
	

	function printChangedFile($onlyToScreen = false){
		// writing the changed file
		
		// $this->printDoc();
		// d($this->getFileString($this->fileNodes->doc));
		
		if ($this->fileChanged) {
			$this->io->cmd_print(">". (($onlyToScreen) ? '': ' writing') .' ' . $this->fileNodes->filename, true, 1);
			if (! $onlyToScreen) $this->io->setWriteMode('screen & file', $this->file->fileSystemLocation);
			$this->io->cmd_print($this->getFileString($this->fileNodes->doc), false);
			
			// reset the write mode
			if (! $onlyToScreen) {
				$this->io->setWriteMode('screen');
				$this->fileChanged = false;
			}
			return true;
		}
		
		return false;
	}
	
	function getFileString($rootNode) {
		return $this->toHTML($rootNode);
	}
	
	// very simple and basic print of the nodes. Good to check the structure
	function printDoc(){
		$this->fileNodes->printDoc();
	}

}

?>