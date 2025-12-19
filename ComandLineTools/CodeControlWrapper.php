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
	public function __construct()  {
		$this->io = new CmdIO();
	}

    public function sanitizeMessage($message)
    {
        // fixing too common typos..
        return str_replace('teh', 'the', trim($message));
    }

    protected function shell($command): string
    {
        return trim(shell_exec($command));
    }
	
	protected function execute($command, $hideCommand = false) {
        if (!$hideCommand) {
		    $this->io->out(' | ' . $command);
        }
		system($command, $retval);
		return $retval;
	}
	
	protected function addComment($message){
		$time = new DateTime();
		$this->io->out("\n> Adding comment to " . $this->emph_config['url'] . " at " . $time->format('Y-m-d H:i:s'));
		
		$url = str_replace('[token]', $this->emph_config['token'], $this->emph_config['url']);
		
		$data = [["s" => $time->getTimestamp() * 1000, "i" => $message]];
		$command = 'curl -d \'' . json_encode($data) . '\' ' . '\'' . $url . '\'';
		// d($command);
		$this->execute($command);
	}


    public function status(){
		throw new \RuntimeException('Not Implemented');
	}

    public function update(){
		throw new \RuntimeException('Not Implemented');
	}

    public function remove($files){
		throw new \RuntimeException('Not Implemented');
	}

    public function merge($branch){
		throw new \RuntimeException('Not Implemented');
	}

    public function mergeRequest(string $targetBranch) : void
    {
        throw new \RuntimeException('Not Implemented');
    }

    public function diff(){
		throw new \RuntimeException('Not Implemented');
	}
	
	/**
	 *
	 * @param Array $sources - the source repositorys used for checkout
	 */
    public function checkout($sources, $branch) {
		throw new \RuntimeException('Not Implemented');
	}

    public function feature($name) {
		throw new \RuntimeException('Not Implemented');
	}

    public function release($ident, $message) {
		throw new \RuntimeException('Not Implemented');
	}

    public function add($files){
		throw new \RuntimeException('Not Implemented');
	}

    public function log($args) {
		throw new \RuntimeException('Not Implemented');
	}
	
	function version() {
		throw new \RuntimeException('Not Implemented');
	}
	
	public function commit($message, $addAll, $files = array()): void
    {
		if ($message) {
			$this->addComment($message);
		} else {
			$this->io->fatal('Please add a message for your commit.');
		}
	}
}