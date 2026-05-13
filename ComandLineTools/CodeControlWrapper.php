<?php

require_once(str_replace('//','/', __DIR__ .'/') .'../Debug/libDebug.php');
require_once(str_replace('//','/', __DIR__ .'/') .'../CmdIO.php');


/* -----------------------------------------------------------------------
 * A function to Wrapp a CodeControl like git or svn
 * ----------------------------------------------------------------------- */
class CodeControlWrapper {

  protected CmdIO $io;
	protected array $emph_config = [
		'token' => 'UNDEFINED',
		'url' => 'https://time2.emphasize.de/storage/?topic=[token]&u=githook'
  ];
	
	/* --------------------
	 * Constructor
	 */
	public function __construct()  {
		$this->io = new CmdIO();
		$this->emph_config['token'] = getenv('TIME2_EMPHASIZE_TOKEN');

		if (! $this->emph_config['token']) {
			throw new RuntimeException('TIME2_EMPHASIZE_TOKEN environment variable is missing.');
		}
	}

	public function sanitizeMessage($message)
	{
			// fixing too common typos..
			return str_replace('teh', 'the', trim($message));
	}

	protected function shell($command): string
	{
			return trim(shell_exec($command) ?? '');
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
    $data = json_encode([["s" => $time->getTimestamp() * 1000, "i" => $message]], JSON_THROW_ON_ERROR);

		$url = str_replace('[token]', $this->emph_config['token'], $this->emph_config['url']);

		$this->io->out("\n> Adding comment to " . $url . " at " . $time->format('Y-m-d H:i:s'));
		$this->io->out("> $data");

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$response = curl_exec($ch);

		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($status < 200 || $status >= 300) {
			$error = curl_error($ch);
			$this->io->out("> ERROR $status: $response ($error)");
			$this->io->out(">");
			$this->io->out("> Please add the message manually:");
			$this->io->out("> ----------------------------------------------");
			$this->io->out("> $message");
			$this->io->out("> ----------------------------------------------");
		} else {
			$this->io->out("> OK $status: $response");
		}
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

    public function mergeRequest(string|bool|null $targetBranch = null) : void
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