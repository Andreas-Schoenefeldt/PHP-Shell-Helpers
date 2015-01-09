<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../CmdIO.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Debug/libDebug.php');

define('EVENT_UNDEFINED', 'ev_undefined');

// the extendable reader event
class ReaderEvent {
	var $lineNumber;
	var $contentObject;
	var $type;
	
	function __construct($lineNumber, $content, $type){
		$this->lineNumber = $lineNumber;
		$this->contentObject = $content;
		$this->type = $type ? $type : EVENT_UNDEFINED;
	}
	
	// returns the event type
	function getEventType(){
		return $this->type;
	}
	
	// returns the content object
	function getContentObject(){
		return $this->contentObject;
	}
}

// the stream reader class, returns ReaderEvent Objects
class StreamReader {
	var $io;

	var $currentLineNumber;
	var $filepath;
	var $filename;
	
	var $eventHeap = array();
	
	// var
	var $fp; // the filepointer
	
	function __construct($filename){
		$this->io = new CmdIO();
		
		$this->filepath = $filename;
		$fileparts = explode('/', $filename);
		$this->filename = $fileparts[count($fileparts) - 1];
		
		if (file_exists($filename)){
			$this->fp = fopen($filename, "r");
			
			$this->currentLineNumber = 0; // linecounter
		}
	}
	
	// gets a line
	function getLine(){
		
		while ($line = fgets($this->fp, 4096)) {
			$this->currentLineNumber++;
			$str = trim($line);
				
			return $str;
		}
		
		return false;
		
	}
	
	// override this function to create your custom events
	function parseEvent(){
		$lineStr = $this->getLine();
		
		if ($lineStr === false) return null;
		
		$this->eventHeap[] = new ReaderEvent($this->currentLineNumber, $lineStr);
	}
	
	// gets the next event
	function getNextEvent(){
		if ( count($this->eventHeap) == 0 ) $this->parseEvent();
		return array_shift($this->eventHeap);
	}
	
}


?>