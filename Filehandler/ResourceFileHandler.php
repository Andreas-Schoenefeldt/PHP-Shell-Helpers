<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../CmdIO.php');

class ResourceFileHandler {
	var $io;
	
	var $valueMap = array(); // TranslationValueText => array (0 => array('path' => $propertiesPath, 'line' => $codeline, 'key' => $keyvalue, 'namespace' => '', 'rootfolder' =>''), 1 => ...)
	var $fileValueMap = array(); // value in file => array (translationValue => 'key to value map',  'translate' => bool, 'files' => array( filename => array( variables = > array(), lines => array )))
	var $keyMap = array(); // recourceKey => array(namspace1 => array(count => , files => array( filepath => array( 12, 89, 108, ...)), cartridges => array('', ...)))
	var $variableKeyMap = array(); // recourceKey => array(namspace1 => array(count => , files => array( filepath => array( 12, 89, 108, ...)), cartridges => array('', ...)))
	
	var $ignoreWriteMap = array(); // file => array(value1, value 2, ... ) // map of specific keys in specific files, that will be written into the project config file.
	var $ignoreWriteMapChanged = false;
	
	var $localisationFiles = array(); // 0 => array('file' => $filepath, 'namespace' => $ns), 1 => path2, ...
	var $localisationMap = array();  //namespace  => array(  [rootfolder] => array( "default" => array(path =>, keys =>, imported => ), [locale1] => ), ...) 
	
	var $currentDefaultFile;
	var $preferedPropertieLocations = array();
	
	var $namespaceMap = array();
	var $changedNamespaces = array(); // $namespace => array( $locale => $textadd)
	
	// functional variables
	var $resourceFileParsingMode;
	var $environment;
	
	function __construct($mode, $environment){
		
		switch ($mode) {
			default:
				$this->resourceFileParsingMode = 'keys';
				break;
			case 'find':
				$this->resourceFileParsingMode = 'values';
				break;
			case 'both':
			case 'project_optimize':
				$this->resourceFileParsingMode = 'both';
				break;
			
		}
		
		$this->environment = $environment;
		
		$this->io = new CmdIO();
	}
	
	function setIgnoreMap($ignoredValues){
		$this->ignoreWriteMap = $ignoredValues;
	}
	
	function parsingModeIs($mode){
		return ($mode == $this->resourceFileParsingMode || $this->resourceFileParsingMode == 'both');
	}
	
	// Returns all the localized key value pairs of the default location(s) for this namespace
	//
	// @param $namespace	the namespace to get
	// @param $cartridges	allows to override the prefered locations with another cartridge path
	function getPreferedLocalisationMap($namespace, $cartridges = array()) {
		
		$result = array();
		
		foreach ($this->localisationMap[$namespace] as $rootfolder => $locales) {
			if (in_array($rootfolder, $cartridges) || $this->isPreferedLocation($rootfolder)) {
				$result[$rootfolder] = $locales;
			}
		}
		
		// if we have not found a prefered one, we return the first entry
		foreach ($this->localisationMap[$namespace] as $rootfolder => $locales) {
			$result[$rootfolder] = $locales;
			break;
		}
		
		// in case it was empty
		return $result;
	}
	
	/**
	 * @param Array $preferedPropertieLocations		-	Array of the target Resource File root folders for Key adds
	 */
	function setPreferedPropertieLocations($preferedPropertieLocations){
		$this->preferedPropertieLocations = $preferedPropertieLocations;
	}
	
	function isPreferedLocation($rootfolder){
		return (!count($this->preferedPropertieLocations) || in_array($rootfolder, $this->preferedPropertieLocations));
	}
	
	// check if translation keys for a value are located in a prefered resource file
	function valueHasKeyInPreferedLocation($value) {
		$entrys = $this->getKeyByValue($value);
		for ($i = 0; $i < count($entrys); $i++) {
			$entry = $entrys[$i];
			if ($this->isPreferedLocation($entry['rootfolder'])) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 *	Will print a list to the user and ask him to select a localisation base file, if nothing is selected.
	 *
	 *
	 */
	function setDefaultFile($force = false) {
		
		$do = $force;
		
		if ($do || empty($this->currentDefaultFile)) {
			$this->io->out();
			
			$rStr = '';
			
			foreach ($this->localisationFiles as $int => $path){
				if ($this->isPreferedLocation($path['rootfolder'])) {
					if ($rStr != '' && $rStr != $path['rootfolder']) $this->io->out();
					$this->io->cmd_print("  [$int] - ".$path['rootfolder'] . ': ' . $path['filename']);
					$rStr = $path['rootfolder'];
				}
			}
			
			$this->io->out();
			
			$index = $this->io->readStdInn("Choose a resource file index or ignore (#)");
			
			if ($index == '#') {
				return false;
			} else {
				$this->currentDefaultFile = $this->localisationFiles[intval($index)];
			}
		}
		
		return true;
	}
	
	function getDefaultFile(){
		return $this->currentDefaultFile;
	}
	
	
	function getLocals($namespace, $folder) {
		return array_keys($this->localisationMap[$namespace][$folder]);
	}
	
	/* Get the prefered root for this namespace */
	function getBestRootFolder($namespace) {
		if (array_key_exists($namespace, $this->localisationMap)) {
			
			if (count($this->preferedPropertieLocations) > 0 && array_key_exists($this->preferedPropertieLocations[0], $this->localisationMap[$namespace])) {
				return $this->preferedPropertieLocations[0];
			} else {
				foreach ($this->localisationMap[$namespace] as $folder => $locales) {
					
					if (count($this->preferedPropertieLocations) == 0) $this->io->error('prefered_propertie_locations was not defined in .translation.config file. trying the guess $folder.', "ResourceFileHandler");
					
					if ($this->isPreferedLocation($folder)) {
						return $folder;
					}
				}
			}
		} else {
			$this->io->error('Namespace "' . $namespace . '" does not exist.', 'ResourceFileHandler');
		}
		
		return null;
	}
	
	// function which investigates weather it is a resource file or not
	public function isResourceFile($filepath){
		$ending = pathinfo($filepath, PATHINFO_EXTENSION);
		
		if ($ending != 'properties') return false; // only property files can be resource files
		
		switch ($this->environment){
			case 'demandware':
				if (! strpos($filepath, '/templates/resources/')) return false; // only take demandware resource files, that are located in a templates / resources folder
				break;
		}
		
		return true;
	}
	
	// returns an asoc array in the format 'rootfolder', 'locale', 'namespace'
	// locale must be default, if not defined
	// namespace is usually the filename without locale
	public function getResourceFileProperties($cleanpath){
		$nsChunks = explode('/', $cleanpath);
		
		// get the namespace
		$ns = $nsChunks[count($nsChunks) - 1];
		
		// checkout, weather it is a localised one
		$oc = explode ('_', $ns, 2);
		$locale = (count($oc) < 2) ? 'default' : $oc[1];
		$namespace = $oc[0];
		
		// get the rootfolder
		$rootfolder = ($nsChunks[0]) ? $nsChunks[0] : $nsChunks[1];
		
		return array(
			  'rootfolder' => $rootfolder
			, 'locale' => $locale
			, 'namespace' => $namespace
		);
	}
	
	/* -------------------------
	 * Function that is actualy building up the keyfile Called by the outside
	 *
	 */
	public function addResourceFile($filepath, $baseDirectory){
		
		$fileparts = explode('.', $filepath);
		
		if ( $this->isResourceFile($filepath) ) {
			$info = pathinfo($filepath);
			$cleanpath = substr($filepath, strlen($baseDirectory), -( strlen($info['extension']) + 1 )); // remove eg. .properties from the pathname and only take the relative path into account
			
			$rfProperties = $this->getResourceFileProperties($cleanpath);
			
			$baseInfoEntry = array('file' => $filepath, 'filename' =>  $info['basename']);
			
			$namespace = $rfProperties['namespace'];
			$rootfolder = $rfProperties['rootfolder'];
			$locale = $rfProperties['locale'];
			
			if ($locale == 'default') {
				$baseInfoEntry['rootfolder'] = $rootfolder;
				$baseInfoEntry['namespace'] = $namespace;
				$baseInfoEntry['subfiles'] = array();
				
				$this->localisationFiles[] = $baseInfoEntry;
			} else {
				// find out where this subfile belongs to
				for ($i = 0; $i < count($this->localisationFiles); $i++) {
					$entry = $this->localisationFiles[$i];
					
					if ($entry['namespace'] == $namespace && $entry['rootfolder'] == $rootfolder) {
						$this->localisationFiles[$i]['subfiles'][$locale] = $baseInfoEntry;
					}
				}
				
			}
			
			$this->io->cmd_print('> ('.$this->resourceFileParsingMode.') found '.str_replace($baseDirectory,'.', $filepath));
			
			// initial creation
			if(! array_key_exists($namespace,  $this->localisationMap)) {
				$this->localisationMap[$namespace] = array();
			}
			
			if (! array_key_exists($rootfolder, $this->localisationMap[$namespace])) {
				$this->localisationMap[$namespace][$rootfolder] = array();
			}
			
			if(! array_key_exists($locale, $this->localisationMap[$namespace][$rootfolder])) {
				$this->localisationMap[$namespace][$rootfolder][$locale] = array('path' => $filepath, 'keys' => array(), 'imported' => false);
			}
			
			// add file by default, if we are parsinf values
			if ($this->parsingModeIs('values')) {
				// load them all... without importing the keys
				$this->parseResourceFile($filepath, $namespace, $rootfolder, $locale);
			}
			
			return true;
		}
		
		return false;
	}
	
	
	/** function to parse a resource File. Call this function, if you want to import an unimported file
	 *
	 */
	function importResourceFile($namespace, $rootfolder, $mode=false, $locale = 'default') {
		
		if ($mode) {
			$oldMode = $this->resourceFileParsingMode;
			$this->resourceFileParsingMode = $mode;
		}
		
		$entry = $this->localisationMap[$namespace][$rootfolder][$locale];
		
		if($entry['imported'] === false) {
			
			d("Importing $namespace for lovale $locale");
			
			$this->parseResourceFile($entry['path'], $namespace, $rootfolder, $locale);
			$this->localisationMap[$namespace][$rootfolder][$locale]['imported'] = true; // use again the full path, otherwise the change is not stored
		}
		
		if ($mode) {
			$this->resourceFileParsingMode = $oldMode;
		}
	}
	
	/**
	 *	Function to import a whole namespace
	 */
	function importNamespace($namespace) {
		
		$roots = $this->localisationMap[$namespace];
		foreach ($roots as $rootfolder => $locals) {
			foreach ($locals as $local => $stats) {
				$this->importResourceFile($namespace, $rootfolder, false, $local);
			}
		}
	}
	
	// returns an array or null
	protected function getResourceKeyValueFromLine($line) {
		
		$parts = explode('=', $line, 2);
		if (count($parts) >= 2 && strrpos( $parts[0], '#') === false) { // only if '=' was found and '#' not
			// add key namespace to namespaceMap
			$key = trim($parts[0]);
			$value = trim($parts[1]);
			
			return array('key' => $key, 'value' => $value);
		} 
		
		return null;
	}
	
	/**
	 * Called by other functions like importResourceFile or parseValues
	 * Does the actual parsing of the resource file
	 *
	 * Assumes, that the localisation map is already build
	 */
	function parseResourceFile($path, $namespace, $rootfolder, $locale = 'default'){
		
		$fp = fopen($path, "r");
			
		$l = 1; // linecounter
		while($line = fgets($fp, 1024)){
			
			$keyVal = $this->getResourceKeyValueFromLine($line);
			
			if ($keyVal) {
				$key = $keyVal['key'];
				$value = $keyVal['value'];
				
				$ns = explode('.', $key);
				if (!array_key_exists($ns[0], $this->namespaceMap)) {
					$this->namespaceMap[$ns[0]] = $namespace;
				}
				
				// add message to key
				if ($this->parsingModeIs('keys')) {
					
					$this->localisationMap[$namespace][$rootfolder][$locale]['keys'][$key] = $value;
					
					$keyMapEntry = array('count' => 0, 'files' => array(), 'cartridges' => array($rootfolder));
					
					if (array_key_exists($key, $this->keyMap)){
						if (array_key_exists($namespace, $this->keyMap[$key])){
							$this->keyMap[$key][$namespace]['cartridges'][] = $rootfolder;
						} else {
							$this->keyMap[$key][$namespace] = $keyMapEntry;
						}
					} else {
						 $this->keyMap[$key] = array();
						 $this->keyMap[$key][$namespace] = $keyMapEntry;
					}
					
					
				}
				if ($this->parsingModeIs('values')) {
					$this->addKeyByValue($value, $path, $key, $namespace, $rootfolder, $l);
				}
			}
			
			$l++;
		}
		
		if ($this->parsingModeIs('keys')) {
			$this->localisationMap[$namespace][$rootfolder][$locale]['imported'] = true;
		}
		
		return true;
	}
	
	/**
	 *	Function to fill the valueMap
	 *
	 *	@param	String	$value				-	the value
	 *	@param	String	$recourceFilePath	-	the path of the recource file
	 *	@param	String	$key				-	The corresponding key of the value
	 *	@param	String	$namespace			-	The namespace of the translation
	 *	@param	String	$rootfolder			-	The rootfolder of the recource file
	 *	@param	Int		$lineNumber			-	The linenumber of the key, if it comes from a recource file. null otherwise
	 *	
	 */
	function addKeyByValue($value, $recourceFilePath, $key, $namespace, $rootfolder, $lineNumber = null, $before = '', $after = ''){
		if (! array_key_exists($value, $this->valueMap)) {
			$this->valueMap[$value] = array();
		} else {
			// $this->io->cmd_print('     > dublicate key for value: '.$value);
		}
		
		$this->valueMap[$value][] = array(
			'path' => $recourceFilePath,
			'line' => $lineNumber,
			'key' => $key,
			'namespace' => $namespace,
			'rootfolder' => $rootfolder,
			'before' => $before,
			'after' => $after
		); 
	}
	
	// Translation functions
	
	function getKeyByValue($value) {
		if (array_key_exists($value, $this->valueMap)) {
			
			return $this->valueMap[$value];
		}
		
		return false;
	}
	
	function getFileValue($value) {
		if (array_key_exists($value, $this->fileValueMap)) {
			return $this->fileValueMap[$value];
		}
		
		return false;
	}
	
	 // value in file => array (translationValue => 'key to value map', 'files' => array( filename => array( variables = > array(), lines => array )))
	function addFileValue($value, $translationValue, $files = array(), $translate = true){
		$this->fileValueMap[$value] = array('translationValue' => $translationValue, 'translate' => $translate, 'files' => $files);
	}
	/**
	 *	Will set the file value translation status, and also add the file => value to the $ignoreWriteMap if the status is false
	 *
	 *	@param $value		- 	the value to translate
	 *	@param $status		-	the new translationstatus of the value
	 */
	function setFileValueTranslationStatus($value, $status) {
		if (array_key_exists($value, $this->fileValueMap)) {
			$this->fileValueMap[$value]['translate'] = $status;
			
			foreach ($this->fileValueMap[$value]['files'] as $filename => $stats){
				if ($status) {
					if (array_key_exists($filename, $this->ignoreWriteMap)) {
						throw new Exception("not implemented");
						// unset($this->ignoreWriteMap[$filename]); // remove also from ignore write map
					}
				} else {
					if (! array_key_exists($filename, $this->ignoreWriteMap)) $this->ignoreWriteMap[$filename] = array();
					$this->ignoreWriteMap[$filename][] = $value;
				}
			}
			
			$this->ignoreWriteMapChanged = true;
		}
	}
	
	function updateTranslationStatusWithIgnoreList($value){
		
		if (array_key_exists($value, $this->fileValueMap)) {
			
			foreach ($this->fileValueMap[$value]['files'] as $filename => $stats){
				if (array_key_exists($filename, $this->ignoreWriteMap)) {
					$values = $this->ignoreWriteMap[$filename];
					for ($i = 0; $i < count($values); $i++){
						if ($values[$i] == $value) { // we found the value in the ignore list, set translate to false and return
							$this->fileValueMap[$value]['translate'] = false;
							return $this->getFileValue($value);
						}
					}
				}
			}
		}
		
		return $this->getFileValue($value);
	}
	
	// returns a resource file path
	function getResourceFileName($namespace, $locale, $defaultPath) {
		return str_replace($namespace, $namespace . '_' . $locale, $defaultPath);
	}
	
	// Will add or change a key for a certain value
	// this is the MAIN function to add or update new key value pairs to the resource files
	function setKeyForFile($namespace, $key, $value, $rootfolder, $locale = 'default', $before = '', $after = ''){
		
		if($rootfolder) {
		
			if ( !array_key_exists($locale, $this->localisationMap[$namespace][$rootfolder])) {
				$this->getLocalisationMapEntry($namespace, $rootfolder, $locale);
				// here we create the new file name - it is environment specific, so the called function might be overwritten
				$this->localisationMap[$namespace][$rootfolder][$locale]['path'] = $this->getResourceFileName($namespace, $locale, $this->localisationMap[$namespace][$rootfolder]['default']['path']);
				$this->localisationMap[$namespace][$rootfolder][$locale]['imported'] = true;
			}
			
			if (!array_key_exists($key, $this->localisationMap[$namespace][$rootfolder][$locale]['keys']) || $this->localisationMap[$namespace][$rootfolder][$locale]['keys'][$key] != $value) {
				
				if ( !array_key_exists($namespace, $this->changedNamespaces)) 				$this->changedNamespaces[$namespace] = array();
				if ( !array_key_exists($rootfolder, $this->changedNamespaces[$namespace])) 	$this->changedNamespaces[$namespace][$rootfolder] = array();
					
				$val = ($locale == 'default') ? '' : '_' . $locale;
				
				if ( !array_key_exists($locale, $this->changedNamespaces[$namespace][$rootfolder])) {
					$this->changedNamespaces[$namespace][$rootfolder][$locale] = $val;
				}
				
				$this->localisationMap[$namespace][$rootfolder][$locale]['keys'][$key] = $value;
				
				// add also to value map, if not existing
				$this->addKeyByValue($value, $this->localisationMap[$namespace][$rootfolder][$locale]['path'], $key, $namespace, $rootfolder, null, $before, $after);
				
				// add also to keymap
				if (! array_key_exists($key, $this->keyMap)) 				$this->keyMap[$key] = array();
				if (! array_key_exists($namespace, $this->keyMap[$key]))	$this->keyMap[$key][$namespace] = array();
			}
		} else {
			$this->io->error('Could not find rootfolder for key ' .  $key);
		}
	}
	
	
	function resourceKeyExists($key, $namespace = false){
		
		if (is_bool($key)) {
			return false;
		}
		
		$namespace = ($namespace) ? $namespace : $this->getBestResourceKeyNamespace($key);
		
		if ($namespace) {
			$this->importNamespace($namespace);
		}
		
		return array_key_exists($key, $this->keyMap);
	}
	
	
	// function to return a namespace based on  key
	function getBestResourceKeyNamespace($key) {
		
		// d($this->keyMap);
		// d('|' . $key . '|');
		
		if (array_key_exists($key, $this->keyMap)) {
			foreach ($this->keyMap[$key] as $namespace => $stats){
				
				foreach ($stats['cartridges'] as $index => $cartridge) {
					if(in_array($cartridge, $this->preferedPropertieLocations)) { // bingo, this file we wan't
						return $namespace;	
					}
				}
			}
			
			// in case we found no prefered
			return $namespace;	
			
		} else {
			$parts = explode('.', $key);
			
			for ($i = 0; $i < count($parts); $i++) {
				if (array_key_exists($parts[$i], $this->localisationMap)) {
					return $parts[$i];
				}
			}
			$oldDefaultFile = $this->getDefaultFile();
			$this->io->out("We could not find a proper recource file for the unknown key " . $key . " in the project. Please select a fitting one.");
			$this->setDefaultFile(true);
			$namespace = $this->currentDefaultFile['namespace'];
			$this->currentDefaultFile = $oldDefaultFile;
			
			return $namespace;
		}
		
		return null;
	}
	
	
	// $this->keyMap: recourceKey => array(namspace1 => array(count => , files => array( filepath => array( 12, 89, 108, ...)), cartridges => array('', ...)))
	function registerKeyMap($keyMap) {
		
		foreach ($keyMap[Fileparser::$SEARCH_MODES['KEYS']] as $key => $namespaces) {
			foreach ($namespaces as $namespace => $stats) {
				$this->registerKey($key, $namespace, $stats);
			}
		}
		
		foreach ($keyMap[Fileparser::$SEARCH_MODES['KEYS_WITH_VARIABLES']] as $key => $namespaces) {
			if (! array_key_exists($key, $this->variableKeyMap)) 	$this->variableKeyMap[$key] = array();
			
			foreach ($namespaces as $namespace => $stats) {
				if (! array_key_exists($namespace, $this->variableKeyMap[$key])) 	$this->variableKeyMap[$key][$namespace] = array('count' => '', 'files' => array());
				
				$this->variableKeyMap[$key][$namespace]['count'] = $stats['count'];
				
				foreach ($stats['files'] as $path => $lines) {
					$this->variableKeyMap[$key][$namespace]['files'][$path] = $lines;
				}
			}
		}
	}
	
	
	function registerKey($key, $namespace = false, $stats = array()) {
		
		$namespace = (! $namespace) ? $this->getBestResourceKeyNamespace($key) : $namespace;
		
		if ($namespace) {
		
			if (array_key_exists($key, $this->keyMap)){
				$add = true;
			} else {
			
				$rootfolder = $this->getBestRootFolder($namespace);
				if ($rootfolder === null) {
					$this->io->read("Do you want to create the file? You can select a existing namespace otherwise. (y/N) ");
					throw new Exception("No prefered folder for namespace $namespace.");
				}
				$locales = $this->getLocals($namespace, $rootfolder);
				$this->io->out('');
				$add = false;
				for( $i = 0; $i < count($locales); $i++) {
					$locale = $locales[$i];
					
					$value = $this->io->readStdInn("> [$locale] (enter to ignore) Please complete the key $key");
					
					if ($value !== '') {
						$this->setKeyForFile($namespace, $key, $value, $rootfolder, $locale);
						$add = true;
					} else {
						break; // user ignored this key
					}
				}
			}
			
			if ($add) {
				$this->keyMap[$key][$namespace]['count'] = $stats['count'];
						
				foreach ($stats['files'] as $path => $lines) {
					$this->keyMap[$key][$namespace]['files'][$path] = $lines;
				}
			}
		}
	}
	
	
	function registerTranslationValueMap($valueMap){
		foreach ($valueMap as $value => $valueObj){
			$this->registerTranslationValue($value, $valueObj);
		}
	}
	
	/**
	 *	Takes a map with the following format and will try to merge it into the existing structure
	 *
	 *	Array(
			[default] => Array(
			  [keys] => Array(
				[key1]                     => "value1"
				[key2]                 	   => "value2"
			  )
			)
			[fr]      => Array(
			  [keys] => Array(
				[key1]                     => "value1"
				[key2]                 	   => "value2"
	 */
	function mergeKeyFileExtract($keyMap) {
		
		foreach ($keyMap as $locale => $stats) {
			foreach ($stats['keys'] as $key => $value){
				$namespace = $this->getBestResourceKeyNamespace($key);
				$rootfolder = $this->getBestRootFolder($namespace);
				
				$this->setKeyForFile($namespace, $key, $value, $rootfolder, $locale);
				
			}
		}
		
	}
	
	/**
	 *
	 * Will register a translation value. If no corresponding key is found, the function will ask for a translation
	 *
	 * returns true, if the file needs to be changed, false otherwise
	 *
	 * 	[In Event of Temporary Errors]                                                                                          => Array(
			[0] => Array(
				[path]       => /Volumes/Data/Andreas/Freelance/BeExcelent/Quiksilver/master/bc_jobframework/cartridge/templates/resources/jobs.properties
				[line]       => 17
				[key]        => job.edit.onTemporaryError
				[namespace]  => jobs
				[rootfolder] => bc_jobframework
			)
		)
	 */
	function registerTranslationValue($value, $valueObj){
		$fileNeedsToChange = false;
		if ($valueObj['translate']) { // ignore untranslatable keys
		
			if ($this->getFileValue($value)) {
				// only extend the files
				$entry = $this->getFileValue($value);
				foreach ($valueObj['files'] as $file => $stats) {
					if (! array_key_exists($file, $entry['files'])) {
						$this->fileValueMap[$value]['files'][$file] = $stats;
					}
				}
			} else {
				// value in file => array (translationValue => 'key to value map', 'files' => array( filename => array( variables = > array(), lines => array )))
				$this->addFileValue($value, $valueObj['value'], $valueObj['files']);
			}
			
			$entry = $this->updateTranslationStatusWithIgnoreList($value);
			
			// no valid namespace or no entry at all found -> let the user deside
			if($entry['translate'] && ( $this->getKeyByValue($valueObj['value']) === false || ! $this->valueHasKeyInPreferedLocation($valueObj['value']))) {
				foreach ($valueObj['files'] as $file => $stats ) {
					
					$this->io->cmd_print("[TRANSLATE] ".$valueObj['value']." in line(s) ".implode(", ", $stats['lines']), true, 1);
					
					if ($this->setDefaultFile()) {
					
						$ns = $this->currentDefaultFile['namespace'];
						$rootfolder = $this->currentDefaultFile['rootfolder'];
						
						// import the selected file
						$this->importResourceFile($ns, $rootfolder, 'keys');
						$this->io->cmd_print("");
						
						$filename = explode('/', $file);
						$filename = explode('.', $filename[count($filename) - 1]);
						$filename = $filename[0];
						
						
						$keyBase = $ns.'.'.$filename.'.';	
						$somethingToTranslate = false;
						
						$suggestedKey = $this->getSuggestedKey($valueObj['value']);
						
						$addkey = $this->completeKey($keyBase, $suggestedKey);
						
						if ($this->resourceKeyExists($addkey)) {
							throw new Exception('The Key ' . $addkey . ' already exists for the namespace ' . $ns);	
						}
					} else {
						$addkey = false;
					}
					
					
					// could be, that the user desided to ignore the key	
					if ($addkey) {
						$this->setKeyForFile($ns, $addkey, $valueObj['value'], $rootfolder, 'default', $valueObj['before'], $valueObj['after']);
						$fileNeedsToChange = true;
					} else {
						// do not translate this text
						$this->setFileValueTranslationStatus($value, false);
					}
					
					if (! count($stats['variables'])) {
						break;
					}
				}
			} else if ($entry['translate']){
				$fileNeedsToChange = true;
			}
		}
		
		return $fileNeedsToChange;
	}
	
	// returns a nicley formated key
	function getSuggestedKey($val){
		$val = trim(str_replace(array(':', '.', '-', '&', ';', "'" , '>', '<' , '*', '{', '}', '?', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '('. ')', '[', ']', '/', '|' ,'\\'), '', $val));
		$val = str_replace(array('é', 'è') , 'e', $val);
		$keyParts = explode(' ',$val);
		$suggestedKey = '';
		
		for ($i = 0; $i < count($keyParts) && $i < 3; $i++) {
			if ($i == 0) {
				$suggestedKey .= strtolower(substr($keyParts[$i],0,1)) . substr($keyParts[$i], 1);
			} else {
				$suggestedKey .= strtoupper(substr($keyParts[$i],0,1)) . substr($keyParts[$i], 1);
			}
		}
		
		return $suggestedKey;
	}
	
	function completeKey($keyBase, $suggestedKey){
		global $io;
		$keyC = $keyBase.$suggestedKey;
		
		// ask the user
		$keyAdd = $this->io->readStdInn("   > Take the key $keyC (enter), don't translate (#) or complete the key ".$keyBase);
		
		if ($keyAdd == '#') {
			return false;
		} elseif ($keyAdd != '') {
			$keyC = $keyBase.$keyAdd;
		}
		
		return $keyC;
	}
	
	
	
	function getLocalisationMapEntry($namespace, $rootfolder, $locale) {
		
		if ( !array_key_exists($locale, $this->localisationMap[$namespace][$rootfolder])) {
			$this->localisationMap[$namespace][$rootfolder][$locale] = array(
				'path' => '',
				'keys' => array(),
				'imported' => false
			);
		}
		
		return $this->localisationMap[$namespace][$rootfolder][$locale];
	}
	
	
	/**
	 *	optimzes all the resource files of the project. Do only call after a project keyMap was registered with registerKeyMap
	 */
	function optimizeResourceFiles(){
		
		if (count($this->preferedPropertieLocations)) {
			
			$removedKeys = array();
			// empty prefered PropertieLocations
			foreach ($this->localisationMap as $namespace => $folders) {
				foreach ($folders as $folder => $locales) {
					
					if (in_array($folder, $this->preferedPropertieLocations)) {
						foreach ($locales as $locale => $stats) {
							
							// move keys to removed keys
							if (! array_key_exists($namespace, $removedKeys)) {
								$removedKeys[$namespace] = array();
								$removedKeys[$namespace][$folder] = array();
							}
							
							$removedKeys[$namespace][$folder][$locale] = array('keys' => $this->localisationMap[$namespace][$folder][$locale]['keys']);
							$this->localisationMap[$namespace][$folder][$locale]['keys'] = array(); // empty localisation map
							
						}
					}
				}
			}
			
			
			// now add used keys only
			foreach ($this->keyMap as $key => $namespaces) {
				foreach ($namespaces as $namespace => $stats) {
					
					// only check, if the key was included at alls
					if ($stats['count'] > 0) {
					
						// key is in prefered cartridge?
						$rightPosition = false;
						for ($i = 0; $i < count($stats['cartridges']); $i++) {
							$folder = $stats['cartridges'][$i];
							if (in_array($folder, $this->preferedPropertieLocations)) {
								// remove from deleted, add to keys
								foreach ($removedKeys[$namespace][$folder] as $locale => $rest) {
									if (array_key_exists($key, $removedKeys[$namespace][$folder][$locale]['keys'])) { // only do soemthing, if the key was already removed
										$this->setKeyForFile($namespace, $key, $removedKeys[$namespace][$folder][$locale]['keys'][$key], $folder, $locale);
										unset($removedKeys[$namespace][$folder][$locale]['keys'][$key]);
									} 
								}
								$rightPosition = true;
							}
						}
						
						// dublicate to master property location folder if not in prefered cartridge
						if (! $rightPosition) {
							$newFolder = $this->preferedPropertieLocations[0];
							
							// this file does not jet exist in the prefered location - create the folder
							if (! array_key_exists($newFolder, $this->localisationMap[$namespace])){
								$this->localisationMap[$namespace][$newFolder] = array();
								$removedKeys[$namespace] = array();
								$removedKeys[$namespace][$newFolder] = array();
								// dublicate the old folder structure
								foreach ($this->localisationMap[$namespace][$folder] as $locale => $rest) {
									$this->getLocalisationMapEntry($namespace, $newFolder, $locale);
									$this->localisationMap[$namespace][$newFolder][$locale]['path'] = str_replace($folder, $newFolder, $this->localisationMap[$namespace][$folder][$locale]['path']);
									$this->localisationMap[$namespace][$newFolder][$locale]['imported'] = true;
									$remKeysArr =  $this->getLocalisationMapEntry($namespace, $folder, $locale);
									$removedKeys[$namespace][$newFolder][$locale] = array('keys' => $remKeysArr['keys']);
								}
							}
							
							foreach ($this->localisationMap[$namespace][$newFolder] as $locale => $rest) {
								$this->setKeyForFile($namespace, $key, $this->localisationMap[$namespace][$folder][$locale]['keys'][$key], $newFolder, $locale);
								unset($removedKeys[$namespace][$newFolder][$locale]['keys'][$key]);
							}
						}
					}
				}
				
				
				// now check if the removed kexs contain a variable key
				// test the starting string
				
				foreach ($this->variableKeyMap as $testKey => $tkNameSpaces) {
					foreach ($tkNameSpaces as $tkNamespace => $tkStats) {
						
						if (array_key_exists($tkNamespace, $removedKeys)){ // in case a unused namespace was used
						
							foreach ($removedKeys[$tkNamespace] as $folder => $locales) {
								foreach ($locales as $locale => $keys) {
									
									foreach ($keys['keys'] as $key => $value) {
										if (strpos($key, $testKey) === 0) {
											$this->setKeyForFile($tkNamespace, $key, $removedKeys[$tkNamespace][$folder][$locale]['keys'][$key], $folder, $locale);
											unset($removedKeys[$tkNamespace][$folder][$locale]['keys'][$key]);
											
										}
										
									}
									
								}
							}
						} else {
							// todo: create this key or try to find a better fitting one
						}
					
					}
				}
				
			}
			
			$this->io->out("\n" . '> Unused Keys:');
			
			d($removedKeys);
			
			if (strtolower($this->io->read('> The above keys are not used and will be removed. Continue with printing the changed resource files? (y/N)')) == 'y') {
				// print the new resource files
				$this->printChangedResourceFiles();
			}
			
		} else {
			$this->io->error('No Prefered Locations defined in the projects config file.', 'ResourceFileHandler');
		}
		
	}
	
	
	// ---------------------  Print Functions -------------------------------
	
	/**
	 * This function returns the string that is included into the code file to call the translation
	 *
	 */
	function getTranslationString($fileValueKey, $file = false, $inText = true){
		
		$val = $fileValueKey;
		$fvEntry = $this->getFileValue($fileValueKey);
		if ($fvEntry && $fvEntry['translate']) {
			$valueKey = $fvEntry['translationValue'];
			for ($i = 0; $i < count($this->valueMap[$valueKey]); $i++) {
				$entry = $this->valueMap[$valueKey][$i];
				
				if ($this->isPreferedLocation($entry['rootfolder'])){
					
					$variables = array();
					
					if (!array_key_exists($file, $fvEntry['files']) || $file === false) {
						// take the first
						foreach ($fvEntry['files'] as $file => $stats){
							$variables = $stats['variables'];
							break;
						}
					} else {
						$variables = $fvEntry['files'][$file]['variables'];
					}
					
					if (count($variables) > 0) {
						$msg = 'msgf';
						$msgArgAdd = ', '.implode(', ', $variables);
					} else {
						$msg = 'msg';
						$msgArgAdd = '';
					}
					
					$val  = $entry['before'];
					$val .= ($inText) ? '${' : '';
					$val .= 'Resource.'.$msg.'(\''.$entry['key'] ."', '". $entry['namespace'] ."', null".$msgArgAdd.")";
					$val .= ($inText) ? '}' : '';
					$val .= $entry['after'];
					break;
				}
				
			}
		}
		
		
		return $val;
	}
	
	
	// print a resource file
	protected function printResourceFile($ns, $rootfolder, $locale){
		
		$subTree = $this->localisationMap[$ns][$rootfolder][$locale];
		
		$oldKey = null;
		foreach ($subTree['keys'] as $key => $value) {
			$keyparts = explode('.', $key);
			$testKey = (count($keyparts) > 2) ? $keyparts[1] : $keyparts[0];
			
			if($oldKey == null){
				$oldKey = $testKey;
			} else if ($oldKey != $testKey){ // add a break between differnet blocks of keys
				$this->io->cmd_print('');
				$oldKey = $testKey;
			}
			
			switch($this->environment){
				default:
					$line = $key.'='.$value;
					break;
				case 'openCMS':
					$line = $key.'='. substr(json_encode($value), 1, -1);
					break;
			}
			
			
			$this->io->cmd_print($line);
		}
	}
	
	// only print the changed files
	function printChangedResourceFiles($onlyToScreen = false){
		
		$changed = false;
		
		foreach ($this->changedNamespaces as $ns => $path) {
			foreach ($path as $rootfolder => $locales) {
				foreach ($locales as $locale => $fileadd) {
					ksort($this->localisationMap[$ns][$rootfolder][$locale]['keys']);
					$changed = true;
					
					$filename = pathinfo($this->localisationMap[$ns][$rootfolder][$locale]['path'], PATHINFO_BASENAME);
					$this->io->cmd_print(">" . (($onlyToScreen) ? '' : 'writing') ." $filename ($rootfolder)", true, 1);
					
					$this->io->setWriteMode( $onlyToScreen ? 'screen' : 'screen & file', $this->localisationMap[$ns][$rootfolder][$locale]['path']);
					
					$this->printResourceFile($ns, $rootfolder, $locale);
					
					if (! $onlyToScreen) $this->io->setWriteMode('screen');
				}
			}
		}
		
		if (! $onlyToScreen) $this->changedNamespaces = array(); // now the changes are written, the changed files can be reset
		
		return $changed;
	}
}

?>