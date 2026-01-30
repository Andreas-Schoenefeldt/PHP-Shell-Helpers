<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Debug/libDebug.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'CodeControlWrapper.php');


/* -----------------------------------------------------------------------
 * A function to Wrapp a the git CodeControl
 * ----------------------------------------------------------------------- */
class GitWrapper extends CodeControlWrapper {
	
	public function status($short = false, $branch = false){
		if ($branch) $this->execute('git branch'); // show the brnaches
		
		$command = 'git status';
		if ($short) $command .= ' -s';
		$this->execute($command);
        $this->io->out('Current Hash (short): ', false);
        $this->execute('git rev-parse --short HEAD', true);
	}
	
	public function update(){
		return $this->execute('git pull');
	}
	
	public function version(){
		$this->execute('git --version');
	}

    public function commit($message, $addAll, $files = array()): void
    {
		$this->io->out("\n> Updating Repository...");
		if( $this->update() == 0 ) {
			$this->io->out("\n> Status of the commit:");		
			// add will print a status afterwards
			if (! $addAll) {
				if (count($files)) $this->add($files);
			} else {
				$this->add(array());
			}

            $message = parent::sanitizeMessage($message);
		
			$command = 'git commit -m "' . $message . '"';
		
			$this->io->out("\n> Commiting local changes...");
			if ($this->execute($command) == 0) {
			
				$this->io->out("\n> Uploading to repository server...");
				$this->execute('git push');
			
				parent::commit($message, $addAll);
				$this->io->out("\n\n> ---------------------------------------");
				$this->io->out("> Local status:\n");
				$this->status();
				$this->io->out("");
			}
		}
	}
	
	public function feature($name): void
    {
		$this->checkout(array(), 'feature/' . $name);
	}

    public function release($ident, $message) {
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

    public function checkout($sources, $branch) {
		
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

    public function add($files){
		if (count($files) == 0) { // add . is default
			$files[] = '--all'; // the add all command
		}
		
		$command = 'git add ' . implode(' ', $files);
		
		$this->execute($command);
		$this->status(true);
	}

    public function diff(){
		$this->execute('git diff');
	}

    public function remove($files){
		$this->execute('git rm -f -r ' . implode(' ', $files));
	}

    protected function getRemoteHost(): string {
        $remoteUrl = $this->shell('git remote get-url origin');

        // Parse the host from the URL
        // Handle SSH format: git@github.com:user/repo.git
        if (preg_match('/^git@([^:]+):/', $remoteUrl, $matches)) {
            return $matches[1];
        }

        // Handle HTTPS format: https://github.com/user/repo.git
        if (preg_match('/^https?:\/\/([^\/]+)/', $remoteUrl, $matches)) {
            return $matches[1];
        }

        return $remoteUrl; // Return as-is if no pattern matches
    }

    public function mergeRequest(string $targetBranch): void {

        $currentBranch = $this->shell('git branch --show-current');
        $parts = explode('/', $currentBranch);
        $name = array_pop($parts);
        $systen = $this->getRemoteHost();

        switch ($systen) {
            case 'github.com':
                if ($this->io->confirm("Create a pull request from $currentBranch to $targetBranch?")) {
                    $res = $this->shell("gh pr create --base $targetBranch --head $currentBranch --title \"$name\" --body=\"\"");

                    if (str_starts_with($res, 'http')) {
                        $this->io->out("Pull request created successfully: $res/files?diff=split&w=1");
                    } else {
                        $this->io->out($res);
                    }
                }
                break;
            case 'gitea.flowconcept.de':
                $remoteUrl = $this->shell('git remote get-url origin');

                if (preg_match('/' . str_replace('.', '\\.', $systen) . '[\/:]([^\/]+)\/([^\/\s]+?)(\.git)?$/', $remoteUrl, $matches)) {
                    $workspace = $matches[1];
                    $repoSlug = $matches[2];

                    if ($this->io->confirm("Create a pull request from $currentBranch to $targetBranch?")) {
                        $res = $this->shell(" tea pulls create -r $workspace/$repoSlug --base=$targetBranch --head=$currentBranch --title=\"$name\"");

                        $lines = preg_split('/\r\n|\r|\n/', $res);
                        $last = $lines ? rtrim(end($lines)) : '';

                        if (str_starts_with($last, 'http')) {
                            $this->io->out("Pull request created successfully: $last/files?style=unified&whitespace=ignore-all&show-outdated=false");
                        } else {
                            $this->io->out($res);
                        }
                    }
                } else {
                    $this->io->out("Could not parse gitea repository URL: $remoteUrl");
                }
                break;
            case 'bitbucket.org':
                $remoteUrl = $this->shell('git remote get-url origin');

                // Parse Bitbucket workspace and repo slug
                // SSH format: git@bitbucket.org:workspace/repo.git
                // HTTPS format: https://bitbucket.org/workspace/repo.git
                if (preg_match('/bitbucket\.org[\/:]([^\/]+)\/([^\/\s]+?)(\.git)?$/', $remoteUrl, $matches)) {
                    $workspace = $matches[1];
                    $repoSlug = $matches[2];

                    // Bitbucket MR URL format
                    $mrUrl = "https://bitbucket.org/$workspace/$repoSlug/pull-requests/new?source=$currentBranch&dest=$targetBranch&title=" . urlencode($name);

                    $this->io->out("Merge request URL for $currentBranch -> $targetBranch:");
                    $this->io->out($mrUrl);

                    // Optionally open in browser (uncomment if desired)
                    // $this->shell("open '$mrUrl'");
                } else {
                    $this->io->out("Could not parse Bitbucket repository URL: $remoteUrl");
                }
                break;
            default:
                throw new RuntimeException("no handler for $systen jet.");
        }
    }

    public function merge($branch){
		$this->update();
		if ($this->execute('git merge ' . $branch) == 0) {
			// uploading to remote repository
			$this->execute('git push');
		}
	}

    public function log($filters) {
		$this->execute('git log --pretty=format:"%h %cn %cr: %s" -60');
		echo ("\n");
	}
}