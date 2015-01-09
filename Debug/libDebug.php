<?php

	/* ---------------------------------
	 * Functions for a better output of php arrays. A pimpt print_r
	 *
	 * --------------------------------- */
	 
	function _dArrayAndObject($var, $align, $offset, $level){
	   $fill = '                                                                                         ';
	   $str = "";
	   
	   if($align){
		  $len = 0;
		  foreach ($var as $k => $v) {
			  $len = (strlen($k) > $len) ? strlen($k) : $len;
		  }
	   }
	   
	   $offAdd = ($align) ? substr($fill, 0, $len) : '  ';
	   
	   foreach ($var as $k => $v) {
		  
		  $name = ($align) ? "[$k]" . substr($fill, 0, $len - strlen($k))  : "[$k]";
		  $str .= "\n" . _d($v, $level, $offset."  ", $name, $align, false);
	   }
	   
	   return $str;
	}
	
	
	function _d($var, $level = 200, $offset = '', $name = false, $align = true, $printOutObject = true){
		$str = $offset;
		
		if ($name !== false) $str .= "$name => ";
		
		if (is_object ($var)){
			$str .= "(Object " . get_class($var) . ")";
			
			if ($printOutObject){
			 $str .= ($level > 0) ? _dArrayAndObject($var, $align, $offset, $level - 1) : '...';
				 
			  $str .= "\n";
			 }
			
		} else if (is_array($var)) {
			if (count($var) == 0) {
				$str .= "Array( )";
			} else {
				$str .= "Array(";
				
				$str .= ($level > 0) ? _dArrayAndObject($var, $align, $offset, $level - 1) . "\n".$offset : '...';
				
				$str .= ")";
				
			}
		} else if (is_bool($var)){
			if ($var) {
				$var = "bool: true";
			} else {
				$var = "bool: false";
			}
			$str .= $var;
		} else if ($var === null) {
		  $str .= '(NULL)';
	   }else {	
			$str .= $var;
		}
		
		return $str;
	 }
	 
	 function debug($var, $level = 200){ d($var, $level); }
	 
	 /**
	  * The function to call
	  */
	 function d($var, $level = 200){
		echo "\n" . _d($var, $level) . "\n";
		
	 }
	 /**  html debug function **/
	 function dh($var, $level = 200) {
		echo '<pre style="background-color: rgba(0,0,0,0.2); border-top: 5px solid #444; border-bottom: 5px solid #444;padding: 14px 14px 18px; box-shadow: 0 0 10px rgba(238,104,230,0.5);">' . htmlentities(_d($var)) . "</pre>";
	 }
 
?>