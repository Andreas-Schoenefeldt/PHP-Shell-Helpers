<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Debug/libDebug.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'../CmdIO.php');


/* -----------------------------------------------------------------------
 * A function to Wrapp a CodeControl like git or svn
 * ----------------------------------------------------------------------- */
class CodeControlWrapper {
	
	var $io;
	var $emph_config = array(
		'token' => '6148lk4g4gk8c0c8wgo0', // jrb52ck0gggsc40sk48k
		'url' => 'http://time.emphasize.de/util/ajax.php'
	);
	
	/* --------------------
	 * Constructor
	 */
	function __construct()  {
		$this->io = new CmdIO();
	}
	
	function execute($command) {
		// $this->io->out($command);
		$lastline = system($command, $retval);
		return $retval;
	}
	
	function addComment($message){
		$time = date('Y-m-d H:i:s');
		$this->io->out("\n> Adding comment to " . $this->emph_config['url'] . " at " . $time);
		$data = 'do=addInfo&token=' . $this->emph_config['token'] . '&info=' . urlencode($message) . '&time=' . urlencode($time);
		$command = 'curl -L "' . $this->emph_config['url'] . '?' . $data . '"';
		// d($command);
		$this->execute($command);
	}
	
	
	function status(){
		throw new Exception('Not Implemented');
	}
	
	function update(){
		throw new Exception('Not Implemented');
	}
	
	function remove($files){
		throw new Exception('Not Implemented');
	}
	
	function merge($branch){
		throw new Exception('Not Implemented');
	}
	
	function diff(){
		throw new Exception('Not Implemented');
	}
	
	/**
	 *
	 * @param Array $sources - the source repositorys used for checkout
	 */
	function checkout($sources, $branch) {
		throw new Exception('Not Implemented');
	}
	
	function add($files){
		throw new Exception('Not Implemented');
	}
	
	function log($args) {
		throw new Exception('Not Implemented');
	}
	
	function version() {
		throw new Exception('Not Implemented');
	}
	
	function commit($message, $addAll, $files = array()){
		if ($message) {
			$this->addComment($message);
		} else {
			$this->io->fatal('Please add a message for your commit.');
		}
	}
}