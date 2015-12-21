<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../FileTranslator.php');
/**
 *	You can define and control the handling of the translate by a file called .translation.config in the project root
 *	Here is a example content: 
 *
 *	ignore_files: om.xml,mani.isml // (optional)
 *	cartridgepath: app_quiksilver,napali_ui,napali_core,..// (required for now) define the cartridgeorder as defined in the site preference file: csv
 *
 *	prefered_propertie_locations: napali_ui,int_bazaarvoice // (sort of required) defines where new resource keys should be added
 *
 */
class DemandwareFileTranslator extends FileTranslator {
	
	var $cartridgePath = array(); // the cartridgepath in order of inclusion
	
	function __construct($fileRoot, $filename, $structureInit, $mode, $environment)  {
		// define how the cartridge structure is build
		$this->relevantFileEndings = array('pipelines' => array('xml'), 'templates' => array('isml'), 'scripts' => array('ds'), 'forms' => array('xml'), 'static' => array('css', 'js'));
		
		parent::__construct($fileRoot, $filename, $structureInit, $mode, $environment);
		
	}
	
	function optimiseProject(){
		
		$this->initaliseDataMap();
		
		if (!array_key_exists('cartridgepath', $this->config) || ! $this->config['cartridgepath']) {
			throw new Exception("No line cartridgepath: found in config. The cartridge order could not be established.");
			
			// TODO: here could be a manual process of adding cartridgenames to the config file
		} 
		$this->io->out("\n> building the Cartridge Structure...");
		$this->buildCartridgeStructure();
		
		// will parse the demandware file structure, starting with the pipelines
		$this->parseCartridgeStructure();
		// now our data is filled
		
		// lets optimize our css
		$this->optimizeCSS();
		
		// register keys in the resource file handler
		$this->resourceFileHandler->registerKeyMap($this->dataMap[Fileparser::$EXTRACT_MODES['KEYS']]);
		
		// optimise resourses
		$this->resourceFileHandler->optimizeResourceFiles();
		
		
		
		$this->io->out("The project was optimized.");
	}
	
	
	// This function will convert the whole project into
	function parseCartridgeStructure(){
		
		$this->io->out("\n> processing pipelines in cartridgepath...\n");
		$pipeFolder = $this->structureOfRelevantFiles->getChild('pipelines');
		
		for ($i = 0; $i < $pipeFolder->childCount; $i++) {
			$file = $pipeFolder->getChild($i);
			
			$this->io->out('     > ' . $file->name);
			$parser = $this->processFile($file, $this->extractmode, 'Pipeline');
			$this->printChangedFiles($parser);
		}
		
		$this->io->out("\n> processing module files...\n");
		$this->dataMap[Fileparser::$EXTRACT_MODES['INCLUDES']]['modules'] = array();
		
		$moduleFiles = $this->getConfig('modules_files');
		if (count($moduleFiles) > 0) {
			for ($i = 0; $i < count($moduleFiles); $i++) {
				$file = $this->structureOfRelevantFiles->getDeepChild('templates/default/' . $moduleFiles[$i]);
				
				if (! $file) {
					$this->io->error("The referenced file {$indexArray[$i]} does not exist", 'DemandwareFileTranslator');
				} else {
					$this->io->out('     > ' . $file->name);
					$this->processFile($file, array(Fileparser::$EXTRACT_MODES['INCLUDES']), 'Module');
				}
			}
		} else {
			$this->io->error('the propertie modules_files is empty or not configured in .translation.config', 'DemandwareFileTranslator');
		}
		
		// now handle the templates
		$this->io->out("\n> processing included template structure...\n");
		
		$indexArray = array_keys($this->dataMap[Fileparser::$EXTRACT_MODES['INCLUDES']]['templates']);
		$i = 0;
		while ($i < count($indexArray)) {
			
			if (! in_array($indexArray[$i], $moduleFiles)) { // ignore modules files
			
				$file = $this->structureOfRelevantFiles->getDeepChild('templates/default/' . $indexArray[$i]);
				
				if (! $file) {
					$this->io->error("The referenced file {$indexArray[$i]} does not exist", 'DemandwareFileTranslator');
				} else {
					$this->io->out('     > ' . $file->name);
					$parser = $this->processFile($file, $this->extractmode, 'ISML');
					
					if(in_array(Fileparser::$EXTRACT_MODES['VALUES'], $this->extractmode)) {
						$this->resourceFileHandler->registerTranslationValueMap($parser->getExtractionData(Fileparser::$EXTRACT_MODES['VALUES']));
					}
					
					$this->printChangedFiles($parser);
					
					// updating the index array
					$indexArray = array_keys($this->dataMap[Fileparser::$EXTRACT_MODES['INCLUDES']]['templates']);
				}
			}
				
			$i++;
		}
		
		// now handle the forms
		$this->io->out("\n> processing forms...\n");
		$indexArray = array_keys($this->dataMap[Fileparser::$EXTRACT_MODES['INCLUDES']]['forms']);
		$i = 0;
		while ($i < count($indexArray)) {
			$file = $this->structureOfRelevantFiles->getDeepChild( 'forms/default/' . $indexArray[$i] . '.xml');
			if (! $file) {
				$this->io->error("The referenced file {$indexArray[$i]} does not exist", 'DemandwareFileTranslator');
			} else {
				$this->io->out('     > ' . $file->name);
				$parser = $this->processFile($file, $this->extractmode, 'Form');
				
				$this->printChangedFiles($parser);
				
				$indexArray = array_keys($this->dataMap[Fileparser::$EXTRACT_MODES['INCLUDES']]['forms']);
			}
			$i++;
		}
		
		// now handle the scripts
		$this->io->out("\n> processing scripts...\n");
		$indexArray = array_keys($this->dataMap[Fileparser::$EXTRACT_MODES['INCLUDES']]['scripts']);
		$i = 0;
		while ($i < count($indexArray)) {
			$file = $this->structureOfRelevantFiles->getDeepChild( 'scripts/' . $indexArray[$i]);
			
			if (! $file) {
				$this->io->error("The referenced file {$indexArray[$i]} does not exist", 'DemandwareFileTranslator');
			} else {
				$this->io->out('     > ' . $file->name);
				$parser = $this->processFile($file, $this->extractmode, 'DS');
				$this->printChangedFiles($parser);
				
				$indexArray = array_keys($this->dataMap[Fileparser::$EXTRACT_MODES['INCLUDES']]['scripts']);
			}
			
			// updating the indexarray
			
			$i++;
		}
		
	}
	
	
	
	function optimizeCSS(){
		
		$this->io->out("\n> processing css files...\n");
		
		foreach ( $this->dataMap[Fileparser::$EXTRACT_MODES['INCLUDES']]['static'] as $relPath => $includeCount) {
			
			$file = $this->structureOfRelevantFiles->getDeepChild( 'static/default/' . $relPath);
			if (! $file) {
				$this->io->error("The referenced file {$relPath} does not exist", 'DemandwareFileTranslator');
			} else {
				$this->io->out('     > ' . $file->name);
				$parser = $this->processFile($file, $this->extractmode, 'CSS');
				$parser->setGlobalIdents($this->dataMap[Fileparser::$EXTRACT_MODES['CSS']]);
				$parser->checkRules();
			
				$this->printChangedFiles($parser);
			}
			
		}
		
	}
	
	function buildCartridgeStructure(){
		
		$CI = $this;
			
		$callback = function ($path, $base) use ($CI) {
			$CI->addFile($path, $base);
		};
		
		$cPath = $this->getConfig('cartridgepath');
		for ($i = 0; $i < count($cPath); $i++) {
			
			$base = $this->root . '/' . $cPath[$i] . '/cartridge';
			
			recurseIntoFolderContent($base, $base, $callback, true);
		}
		
		if (in_array(Fileparser::$EXTRACT_MODES['INCLUDES'], $this->extractmode)){
			// adding fixed folder positions to data map
			$inclFolders = $this->getConfig('include_folders');
			$callback = function ($path, $base) use ($CI) {
				$relPath = str_replace($base, '', $path);
				$path_parts = pathinfo($relPath);
				
				if (array_key_exists('extension', $path_parts)) {
					switch($path_parts['extension']) {
						
						case 'isml':
							$relPath = str_replace($base, '', $path);
							$name = cleanupFilename(normalizePath($relPath));
							
							if (! array_key_exists($name, $CI->dataMap[Fileparser::$EXTRACT_MODES['INCLUDES']]['templates'])) {
								$CI->dataMap[Fileparser::$EXTRACT_MODES['INCLUDES']]['templates'][$name] = 1;
							}
							
							break;
					}
				}
			};
			
			for ($k = 0; $k < count($inclFolders); $k++) {
				for ($i = 0; $i < count($cPath); $i++) {	
					$folders = explode('/', $inclFolders[$k]);
					array_pop($folders);
					
					$path = $this->root . '/' . $cPath[$i] . '/cartridge/' . $inclFolders[$k];
					$base = $this->root . '/' . $cPath[$i] . '/cartridge/' . implode('/', $folders);
					
					recurseIntoFolderContent($path, $base, $callback, true);
				}
			}
		}
	}
	
	function addFile($path, $base){
		
		$path_parts = pathinfo($path);
		$filename = $path_parts['basename'];
		
		$relPath = explode('/', trim(normalizePath(str_replace($base, '', $path_parts['dirname']))));
		
		// check, if we have a relevant file
		if ($this->isRelevant($relPath, $filename, $path_parts['extension'])){
			
			// The file does not exist now add it to the structure
			if (! $this->fileExists($relPath, $filename)) {
			
				$cExtends = explode('/', $base);
				
				$file = new File($path);
				$file->cartridge = $cExtends[count($cExtends) - 2]; // extending the file with the cartridge property
				
				parent::addFile($relPath, $file);
			}
		}
	}
	
	function createFileProcessor($file, $processorName) {
		
		switch ($processorName) {
			default:
				$processor = parent::createFileProcessor($file, $processorName);
				break;
			case 'ISMLFileparser':
				// path modules array, if existent
				$modArr = (array_key_exists(Fileparser::$EXTRACT_MODES['INCLUDES'], $this->dataMap) && array_key_exists('modules', $this->dataMap[Fileparser::$EXTRACT_MODES['INCLUDES']])) ? $this->dataMap[Fileparser::$EXTRACT_MODES['INCLUDES']]['modules'] : array();
				
				$processor = new $processorName($file, $this->structureInit, $this->mode, $this->environment, $modArr);
				break;
		}
		
		return $processor;
	}
	
}

?>