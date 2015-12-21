<?php

	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../CmdIO.php');

	/**
	 * The fixed Field File Writer Object - To write data into a textfile with ancient fixed length data type structure
	 *	
	 *	Possible Values for the definition are:
	 *			name (String) 		- the name of the field
	 *			length (Int)		- the fieldlength 
	 *			alignment (enum)	- 'left' or 'right' : were should the content be aligned in the field
	 *			fillchar (String)	- Put any char here you like to fill the blank chars with
	 *			datatype (enum)		- The Datatype of the field: String, Date (DW Date format is used), Decimal (Number with two decimal parts, without a dot eg 10043), Decimal Dot (like decimal, but with a dot. eg 100.43)
	 *			format (String)		- The DW Date format String, used to format a Date Field into a string
	 *
	 *
	 * @param fieldDefinitions 	- MUST be an array of definitions with definition Objects eg. {"name": "RETURN AUTHORIZATION DATE", "length": 8, "datatype": "Date", "format": "yyyyMMdd"}
	 * @param file				- The file to write to
	 * @param append			- create a new file or use the existing one.
	 * @param format			- the format of the output file. Can be TLV (Tag Length Value) or CSV (Comma Seperated Value)
	 *
	 */
	

class FixedFieldFileWriter {
	
	var $io = null;
	
	var $fieldDefinitions = null;
	var $definitionLabelMapping = array();
	var $currentDefinition = null;
	var $currentField = null;
	var $fieldDefaults = array(
		  'datatype' => "String"
		, 'format' => 'd.m.Y - h:m'
		, 'default' => ''
	);
	
	var $file;
	var $format = 'CSV';
	var $humanReadable = false;
	
	var $csvConfig = array(
		  'seperator' => ','
		, 'textFormatSign' => '"'
	);
	
	var $stdBlanks = "                                                                                                    ";
	
	
	function __construct($fieldDefinitions, $filepath, $append = false, $humanReadable = false) {
		$this->io = new CmdIO();
		
		if (! count($fieldDefinitions)) {
			$this->io->fatal("FixedFieldFileWriter must be initialised with a definition array or an object of definition arrays");
		}
		
		if ( $filepath) {
			
			$this->fieldDefinitions = $fieldDefinitions;
			$this->definitionLabelMapping = array('default' => array());
			$this->currentDefinition = $this->fieldDefinitions['default'];
			for ($i = 0; $i < count($this->currentDefinition); $i++) {
				$this->definitionLabelMapping['default'][$this->currentDefinition[$i]['name']] = $i;
			}
			
			$this->file = fopen($filepath,  ($append ? 'a' : 'w'));
			$this->humanReadable = $humanReadable;
			
		} else {
			$this->io->fatal("Please provide a file to write to.");
		}
	}
	
	// Functions
	// --------------------------------
	
	function writeLine($line) {
		fwrite($this->file, $line . PHP_EOL);
		return $this;
	}
	
	function printHeader($definitionName = 'default'){
		$this->currentDefinition = $this->fieldDefinitions[$definitionName];
		$lineString = '';
		
		if (empty($this->currentDefinition)) {
			$this->io->error("The definition with the name " . $definitionName . " does not exist.");
			return null;
		} else {
			
			for ($i = 0; $i < count($this->currentDefinition); $i++) {
				if ($i > 0) $lineString .= $this->csvConfig['seperator'];
				$this->currentField = $this->currentDefinition[$i];
				$lineString .= ! $this->humanReadable  && array_key_exists('machineName', $this->currentField) ? $this->currentField['machineName'] : $this->currentField['name']  ;
			}
			
		}
		
		$this->writeLine($lineString);
	}
	
	
	/**
	 *	@param	definitionName 		defines the field definition to use
	 *	@param	values				the object of line values, Object {"FIELD NAME": FieldValue, ...}
	 */
	function printLine($values, $definitionName = 'default'){
		
		$this->currentDefinition = $this->fieldDefinitions[$definitionName];
		$lineString = '';
		
		if (! $this->currentDefinition) {
			$this->io->error("The definition with the name " . $definitionName . " does not exist.");
			return null;
		} else {
			
			for ($i = 0; $i < count($this->currentDefinition); $i++) {
				$this->currentField = $this->currentDefinition[$i];
				
				if ($i > 0) $lineString .= $this->csvConfig['seperator'];
				$lineString .= $this->getFieldValue( array_key_exists($this->currentField['name'], $values) ? $values[$this->currentField['name']] : null );
			}
			
			$this->writeLine($lineString);
		}
		
		return $lineString;
	}
	
	
	// Returns the actual computed, filled and alignes field value
	function getFieldValue($val){
					
		if ($val === null || $val === '') $val = $this->getFieldDefinitionValue('default');
		
			
		// preprocessing
		switch ($this->getFieldDefinitionValue('datatype')) {
			case 'fixed4dot':
				
				if ($val == 0) {
					$val = '0.0000'; // we have to do this, because 0 * 100 is still 0
				} else {
					$full = intval($val) . ''; //Math.floor will not only cut fractional part in case of negative number
					$fractional = round(intval($val) * 10000) . '';
					$fractional = substr($fractional, -4);
					$val = $full . '.' . $fractional;
				}
			
				break;
			default:
				$val = $val.'';
				break;
			case 'text':
				$val = $val !== '' ? $this->csvConfig['textFormatSign'] . $val . $this->csvConfig['textFormatSign'] : '';
				break;
			case 'date':
				$val = $val !== '' ? date($this->getFieldDefinitionValue('format'), intval($val)) : '';
				break;
			case 'enum':
				$enumVals =  $this->getFieldDefinitionValue('enumValues');
				if (array_key_exists($val, $enumVals)) {
					$val = $enumVals[$val];
				}
				$val = $val.'';
				break;
		}
		
		
		return $val;
		
	}
	
	// to get the actual value or the default
	function getFieldDefinitionValue($fieldId) {
		if (! $fieldId) return '';
		return (! array_key_exists($fieldId, $this->currentField)) ? $this->fieldDefaults[$fieldId] : $this->currentField[$fieldId];
	}
	
	function parseInput($val, $fieldId, $definitionName = 'default') {
		$this->currentDefinition = $this->fieldDefinitions[$definitionName]; // set the corresponding field
		
		if (array_key_exists($fieldId, $this->definitionLabelMapping[$definitionName])) {
			
			$this->currentField = $this->currentDefinition[$this->definitionLabelMapping[$definitionName][$fieldId]];
			
			switch ($this->getFieldDefinitionValue('datatype')) {
				default:	
					$val = trim($val);
					break;
				case 'mg-csv-def';
					$vals = explode(',', $val);
					$val = array();
					for ($i = 0; $i < count($vals); $i++) {
						$val[] = trim($vals[$i]);
					}
					break;
				case 'date';
					$val = trim(str_replace(' ', '', $val)); // clean the input
					
					if ($val) {
						$format = $this->getFieldDefinitionValue('input-format') ? $this->getFieldDefinitionValue('input-format') : $this->getFieldDefinitionValue('format');
						$date = date_create_from_format($format, $val);
						if ($date) {
							$val = $date->getTimestamp();
						} else {
							$val = null;
							$this->io->error('Could not parse ' . $fieldId . ' with value ' . $val . ' to the format ' . $format . '.');
						}
					} else {
						$val = null;
					}
					break;
				case 'Boolean':
					if (strtolower($val) == 'ja') $val = 1;
					if (strtolower($val) == 'nein') $val = 0; 
					break;
				case 'enum':
					
					$enumVals = $this->getFieldDefinitionValue('enumValues');
					if (array_key_exists($val, $enumVals)) {
						$val = $enumVals[$val];
					} 
					break;
			}
		} else {
			
			$this->io->error($fieldId . ' does not exist in writer definition.');
		}
		
		return $val;
	}
	
	function close() {
		fclose($this->file);
	}

}

?>