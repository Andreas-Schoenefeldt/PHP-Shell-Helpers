<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Debug/libDebug.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'CodeControlWrapper.php');


/* -----------------------------------------------------------------------
 * A function to Wrapp a the git CodeControl
 * ----------------------------------------------------------------------- */
class HgWrapper extends CodeControlWrapper {
	
	function status($short = false, $branch = false){
		$this->execute('hg summary');
		$this->execute('hg status');
	}
	
	function update(){
		$this->execute('hg pull');
	}
	
	function version(){
		$this->execute('hg version');
	}
	
	function log($filters) {
		$this->execute('hg log');
		echo ("\n");
	}
}