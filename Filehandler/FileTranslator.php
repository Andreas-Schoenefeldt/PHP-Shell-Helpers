<?php
	
require_once(str_replace('//','/',dirname(__FILE__).'/') .'../CmdIO.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'Folder.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'File.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'PseudoXML.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'staticFunctions.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'Fileparser.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Debug/libDebug.php');

class FileTranslator {
	
	var $io;
	
	var $file;
	var $root;
	var $structureInit;
	var $mode;
	var $extractmode = array(); // the default extract mode 
	var $environment;
	
	var $resourceFileHandler;
	
	var $relevantFileEndings = array(); // endings of files, that are taken into account
	var $structureOfRelevantFiles; // the relevant structure, after all the irelevant files are cleaned out
	
	var $config = array(); // simple config file. ignore_files: cvs-string (converted into array) \n cartridgepath: cvs-string (converted into array)
	
	var $dataMap = array(); // holds the data connections between the differen tproject files
	/**
	 *	Array(
			[includes] => Array(
			  [pipelines] => Array( )
			  [templates] => Array(...)
			  [scripts]   => Array(...)
			  [forms]     => Array(...)
			  [modules]   => Array( )
			  [static]  => Array(...)
			)
			[keys]     => Array(
			  [keys]                => Array(...)
			  [keys_with_variables] => Array(...)
			)
			[values]   => Array(...)
	 *
	 *
	 *
	 */
	
	var $configFileName = null;
	
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
	function __construct($fileRoot, $filename, $structureInit, $mode, $environment)  {
		$this->io = new CmdIO();
		
		$this->file = (is_file($filename)) ? new File($filename) : new Folder($filename);
		$this->root = $fileRoot;
		$this->structureInit = $structureInit;
		
		$this->mode = $mode;
		
		if (! count($this->extractmode)){
			switch($this->mode) {
				case 'find':
					$this->extractmode = array(Fileparser::$EXTRACT_MODES['VALUES']);
					break;
				case 'keys':
				case 'new_only':
					$this->extractmode = array(Fileparser::$EXTRACT_MODES['KEYS']);
					break;
				case 'project_optimize':
					$this->extractmode = array(Fileparser::$EXTRACT_MODES['INCLUDES'], Fileparser::$EXTRACT_MODES['KEYS'], Fileparser::$EXTRACT_MODES['VALUES'], Fileparser::$EXTRACT_MODES['CSS']);
					break;
			}
		}
		
		$this->environment = $environment;
		
		$this->resourceFileHandler = $structureInit['resourceFileHandler'];
		
		// read the projects configuration file, if existend
		$this->configFileName = $fileRoot . '/.translation.config';
		if (file_exists($this->configFileName)){
			$this->io->out("\n> found a config file " . $this->configFileName);
			$this->readConfig($this->configFileName);
			$this->io->out("> Parsed values: ");
			d($this->config);
		} else {
			$this->io->error("No Config file found in " . $this->configFileName, "FileTranslator");
		}
		
		if (array_key_exists('prefered_propertie_locations', $this->config)) {
			$this->resourceFileHandler->setPreferedPropertieLocations($this->config['prefered_propertie_locations']);
		}
		
		$this->structureOfRelevantFiles = new Folder('ROOT');
		
		$this->translate();
	}
	
	/**
	 *	Default translation function
	 */
	function translate(){
		
		switch ($this->mode) {
			default:
				if ($this->file->isFile) {
					$this->io->cmd_print('Start to parse the file '. $this->file->name , true, 1); 
					$parser = $this->processFile($this->file, $this->extractmode, strtoupper($this->file->extension));
					
					if(in_array(Fileparser::$EXTRACT_MODES['VALUES'], $this->extractmode)) {
						$this->resourceFileHandler->registerTranslationValueMap($this->dataMap[Fileparser::$EXTRACT_MODES['VALUES']]);
					}
					
					$this->io->out("\n" . '> Changed Files:');
					$this->printChangedFiles($parser);
				}
			break;
			case 'project_optimize':
				$this->optimiseProject();
				break;
		}
	}
	
	function printChangedFiles($parser){
		$changed = $this->resourceFileHandler->printChangedResourceFiles(true);
		$changed = $changed || $parser->printChangedFile(true);
		
		if ($changed && strtolower($this->io->read('> Do you whish to print this changed files? (y/N)')) == 'y'){
			// the real file write
			$this->resourceFileHandler->printChangedResourceFiles();
			$parser->printChangedFile();
			
		}
		
		$this->resourceFileHandler->currentDefaultFile = null; // reset of the default file
		
		// printing of the possible ignored files
		$this->updateProjectConfig();
	}
	
	/**
	 *	@param File 	$file			-	A File object of the file to parse
	 *	@param Array 	$extractmode	-	Array of extraction constants for the fileparse
	 *	@param String	$processor		-	The String Classname of the processor
	 */
	function processFile($file, $extractmode, $processor){
		
		// load  and createthe processor
		$parser = $this->createFileProcessor($file, $this->loadFileProcessor($processor));	
		$parser->setExtractMode($extractmode);
		$this->addData($parser->extractData());
		
		return $parser;
	}
	
	function createFileProcessor($file, $processorName) {
		return new $processorName($file, $this->structureInit, $this->mode, $this->environment);
	}
	
	
	function optimiseProject(){
		throw new Exception('optimiseProject Not Implemented');
	}
	
	
	
	
	// adds a project config file
	function readConfig($configFile) {
		$fp = fopen($configFile, 'r');
		
		$ignoredValues = array();
		$mode = 'list';
		
		while ($line = fgets($fp, 2048)) {
			
			$parts = ($mode != 'IGNORED_VALUES') ? explode('//', $line, 2) : array($line); // remove simple comments if we are above the ignored comments mode
			$parts = explode(':', $parts[0], 2);
			$name = trim($parts[0]);
			
			switch ($name) {
				case 'IGNORED_VALUES':
					$mode = 'IGNORED_VALUES';
					break;
			}
			
			if (count($parts) > 1 && trim($parts[1])) {
				switch ($mode){
					default:
						$values = explode(',', $parts[1]);
						for ($i = 0; $i < count($values); $i++) {
							$values[$i] = trim($values[$i]);
						}
						
						$this->config[$name] = $values;
					
						break;
					case 'IGNORED_VALUES':
						if (! array_key_exists($name, $ignoredValues)) $ignoredValues[$name] = array();
						$ignoredValues[$name][] = trim($parts[1]);
						break;
				}
			}
			
		}
		fclose($fp);
		$this->resourceFileHandler->setIgnoreMap($ignoredValues);
	}
	
	/**
	 *	Function which will update the project config file
	 *
	 */
	function updateProjectConfig(){
		
		if ($this->resourceFileHandler->ignoreWriteMapChanged) {
			
			$fp = fopen($this->configFileName, 'r');
			$lines = array();
			
			$computeString = "// -- no manual entered data below this line --";
			
			while ($line = fgets($fp, 2048)) {
				if (strpos($line, $computeString) !== false){
					break;
				} else {
					$lines[] = $line;
				}
			}
			fclose($fp);
			
			$fp = fopen($this->configFileName, 'w');
			for ($i = 0; $i < count($lines); $i++) {
				fwrite($fp, $lines[$i]);
			}
			
			fwrite($fp, $computeString . "\n\n");
			fwrite($fp, "IGNORED_VALUES:\n");
			foreach ($this->resourceFileHandler->ignoreWriteMap as $filename => $values) {
				for ($i = 0; $i < count($values); $i++){
					fwrite($fp, $filename.':'.$values[$i]."\n");
				}
			}
			
			fclose($fp);
			
			$this->resourceFileHandler->ignoreWriteMapChanged = false;
		}
	}
	
	
	// returns the config or an empty aaray;
	function getConfig($confKey) {
		if (array_key_exists($confKey, $this->config)){
			return $this->config[$confKey];
		}
		
		return array();
	}
	
	
	// checks weather a file is relevant for the parsing or not
	// pathing * as folder works as wildcard
	function isRelevant($relPath, $filename, $extension) {
		
		// file ignored?
		if (array_key_exists('ignore_files', $this->config) && in_array($filename, $this->config['ignore_files'])) return false;
		
		// do we have a relevant file?
		foreach ($this->relevantFileEndings as $folder => $extensions) {
			if ((in_array($folder, $relPath) || $folder == '*' ) && in_array($extension, $extensions)) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * @param Array $path 	-	array of the relative project path
	 * @param File $file	-	File Object of the file
	 */
	function addFile($path, $file){
		
		$currentFolder = $this->structureOfRelevantFiles;
		
		for ($i = 0; $i < count($path); $i++) {
			
			if (! $currentFolder->hasChild($path[$i])) {
				$currentFolder->addFolder($path[$i]);
			}
			
			$currentFolder = $currentFolder->getChild($path[$i]);
			
		}
		
		$currentFolder->addChild($file->name, $file);
		
	}
	
	/**
	 * @param Array $path 		-	array of the relative project path
	 * @param String $filename	-	File Object of the file
	 */
	function fileExists($path, $filename){
		$currentFolder = $this->structureOfRelevantFiles;
		for ($i = 0; $i < count($path); $i++) {
			if (! $currentFolder->hasChild($path[$i])) {
				return false;
			}
			$currentFolder = $currentFolder->getChild($path[$i]);
		}
		
		return $currentFolder->hasChild($filename);
	}
	
	function initaliseDataMap() {
		for ($i = 0; $i < count($this->extractmode); $i++) {
			$this->dataMap[$this->extractmode[$i]] = array();
			
			switch ($this->extractmode[$i]){
				case Fileparser::$EXTRACT_MODES['INCLUDES']:
					foreach ($this->relevantFileEndings as $folder => $extensions) {
						$this->dataMap[$this->extractmode[$i]][$folder] = array();
					}
					break;
				case Fileparser::$EXTRACT_MODES['CSS']:
					
					$adds = array('classes', 'classes_dynamic', 'ids', 'ids_dynamic');
					
					for ($k = 0; $k < count($adds); $k++) {
						$css_subindex = $adds[$k];
						$this->dataMap[$this->extractmode[$i]][$css_subindex] = array();
						foreach ($this->getConfig('include_css_' . $css_subindex) as $index => $key) {
							$this->dataMap[$this->extractmode[$i]][$css_subindex][$key] = 1; 
						}	
					}
					break;
			}
		}
	}
	
	/**
	 *	Merge function to combine the data of a single file into the global file data
	 *
	 *	@param Array $dataMap	-	A data map of a file
	 */
	function addData($dataMap) {
		$this->dynamicRecurseDataAdd($dataMap);
	}
	
	private function dynamicRecurseDataAdd($tree, $access = ''){
		
		foreach ($tree as $key => $value) {
			
			$t_key = (is_string($key)) ? "'" . str_replace("'", "\\'", $key) . "'" : $key;
			$put_access =  $access . "[$t_key]";
			
			$code = "
if (! array_key_exists($t_key, \$this->dataMap$access)){
	\$this->dataMap$put_access = \$value;
} else {
	if (is_array(\$value)) {
		\$this->dynamicRecurseDataAdd(\$value, \$put_access);
	} else {
		\$this->dataMap$put_access += \$value;
	}
}
			";
			
			eval($code);
			
		}
		
	}
	
	
	/**
	 * Will lock after the given processor. If the file is not found, an exception is thrown
	 *
	 * @param String $processorName	-	The name of the Fileprocessor. will be combined into <environment>/<$processorName>Fileparser.php
	 */
	function loadFileProcessor($processorName){
		
		$processorName .= 'Fileparser';
		
		$path = str_replace('//','/',dirname(__FILE__).'/') . $this->environment . '/' . $processorName . '.php';
		if (file_exists($path)){
			require_once($path);
		} else {
			// fallback to root
			$path = str_replace('//','/',dirname(__FILE__).'/') . $processorName . '.php';
			if (file_exists($path)){
				require_once($path);
			} else {
				throw new Exception("$path does not exist. The parser could not be initialised.");
			}
		}
		
		return $processorName;
	}
	
}

?>