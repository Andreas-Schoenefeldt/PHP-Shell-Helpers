<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'Fileparser.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'PseudoCSS.php');


/* -----------------------------------------------------------------------
 * 
 * ----------------------------------------------------------------------- */
class CSSFileparser extends Fileparser {
	
	private $globalIdents = array(); // holds the combined amount of existing idents in the code - array(classes => array(), ids=> array(), ids_dynamic() => array(), classes_dynamic => array())
	
	private $unusedNodes = array();
	private $unusedIdents = array('classes' => array(), 'ids' => array());
	
	/* --------------------
	 * Constructor
	 *
	 * @param String $structureInit	- 
	 *
	 * array(
	 *		"parsingRegex" => array( , , ),			Array of Regular expressions for finding translateble parts in the file
	 *		"forcedSingleNodes" => array(, , , ),	Nodes which will be handled as single nodes, no matter a / was found or not
	 *		"textNodes" => array( , , ) 			Nodes which content will be inserted as text. No further parsing is going on
	 *		"ignoreAttributeNodes" => array( , , )  Nodes where never an attribute will be translated
	 * )
	 * 
	 * @param String $filename		- The absolute or relative Path to the file
	 */
	function __construct($file, $structureInit, $mode, $environment)  {
		
		parent::__construct($file, $structureInit, $mode, $environment, 'PseudoCSS');
		
	}
	
	function setGlobalIdents($globalIdents){
		$this->globalIdents = $globalIdents;
	}
	
	function setClasses($classes) {
		$this->globalIdents['classes'] = $classes;
	}
	
	function setIds($ids) {
		$this->globalIdents['ids'] = $ids;
	}
	
	/**
	 *	Function to verify, weather the given rules in this file do realy target real html objects. Will veryfy by used classes and ids in the rules;
	 */
	function checkRules(){
		$rules = $this->fileNodes->doc->find('rule');
		
		for ($i = 0; $i < count($rules); $i++) {
			$rule = $rules[$i];
			
			if ($this->hasBody($rule)) {
			
				$classes = $rule->find('class');
				for ($k = 0; $k < count($classes); $k++) {
					if (! $this->isUsed('classes', $classes[$k]->getText(), $classes[$k]->lineNumber)) {
						$this->markUnused($classes[$k]->getOffsetParent('identifier, identifier-removed'));
					}
				}
				
				$ids = $rule->find('id');
				for ($k = 0; $k < count($ids); $k++) {
					if (! $this->isUsed('ids', $ids[$k]->getText(), $ids[$k]->lineNumber)) {
						$this->markUnused($ids[$k]->getOffsetParent('identifier, identifier-removed'));
					}
				}
			} else {
				
				$this->io->out('> [INFO] Removing empty rule in line ' . $rule->lineNumber . ': ' . str_replace("\n", '', $this->toCSS($rule)));
				$rule->remove(); // do not enter empty rules
				$this->fileChanged = true;
			}
			
		}
		
		// find out, if a medi query is now empty or empty at all
		$this->removEmptyMediaQuerys();
		
		$remC = (count($this->unusedIdents['classes']) > 0);
		$remI = (count($this->unusedIdents['ids']) > 0);
		
		if ($remC || $remI) {
			
			$this->io->out("\n> The following classes and ids are not used in your codebase. If you are not convinient with this add the classes or ids you want to keep save to the propertie include_css_classes in your .translation.config");
			
			foreach ($this->unusedIdents as $sub => $map) {
				if (count($map) > 0) {
					$this->io->out("\n> $sub:\n");
					foreach ($map as $key => $lines) {
						$this->io->out("  $key | lines " . implode(', ', $lines));
					}
				}
			}
			
			if ($this->io->read("Do you want to optimize your css file through deleting this rules? (y/N)") == 'y') {
				for ($i = 0; $i < count($this->unusedNodes); $i++) {
					$this->fileChanged = true;
					$node = $this->unusedNodes[$i];
					$node->remove();
					$this->io->out('> [INFO] Removing unused css node in line ' . $node->lineNumber . ': ' . str_replace("\n", '', $this->toCSS($node)));
				}
				
				$this->removEmptyMediaQuerys();
			}
		}
	}
	
	function removEmptyMediaQuerys(){
		$querys = $this->fileNodes->doc->find('media-query');
		for ($i = 0; $i < count($querys); $i++) {
			$query = $querys[$i];
			if (count($query->find('rule')) == 0) {
				$this->io->out('> [INFO] Removing empty query in line ' . $query->lineNumber . ': ' . str_replace("\n", '', $this->toCSS($query)));
				$query->remove();
				$this->fileChanged = true;
			}
		}
	}
	
	function hasBody($rule){
		$names = $rule->find('name');
		return (count($names) > 0);
	}
	
	function isUsed($sub, $ident, $lineNumber) {
		if (! array_key_exists($ident, $this->globalIdents[$sub])) {
			// it is not found in the direct classes - try the dynamic ones
			$dynamics = $this->globalIdents[$sub . '_dynamic'];
			$found = false;
			foreach ($dynamics as $regex => $count){
				if (preg_match($regex, $ident)){
					$found = true;
					break;
				}
			}
			
			if (! $found) {
			
				if (! array_key_exists($ident, $this->unusedIdents[$sub])) {
					$this->unusedIdents[$sub][$ident] = array();
				}
				
				$this->unusedIdents[$sub][$ident][] = $lineNumber;
				return false;
			}
		};
		return true;
	}
	
	
	/**
	 *
	 *	@param XMLNode $identifier 	-	The unused identifieer. If it is the only one the whole rule will be added
	 */
	function markUnused($identifier) {
		if (count($identifier->getParent()->find('identifier')) > 1) {
			// $this->io->out('> [INFO] Unused Identify in line ' . $identifier->lineNumber . ': ' . $this->toCSS($identifier));
			$identifier->nodeName = 'identifier-removed';
			$this->unusedNodes[] = $identifier;
		} else {
			$rule = $identifier->getOffsetParent('rule');
			// $this->io->out('> [INFO] Unused CSS Rule in line ' . $rule->lineNumber . ': ' . $this->toCSS($rule));
			$this->unusedNodes[] = $rule;
		}
	}
	
	
	/**
	 * Main Function of the Fileparser
	 *
	 */
	function extractData(){
		
		$obj = $this;
		
		$this->fileNodes->crawlAllNodes(function($node) use ($obj) {
			
			if (in_array($obj::$EXTRACT_MODES['CSS'], $obj->extractMode)) {
				$obj->extractCSS($node);
			}
			
			return $obj->processChildren($node); // process the children ?
			
		} );
		
		return $this->getExtractionData();
	
	}
	
	
	function extractCSS($node){
		
		switch ($node->nodeName) {
			case 'class':
				$this->addFind('class', $node->getText());
				break;
			case 'id':
				$this->addFind('id', $node->getText());
				break;
		}
		
	}
	
	function addFind($type, $value) {
		$key = 'found_' . $type;
		$file = $this->file->fileSystemLocation;
		
		$this->addData(self::$EXTRACT_MODES['CSS'], $key, $file, $value);
	}
	
	
	
	function getFileString($rootNode) {
		return $this->toCSS($rootNode);
	}
	
	
	/**
	 *	Main Printing Function of a css rule
	 *
	 *
	 *
	 *
	 */
	function toCSS($node, $childIndex = 0, $offset = '') {
		$result = '';
		$addOffset = false;
		if ($node->nodeType != 'text') {
			
			switch ($node->nodeName) {
				default:
					
					d($node);
					$node->printNode($this->io);
					throw new Exception('Don\'t know, how to print ' . $node->nodeName . ' as css');
					break;
				case 'property':
				case 'name':
				case 'value':
				case 'document':
				case 'combined':
				case 'tag':
				case 'rule':
					break;
				case 'head':
					$result .= $offset;
					break;
				case 'body':
					$result .= '{';
					break;
				case 'identifier-removed':
				case 'identifier':
					if ($childIndex > 0) {$result .= ', ';}
					break;
				case 'class':
					$result .= '.';
					break;
				case 'id':
					$result .= '#';
					break;
				case 'at':
					$result .= '@';
					break;
				case 'pseudo':
					$result .= ':';
					break;
				case 'has-child':
					$result .= '>';
					break;
				case '/*':
					$result .= $offset.'/* ';
					if ($node->getChildCount() > 1) {
						$result .= "\n";
						$addOffset = true;
					}
					break;
				case '':
					$result .= "\n"; // the whitespace node
					break;
				case 'media-query':
					$result .= $offset."@". $node->attr('type'). " " . $node->attr('media') . ' {'. "\n";
					$addOffset = true;
					break;
			}
			
			switch ($node->nodeName) {
				default:
					for ($i = 0; $i < $node->getChildCount(); $i++){
						$result .= $this->toCSS($node->getChild($i), $i, (($addOffset)? $offset . "\t" : $offset));
					}
					break;
				case '/*':
					for ($i = 0; $i < $node->getChildCount(); $i++){
						if ($node->getChildCount() > 1) $result .= $offset."\t";
						$result .= $this->toCSS($node->getChild($i), $i, $offset);
						if ($node->getChildCount() > 1) {
							$result .= "\n";
						} else {
							$result .= " ";
						}
					}
					break;
				case 'identifier-removed':
				case 'identifier':
					$first = true;
					for ($i = 0; $i < $node->getChildCount(); $i++) {
						if (! $first) {
							$result .= ' ';
						} 
						$result .= $this->toCSS($node->getChild($i), $i, (($addOffset)? $offset . "\t" : $offset));
						$first = false;
					}
					break;
			}
			
			switch ($node->nodeName) {
				case 'head':
					$result .= ' ';
					break;
				case 'body':
					$result .= '}';
					break;
				case 'rule':
					$result .= "\n";
					break;
				case '/*':
					$result .= "*/\n";
					break;
				case 'media-query':
					$result .= $offset."}\n";
					break;
				case 'property':
					$result .= ";";
					break;
				case 'name':
					$result .= ": ";
					break;
			}
		} else {
			$result .= $node->getText();
		}
		
		return $result;
	}
	
	

}

?>