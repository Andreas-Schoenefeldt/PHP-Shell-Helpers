<?php

require_once(str_replace('//','/', __DIR__ .'/') .'../Debug/libDebug.php');
require_once(str_replace('//','/', __DIR__ .'/') .'../CmdIO.php');


/* -----------------------------------------------------------------------
 * A function to Wrapp a CodeControl like git or svn
 * ----------------------------------------------------------------------- */
class CodeControlWrapper {

    protected CmdIO $io;
	protected array $emph_config = [
		'token' => 'andreas',
		'url' => 'https://time2.store.emphasize.de/?topic=[token]&u=githook'
    ];
	
	/* --------------------
	 * Constructor
	 */
	function __construct()  {
		$this->io = new CmdIO();
	}
	
	function execute($command, $hideCommand = false) {
        if (!$hideCommand) {
		    $this->io->out(' | ' . $command);
        }
		$lastline = system($command, $retval);
		return $retval;
	}
	
	function addComment($message){
		$time = new DateTime();
		$this->io->out("\n> Adding comment to " . $this->emph_config['url'] . " at " . $time->format('Y-m-d H:i:s'));
		
		$url = str_replace('[token]', $this->emph_config['token'], $this->emph_config['url']);
		
		$data = [["s" => $time->getTimestamp() * 1000, "i" => $message]];
		$command = 'curl -d \'' . json_encode($data) . '\' ' . $url;
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
	
	function feature($name) {
		throw new Exception('Not Implemented');
	}
	
	function release($ident, $message) {
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