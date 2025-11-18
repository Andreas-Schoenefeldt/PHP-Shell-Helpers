<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../CmdIO.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Debug/libDebug.php');


/* -----------------------------------------------------------------------
 * A function to open a file and parse the content into keymaps
 *
 *
 *
 *
 * Parameter Reader
		params
			Param:
				id
				name	
				datatype - default String
				
				description
				default
				
				Für datatyoe enum:
					values
					id =>	- name
						- description
		Description
 * ----------------------------------------------------------------------- */
class CmdParameterReader {
	var $io;
	var $scriptName = null; 
	var $fileNames = array(); // Script can work on a list by default
	var $scriptDescription;
	
	var $parameters = array(); // the whole paramter - value map
	var $names = array(); // the parameter names and aliases
	var $definition = array(); // the parameter definition
	
	var $unrestricted = array(); // parameters with no restriction
	var $required = array(); // paramters which are required
	
	var $valid = true; // flag to define a valid or a invalid parameter configuration
	
	function __construct($parameters, $allowed, $scriptDescription)  {
		$this->io = new CmdIO();
		$this->scriptName = $parameters[0];
		$this->scriptDescription = $scriptDescription;
		
		$this->buildDefinition($allowed);
		
		// parse the parameters
		
		$this->parseParameters($parameters, $allowed);
		
		
		// check, if all required parameters are set
		foreach ($this->required as $paramname => $paramval) {
			if (! array_key_exists($paramname, $this->parameters)) {
				$this->print_usage();
				$this->io->fatal("The Parameter -$paramname is required for the execution of this script.", 'CmdParameterReader');
			}
		}
		
	}
	
	function getParameterDataType($paramName) {
		if (array_key_exists($paramName, $this->names)) {
			$id = $this->names[$paramName];
			if (array_key_exists('datatype', $this->definition[$id])) {
				return $this->definition[$id]['datatype'];
			} else {
				return 'String';
			}
		} else {
			$this->io->fatal("Unknown parameter $paramName", get_class($this));
		}
	}
	
	function parseParameters($parameters, $allowed){
		for ($i = 1; $i < count($parameters); $i++) {
			$param = trim($parameters[$i]);
            
			if (  substr($param, 0, 1) == '-') { // found a additional parameter
				
				$parts = explode('-', $param, 2);
				
				// get the name
				$name = $parts[1];
				if ( array_key_exists($name, $this->names)) {
					$name = $this->names[$name];
				} else {
					$this->io->fatal("Unknown parameter $name", get_class($this));
				}
				
				// get the datatype
				$dataType = $this->getParameterDataType($name);
				
				switch ($dataType) {
					default:
						throw new Exception("Datatype $dataType is not jet implemeted");
						break;
					case 'Boolean':
						$val = true;
						break;
					case 'String':
						// try the second
						if(($parameters[$i + 1] ?? false) && substr($parameters[$i + 1], 0, 1) != '-') {
							$val = 	$parameters[$i + 1];
							// do not check this parameter again
							$i++;
						} else { // if only the parameter is given
							$val = $allowed[$name]['default'] ?? '';
						}
						break;
					case 'List':
					case 'Filelist': // Filelist is depricated
						$val = array();
						while ($i + 1 < count($parameters) && count(explode('-', $parameters[$i + 1], 2)) < 2) {
							$val[] = $parameters[$i + 1];
							$i++;
						}
						break;
					case 'Enum':
						
						// try the second
						if($parameters[$i + 1] && count(explode('-', $parameters[$i + 1], 2)) < 2) {
							$val = 	$parameters[$i + 1];
							// do not check this parameter again
							$i++;
						} else { // if only the parameter is given
							$val = '';
						}
						
						// check if the parameter is part of the definition
						if (! $val = $this->isValueOfEnum($val, $name)) {
							$this->io->fatal( "Unalowed usage of parameter ".$name , get_class($this));
						}
						
						break;
					
				}
				
				$this->parameters[$name] = $val;
				
			} else if ($param) {
				
				$this->fileNames[] = $param;
				
			}
			
		}
	}
	
	function isValueOfEnum($val, $param){
		foreach ($this->definition[$this->names[$param]]['values'] as $key => $stats){
			if ($val == $key || (array_key_exists('name', $stats) && $stats['name'] == $val)) {
				return $key;
			}
		}
		return false;
	}
	
	function buildDefinition($allowed){
		$this->definition = $allowed;
		
		// add the defaults for every parameter
		foreach ($allowed as $key => $values){
			
			$this->names[$key] = $key;
			if (array_key_exists('name', $values)) {
				$this->names[$values['name']] = $key;
			}
			
			if (array_key_exists('default', $values) && !($values['only_fill_if_present'] ?? false)) {
				$this->parameters[$key] = $values['default'];
			}
			
			// add to required, if required is set explicitly
			if (array_key_exists('required', $values) && $values['required'] === true) {
				$this->required[$key] = true;
			}
		}
			
		
	}
	
	
	function getVal($paramName){
		if (array_key_exists($paramName, $this->names) && array_key_exists($this->names[$paramName], $this->parameters)) {
			return $this->parameters[$this->names[$paramName]];
		}
		
		return null;
		
	}
	
	// depricated
	// use: getFiles() : Array 
	function getFileName(){
		return (count($this->fileNames) > 0) ? $this->fileNames[0] : null;
	}
	
	function getFiles() {
		return $this->fileNames;
	}
	
	
	
	function print_usage(){
		$this->io->cmd_print('');
		$this->io->cmd_print($this->scriptDescription, true, 2);
		
		// print the parameters
		foreach ($this->definition as $key => $params) {
			$output = ">\t ";
			
			$keyStr = '';
			if (array_key_exists('name', $params) && $params['name'] != $key) {
				$keyStr .= $params['name'] . ' (-' . $key . ')';
			} else {
				$keyStr .=  $key;
			}
			
			$output .= '-'.$keyStr.':';
			
			if (array_key_exists('default', $params) || ! array_key_exists($key, $this->required)){ // this parameter has no fixed key range
				$output .= ' (optional)'; 
			}
			
			if (array_key_exists('description', $params)){
				$output .= ' ' . $params['description'];
			}
			
			$this->io->cmd_print($output);
			
			if ($this->getParameterDataType($key) == 'Enum') {
				
				foreach ($this->definition[$key]['values'] as $pId => $stats) {
					$output = ">\t\t ";
					
					if (array_key_exists('name', $stats) && $stats['name'] != $pId) {
						$output .= $stats['name'] . ' (' . $pId . ')';
					} else {
						$output .=  $pId;
					}
					$output .= ':';
					
					if (array_key_exists('default', $params) && $pId == $params['default']){
						$output .= ' (default)';
					}
					
					if (array_key_exists('description', $stats)){
						$output .= ' ' . $stats['description'];
					}
					
					$this->io->cmd_print($output);
				}
				$this->io->cmd_print('>');
			}
		}
		
		// print the last unnamed parameter
		$output = "\t ";
		$this->io->cmd_print($output . "n. parameter: the file or folder to process\n", true, 3);
		
	}
}
