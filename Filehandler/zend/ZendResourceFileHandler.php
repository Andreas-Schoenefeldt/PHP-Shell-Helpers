<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../ResourceFileHandler.php');

class ZendResourceFileHandler extends ResourceFileHandler {
	
	var $defaultLocale = 'en';
	
	// checks against zend lang files
	public function isResourceFile($filepath){
		preg_match("/languages\\/lang\.\w*?\.php/", $filepath, $matches);
		return count($matches) > 0;
	}
	
	// returns a resource file path
	function getResourceFileName($namespace, $locale, $defaultPath) {
		$info = pathinfo($defaultPath);
		return $info['dirname'] . '/' . $namespace . '.' . $locale . '.' . $info['extension'];
	}
	
	// returns an asoc array in the format 'rootfolder', 'locale', 'namespace'
	// locale must be default, if not defined
	// namespace is usually the filename without locale
	public function getResourceFileProperties($cleanpath){
		
		$nsChunks = explode('/', $cleanpath);
		
		// get the namespace
		$fileName = $nsChunks[count($nsChunks) - 1];
		
		$parts = explode('.', $fileName, 2);
		$locale = $parts[1] == $this->defaultLocale ? 'default' : $parts[1]; // we need a default language
		$namespace = $parts[0];
		
		// get the rootfolder
		$rootfolder = $nsChunks[0] ? $nsChunks[0] : $nsChunks[1];
		
		return array(
			  'rootfolder' => $rootfolder
			, 'locale' => $locale
			, 'namespace' => $namespace
		);
	}
	
	// returns an array or null
	protected function getResourceKeyValueFromLine($line) {
		
		$parts = explode('=>', $line, 2); // this is a bit unsecure but straight foreward. It needs to be a proper parsing, when we run itno issues.
		if (count($parts) >= 2) {
			// add key namespace to namespaceMap
			preg_match("/^[, \t]*?['\"](.*?)['\"]\s*?$/", $parts[0], $matches);			
			$key = $matches[1];
			
			preg_match("/^\s*?(['\"])(.*?)['\"]\s*?,?\s*?$/", $parts[1], $matches);
			$value = str_replace('\\' . $matches[1], $matches[1], $matches[2]);
		
			return array('key' => $key, 'value' => $value);
		} 
		
		return null;
	}
	
	// print a resource file
	protected function printResourceFile($ns, $rootfolder, $locale){
		
		$subTree = $this->localisationMap[$ns][$rootfolder][$locale];
		
		// print the start of the file;
		$this->io->cmd_print('<?php');
		$this->io->cmd_print('// generated at ' . date('d.m.Y H:i:s') . ' via resource file utility script');
		$this->io->cmd_print('// format: ' . $this->environment);
		$this->io->cmd_print('');
		$this->io->cmd_print('return array(');
		
		$oldKey = null;
		foreach ($subTree['keys'] as $key => $value) {
			$testKey = strtoupper(substr($key,0,1));
			
			if($oldKey == null){
				$oldKey = $testKey;
			} else if ($oldKey != $testKey){ // add a break between different blocks of keys
				$this->io->cmd_print('');
				$oldKey = $testKey;
			}
			
			switch($this->environment){
				default:
					
					// first we remove the escape in order to be sure
					$value = str_replace('\\\'', "'", $value);
					
					// we escape the string again for printing
					$value = str_replace("'", "\\'", $value);
				
					$line = "\t'" . $key."' => '" . $value . "',";
					break;
			}
			
			$this->io->cmd_print($line);
		}
		
		$this->io->cmd_print(');');
	}
}


?>