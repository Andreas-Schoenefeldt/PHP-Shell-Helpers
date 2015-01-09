<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Debug/libDebug.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'CodeControlWrapper.php');


/* -----------------------------------------------------------------------
 * A function to Wrapp a CodeControl like git or svn
 * ----------------------------------------------------------------------- */
class SvnWrapper extends CodeControlWrapper {
	
	function update(){
		$this->execute('svn cleanup');
		return $this->execute('svn up');
	}
	
	function status($short = false){
		$command = 'svn st';
		$this->execute($command);
	}
	
	function version(){
		$this->execute("svn --version");
	}
	
	function commit($message, $addAll, $files = array()){
		// add will print a status afterwards
		if (! $addAll && count($files) > 0) {
			$this->io->out("\n> Status of the commit:");
			$this->add($files);
		}
		
		$this->io->out("\n\n> Updating Repository...");
		if ($this->update() == 0) {
			
			$command = 'svn commit -m "' . $message . '"';
			
			$this->io->out("\n> Uploading to repository server...");
			$this->execute($command);
			
			parent::commit($message, $addAll);
			$this->io->out("\n\n> ---------------------------------------");
			$this->io->out("> Local status:\n");
			$this->status();
			$this->io->out("");
		}
	}
	
	function add($files){
		if (count($files) == 0) { // add . is default
			$files[] = '*';
		}
		
		$command = 'svn add ' . implode(' ', $files);
		
		$this->execute($command);
		$this->status(true);
	}
	
	function remove($files){
		$command = 'svn rm --force ' . implode(' ', $files);
		$this->execute($command);
		$this->status(true);
	}
	
	function diff(){
		$this->execute("svn diff");
	}
	
	function log($args) {
		$this->execute("svn log -v --limit 10");
	}
	
}