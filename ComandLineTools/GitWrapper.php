<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Debug/libDebug.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'CodeControlWrapper.php');


/* -----------------------------------------------------------------------
 * A function to Wrapp a the git CodeControl
 * ----------------------------------------------------------------------- */
class GitWrapper extends CodeControlWrapper {

    public const SYSTEM_SCM = 'scm'; // SCM-Manager - allows pull requests only with addiotnal plugins
    public const SYSTEM_GITHUB = 'github';
    public const SYSTEM_GITLAB = 'gitlab';
    public const SYSTEM_GITEA = 'gitea';
    public const SYSTEM_BITBUCKET = 'bitbucket';
	
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

    protected function getSystem(): string {
        $remoteUrl = $this->shell('git remote get-url origin');
        $host = $this->getRemoteHost($remoteUrl);

        switch ($host) {
            case 'github.com':
                return self::SYSTEM_GITHUB;
            case 'bitbucket.org':
                return self::SYSTEM_BITBUCKET;        
        }

        // we could not identify it jet, do some educated guesses

        if (str_starts_with($host, 'gitea.')) {
            return self::SYSTEM_GITEA;
        } else if (str_starts_with($host, 'gitlab.')) {
            return self::SYSTEM_GITLAB;
        } else if (str_contains($remoteUrl, '/scm/')) {
            return self::SYSTEM_SCM;
        } 

        // the defauld, just return the host
        return $host;
    }

    protected function getRemoteHost($url = null): string {
        $remoteUrl = $url ?: $this->shell('git remote get-url origin');

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

    public function mergeRequest(string|bool|null $targetBranch = null): void {

        if (is_bool($targetBranch) || !$targetBranch) {
            $targetBranch = $this->shell('git config init.defaultBranch');
            if (!$targetBranch) {
                $this->io->error('Could not determine the default branch. Please set it with `git config --local init.defaultBranch <branch>` for this repository.');
                return;
            }

            $this->io->out('The target "'. $targetBranch . '" branch was taken from the git configuration. Change it with `git config --local init.defaultBranch <branch>` for this repository.');
            $this->io->out('');
        }

        $currentBranch = $this->shell('git branch --show-current');
        $parts = explode('/', $currentBranch);
        $name = str_replace(['-', '_'], ' ', array_pop($parts));
        $systen = $this->getSystem();

        switch ($systen) {
            case self::SYSTEM_GITHUB:
                if ($this->io->confirm("Create a pull request from $currentBranch -> $targetBranch?")) {
                    $res = $this->shell("gh pr create --base $targetBranch --head $currentBranch --title \"$name\" --body=\"\"");

                    if (str_starts_with($res, 'http')) {
                        $this->io->out("Pull request created successfully: $res/files?diff=split&w=1");
                    } else {
                        $this->io->out($res);
                    }
                }
                break;
            case self::SYSTEM_GITEA:
                $remoteUrl = $this->shell('git remote get-url origin');
                $regex = '/[\/:]([^\/]+)\/([^\/\s]+?)(\.git)?$/';
                if (preg_match($regex, $remoteUrl, $matches)) {
                    $workspace = $matches[1];
                    $repoSlug = $matches[2];

                    if ($this->io->confirm("Create a pull request from $currentBranch -> $targetBranch?")) {
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
                    $this->io->out("Could not parse gitea repository URL: $remoteUrl with $regex");
                }
                break;
            case self::SYSTEM_BITBUCKET:
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
            case self::SYSTEM_GITLAB:
                if ($this->io->confirm("Create a merge request from $currentBranch -> $targetBranch?")) {

                    $command = "glab mr create --target-branch $targetBranch --source-branch $currentBranch --title=\"#$name\" -y --description=\"$(cat $(git rev-parse --show-toplevel)/.gitlab/merge_request_templates/default.md)\" --remove-source-branch";

                    $this->io->out("Executing: $command");
                    $res = $this->shell($command);

                    $lines = preg_split('/\r\n|\r|\n/', $res);
                    $last = $lines ? rtrim(end($lines)) : '';

                    if (str_starts_with($last, 'http')) {
                        $this->io->out("Merge request created successfully: $last");
                    } else {
                        $this->io->out($res);
                    }
                }
                break;
            case self::SYSTEM_SCM:
                $remoteUrl = $this->shell('git remote get-url origin');

                if (preg_match('/\/scm\/repo\/([^\/]+)\/([^\/\s]+?)(\.git)?$/', $remoteUrl, $matches)) {
                    $namespace = $matches[1];
                    $repoName = $matches[2];

                    if ($this->io->confirm("Create a merge request from $currentBranch -> $targetBranch?")) {

                        $host = $this->getRemoteHost($remoteUrl);

                        $this->io->out("Retrieving credentials for $host");
                        $output = $this->shell("powershell -Command \"\\\"protocol=https`nhost=$host`n`n\\\" | git credential fill\"");

                        preg_match('/^username=(.+)$/m', $output, $userMatch);
                        preg_match('/^password=(.+)$/m', $output, $passMatch);

                        $username = trim($userMatch[1] ?? '');
                        $password = trim($passMatch[1] ?? '');

                        $makeApiRequest = function ($path, $method = 'GET', ?array $data = null, array $headers = []) use ($host, $username, $password) {
                        
                            $finalHeaders = array_replace(['Content-Type: application/json'], $headers);
                            $finalUrl = "https://$host$path";
                           
                            $curlOptions = [
                                CURLOPT_URL => $finalUrl,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_USERPWD => "$username:$password",
                                CURLOPT_SSL_VERIFYPEER => false,
                                CURLOPT_SSL_VERIFYHOST => false,
                                CURLOPT_HTTPHEADER => $finalHeaders,
                            ];
                        
                            if ($method == 'POST') {
                                $curlOptions[CURLOPT_POST] = true;
                                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                            }

                            $ch = curl_init();
                            curl_setopt_array($ch, $curlOptions);

                            $response = curl_exec($ch);
                            $curlError = curl_error($ch);

                            $data = null;

                            if ($curlError) {
                                $this->io->out("Could not load $path: $curlError");
                            } else if ($response) {
                                $data = json_decode($response, true);

                                if (array_key_exists('errorCode', $data)) {
                                    $this->io->out("Received error {$data['errorCode']}: {$data['message']}");
                                    $data = null;
                                }

                            }

                            return $data;
                        };

                        $data = $makeApiRequest("/scm/api/v2/pull-requests/$namespace/$repoName/template");

                        if ($data) {
                            $request = [
                                "title" => $name,
                                "source" => $currentBranch,
                                "target" => $targetBranch,
                                // "reviewers" => array_map(function ($r) { return ['id' => $r['id']];}, $data['defaultReviewers'] ?? []),
                                "shouldDeleteSourceBranch" => true
                            ];

                            $makeApiRequest("/scm/api/v2/pull-requests/$namespace/$repoName", 'POST', $request, ['Content-Type: application/vnd.scmm-pullRequest+json;v=2']);

                            // load the available merge requests
                            $availablePRs = $makeApiRequest("/scm/api/v2/pull-requests/$namespace/$repoName");

                            if ($availablePRs) {
                                $pr = array_values(array_filter($availablePRs['_embedded']['pullRequests'], fn($p) => $p['source'] == $currentBranch && $p['target'] == $targetBranch))[0] ?? null;

                                if ($pr) {
                                    $this->io->out("Pull request created successfully: https://$host/scm/repo/$namespace/$repoName/pull-request/{$pr['id']}/diff/");
                                } else {
                                    $this->io->out("Pull request could not be created");
                                }
                            }
                        }
                    }
                } else {
                    $this->io->out("Could not parse SCM-Manager repository URL: $remoteUrl");
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