<?php

/**
 * Copyright 2015 delight.im <info@delight.im>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Delight\GitScraper;

/** Downloads entire Git repositories from publicly accessible `.git` folders over HTTP */
class GitScraper {

	const HASH_REGEX = '[a-z0-9]{40}';
	const GIT_URL_REGEX = '/^(http|https):\\/\\/(.*?)(?:\\/|$)(.*?)(?:\\/)?(?:\\.git)?(?:\\/)?$/i';
	const HEAD_REF_REGEX = '/^ref: ([a-zA-Z\\/]+)\\s*/';
	const TREE_ENTRIES_REGEX = '/(1)?([0-7]{5}) ([^\x00]+)\x00(.{20})/s';
	const OBJECT_STRUCTURE_REGEX = '/^([a-z]+) ([0-9]+)\x00(.*)/s';

	private $path;
	private $workingDir;
	private $files;

	public function __construct($url) {
		$this->path = self::normalizeGitUrl($url);
		$this->workingDir = array();
		$this->files = array();
	}

	public function fetch() {
		$head = @file_get_contents($this->path.'/HEAD');
		$headRef = self::parseHead($head);

		$ref = @file_get_contents($this->path.'/'.$headRef);
		$refHash = self::parseHash($ref);

		$this->loadObject($refHash);
	}

	private static function normalizeGitUrl($url) {
		$url = trim($url);

		if (preg_match(self::GIT_URL_REGEX, $url, $matches)) {
			$protocol = $matches[1];
			$host = $matches[2];

			$path = $protocol.'://'.$host;
			if (!empty($matches[3])) {
				$path .= '/'.$matches[3];
			}

			$path .= '/.git';

			return $path;
		}
		else {
			throw new \Exception('Invalid URL: '.$url);
		}
	}

	private static function parseHead($head) {
		if (preg_match(self::HEAD_REF_REGEX, $head, $matches)) {
			return $matches[1];
		}
		else {
			throw new \Exception('No head reference found: '.$head);
		}
	}

	private static function parseHash($hash) {
		if (preg_match('/^\\s*('.self::HASH_REGEX.')\\s*$/', $hash, $matches)) {
			return $matches[1];
		}
		else {
			throw new \Exception('Hash could not be parsed: '.$hash);
		}
	}

	private static function createPathFromHash($hash) {
		return substr($hash, 0, 2).'/'.substr($hash, 2);
	}

	private static function decode($data) {
		$decoded = zlib_decode($data);

		if ($decoded === false) {
			throw new \Exception('Cannot decode data: '.base64_encode($data));
		}

		return $decoded;
	}

	private function getWorkingDir($asString = false) {
		if ($asString) {
			if (count($this->workingDir) === 0) {
				return '';
			}
			else {
				return implode('/', $this->workingDir).'/';
			}
		}
		else {
			return $this->workingDir;
		}
	}

	private function extractEntriesFromTree($data) {
		$numMatches = preg_match_all(self::TREE_ENTRIES_REGEX, $data, $matches);

		if ($numMatches !== false) {
			for ($i = 0; $i < $numMatches; $i++) {
				$hash = bin2hex($matches[4][$i]);
				$name = $matches[3][$i];
				$isBlob = ($matches[1][$i] === '1');

				if ($isBlob) {
					$this->files[] = array(
						'hash' => $hash,
						'name' => $this->getWorkingDir(true).$name,
						'mode' => $matches[2][$i]
					);
				}
				else {
					$this->changeWorkingDir($name);
					$this->loadObject($hash);
					$this->changeWorkingDir('../');
				}
			}
		}
	}

	private function changeWorkingDir($dir) {
		if ($dir === '../') {
			array_pop($this->workingDir);
		}
		elseif ($dir === './') {
			// ignore
		}
		else {
			$this->workingDir[] = $dir;
		}
	}

	private function createObjectUrl($hash) {
		return $this->path.'/objects/'.self::createPathFromHash($hash);
	}

	private function loadObject($hash, $mode = null) {
		$obj = @file_get_contents($this->createObjectUrl($hash));

		if (empty($obj)) {
			return null;
		}

		return $this->parseObject($obj);
	}

	private function extractTreeFromCommit($data) {
		$treeHash = self::findTreeHash($data);
		$this->loadObject($treeHash);
	}

	private function parseObject($obj) {
		$str = self::decode($obj);

		if (preg_match(self::OBJECT_STRUCTURE_REGEX, $str, $matches)) {
			$type = $matches[1];
			$length = $matches[2];
			$data = $matches[3];

			if ($type === 'commit') {
				$this->extractTreeFromCommit($data);

				return null;
			}
			elseif ($type === 'tree') {
				$this->extractEntriesFromTree($data);

				return null;
			}
			elseif ($type === 'blob') {
				return $data;
			}
			elseif ($type === 'tag') {
				// TODO

				return null;
			}
			else {
				throw new \Exception('Unknown type: '.$type);
			}
		}
		else {
			throw new \Exception('Cannot parse object');
		}
	}

	private static function findTreeHash($str) {
		if (preg_match('/tree ([a-z0-9]{40})/', $str, $matches)) {
			return $matches[1];
		}
		else {
			throw new \Exception('Could not find tree hash: '.$str);
		}
	}

	public function getFiles() {
		if (count($this->files) === 0) {
			throw new \Exception('Either there are no files or you didn\'t call `fetch()` yet');
		}

		return $this->files;
	}

	private static function normalizeTargetDir($dir) {
		$lastChar = substr($dir, -1);

		if ($lastChar !== '/' && $lastChar !== '\\') {
			$dir .= '/';
		}

		return $dir;
	}

	public function download($targetDir = './') {
		$targetDir = self::normalizeTargetDir($targetDir);

		if (!file_exists($targetDir) || !is_dir($targetDir)) {
			throw new \Exception('Target directory does not exist: '.$targetDir);
		}

		foreach ($this->files as $file) {
			$pathSegments = explode('/', $file['name']);
			$filename = array_pop($pathSegments);

			// if the file is not in the root directory but in a sub-directory
			if (count($pathSegments) > 0) {
				$path = implode('/', $pathSegments).'/';

				// if the specific sub-directory does not exist yet
				if (!file_exists($targetDir.$path)) {
					// create the directory
					@mkdir($targetDir.$path, 0755, true);
				}
			}
			else {
				$path = '';
			}

			$content = $this->loadObject($file['hash'], $file['mode']);

			// save the file
			file_put_contents($targetDir.$path.$filename, $content);
		}
	}

}
