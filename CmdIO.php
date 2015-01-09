<?php
	/* ----------------------------------
	 * A Class to handle Comandline output and input
	 * ---------------------------------- */
	class CmdIO{
		
		var $writeFileHandler = null; //
		var $writeMode = 0; // 0 > only to screen, 2 > only to file, 1 > screen & file
		
		function __construct()  {
		}
		
		function wait($message = 'press enter to continue') { return $this->readStdInn($message);}
		function read($message = 'press enter to continue') { return $this->readStdInn($message);}
		function readStdInn($message = 'press enter to continue'){
			$this->cmd_print($message.': ', false);			
			$stdin = fopen('php://stdin', 'r');
			$input = fgets($stdin, 255);
			fclose($stdin);
		
			$c_input = str_replace(array("\n", "\r"), "", $input);
			return $c_input;
		}
		
		/* -----------------------------------------
		 * Function to print something to the cmd
		 *
		 * @text	-	the text
		 * @nl		-	boolean, if the text should be in a new line
		 * ----------------------------------------- */
		function cmd_print($text = '', $nl = true, $mode = 0){
			$defLineStart = "> ";
			$sep = "------------------------------------------------------------------------------\n";
			$line = '';
			switch ($mode){
				default:
					$line = $text;
					break;
				case 1:
					$line = "\n".$sep;
					$line .= "| ".$text."\n";
					$line .= $sep;
					break;
				case 2:
					$line = $sep;
					$line .= $defLineStart.$text."\n";
					break;
				case 3:
					$line = $defLineStart.$text."\n";
					$line .= $sep;
					break;
			}
			
			if ($nl) $line .= "\n";
			
			if($this->writeMode < 2 ) {
				echo $line;
			}
			
			if($this->writeMode > 0) {
				fwrite($this->writeFileHandler, $line);
			}
			
			
		}
		
		/* -----------------------------------------
		 * Function to print something to the cmd
		 *
		 * @text	-	the text
		 * @nl		-	boolean, if the text should be in a new line
		 * ----------------------------------------- */
		function out($text = '', $nl = true, $mode = 0) {
			$this->cmd_print($text, $nl, $mode);
		}
		
		function fatal($text, $location = '?'){
			$this->cmd_print('> [FATAL ERROR] in '.$location.': '.$text);
			die();
		}
		
		function error($text, $location = '?') {
			$this->cmd_error($text, $location);
		}
		
		function cmd_error($text, $location = '?') {
			$this->cmd_print('> [ERROR] in '.$location.': '.$text);
		}
		
		function warn($text, $location = '?') {
			$this->cmd_print('> [WARNING] in '.$location.': '.$text);
		}
		
		// 0 > only to screen, 2 > only to file, 1 > screen & file
		function setWriteMode($mode, $filepath = null){
			if (is_int($mode)){
				$this->writeMode = $mode;
			} else {
				switch($mode){
					default:
						$this->writeMode = 0;
						break;
					case 'screen & file':
						$this->writeMode = 1;
						break;
					case 'file':
						$this->writeMode = 2;
						break;
				}	
			}
			
			// close the current handler, if one is present
			if ($this->writeFileHandler) fclose($this->writeFileHandler);
			
			if ($filepath && $this->writeMode > 0) {
				$this->writeFileHandler = fopen($filepath, 'w');
			}
		}
		
	}
?>