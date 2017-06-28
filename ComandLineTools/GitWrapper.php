<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Debug/libDebug.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'CodeControlWrapper.php');


/* -----------------------------------------------------------------------
 * A function to Wrapp a the git CodeControl
 * ----------------------------------------------------------------------- */
class GitWrapper extends CodeControlWrapper {
	
	function status($short = false, $branch = false){
		if ($branch) $this->execute('git branch'); // show the brnaches
		
		$command = 'git status';
		if ($short) $command .= ' -s';
		$this->execute($command);
	}
	
	function update(){
		$this->execute('git pull');
	}
	
	function version(){
		$this->execute('git --version');
	}
	
	function commit($message, $addAll, $files = array()){
		$this->io->out("\n> Status of the commit:");		
		// add will print a status afterwards
		if (! $addAll) {
			if (count($files)) $this->add($files);
		} else {
			$this->add(array());
		}
		
		$command = 'git commit -m "' . $message . '"';
		
		$this->io->out("\n> Commiting local changes...");
		if ($this->execute($command) == 0) {
			
			$this->io->out("\n\n> Updating Repository...");
			$this->update();
			
			$this->io->out("\n> Uploading to repository server...");
			$this->execute('git push');
			
			parent::commit($message, $addAll);
			$this->io->out("\n\n> ---------------------------------------");
			$this->io->out("> Local status:\n");
			$this->status();
			$this->io->out("");
		}
	}
	
	function feature($name) {
		$this->checkout(array(), 'feature/' . $name);
	}
	
	function release($ident, $message) {
		$this->io->out("\n> Exisiting Releases:");		
		$this->execute('git tag');
		$this->io->out("");
		
		$this->status();
		
		if ($this->io->confirm('Are you sure you want to create the tag ' . $ident . '? All uncommitted files will be committed if needed.')){
			$this->commit($message, true);
			
			$this->execute('git tag -a ' . $ident . ' -m "' . $message . '"');
			
			$this->execute('git push origin ' . $ident);
			
			$this->io->out("\n> Release $ident was created and pushed to the server");
		}
		$this->io->out("");
	}
	
	function checkout($sources, $branch) {
		
		$command = 'git checkout ';
        
		if (count($sources) == 0) {
			if ($branch) $command .= '-b ' . $branch;
		} else {
			$command .= join(' ', $sources);
		}
		
		$this->execute($command);
		
		if (count($sources) == 1 && count(explode('/', $sources[0])) == 1) {
			// this is a branch, fetch the head
			$command = 'git pull origin ' . $sources[0];
			$this->execute($command);			
		} else if ($branch) { // this is a new branch
			$command = 'git push --set-upstream origin ' . $branch; // push branch to remote repository
			$this->execute($command);	
		} else {
			$this->execute("git pull");
		}
	}
	
	function add($files){
		if (count($files) == 0) { // add . is default
			$files[] = '--all'; // the add all command
		}
		
		$command = 'git add ' . implode(' ', $files);
		
		$this->execute($command);
		$this->status(true);
	}
	
	function diff(){
		$this->execute('git diff');
	}
	
	function remove($files){
		$this->execute('git rm -f -r ' . implode(' ', $files));
	}
	
	function merge($branch){
		$this->update();
		if ($this->execute('git merge ' . $branch) == 0) {
			// uploading to remote repository
			$this->execute('git push');
		}
	}
	
	function log($filters) {
		$this->execute('git log --pretty=format:"%h %cn %cr: %s" -60');
		echo ("\n");
	}
}