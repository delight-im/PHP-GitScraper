# GitScraper

Downloads entire Git repositories from publicly accessible `.git` folders over HTTP

 * Directory indexes or directory browsing on the web server are *not* required
 * Running `git update-server-info` on the server is *not* required

## Requirements

 * PHP 5.5.0+

## Installation

 * Install via [Composer](https://getcomposer.org/) (recommended)

   `$ composer require delight-im/git-scraper`

   Include the Composer autoloader:

   `require __DIR__.'/vendor/autoload.php';`

 * or
 * Install manually
   * Copy the contents of the [`src`](src) directory to a subfolder of your project
   * Include the files in your code via `require` or `require_once`

## Usage

```
$scraper = new \Delight\GitScraper\GitScraper('http://www.example.com/.git/');
$scraper->fetch();
// var_dump($scraper->getFiles());
$scraper->download('./');
```

## Terminology

 * hash
   * used to identify objects in Git
   * always uses the SHA-1 algorithm
   * has a length of 20 bytes, 40 hex characters or 160 bits
   * ensures file integrity
 * object
   * stored in `.git/objects`
   * addressable by its unique hash
   * has a small header describing the type and length of its content
   * compressed with `zlib`
   * can be previewed (in a slightly modified version) by running the command `git cat-file -p {hash}`
 * `commit` object
   * points to a single `tree` object (stored as 40 hex characters)
   * contains the name and email address of the committer as well as the commit time
   * includes information about the author (may *not* be the committer) which are analogous to the committer data
   * holds the commit message or description of the commit
   * points to the parent tree as well so that you can browse the history
 * `tree` object
   * corresponds to a directory on the file system
   * contains pointers to other objects (stored as 20 bytes)
   * `tree` objects (i.e. sub-directories) and `blob` objects (i.e. files inside the directory) may be listed here
 * `blob` object
   * similar to a file on the file system
   * simply a binary representation of the file

## Further reading

 * [Pro Git](https://git-scm.com/book/en/v2)
   * ["Git Internals"](https://git-scm.com/book/en/v2/Git-Internals-Plumbing-and-Porcelain)
   * ["The Dumb Protocol"](https://git-scm.com/book/en/v2/Git-Internals-Transfer-Protocols#The-Dumb-Protocol)
 * [Git Magic](http://www-cs-students.stanford.edu/~blynn/gitmagic/)
   * ["Secrets Revealed"](http://www-cs-students.stanford.edu/~blynn/gitmagic/ch08.html)
 * [A Hacker's Guide to Git](http://wildlyinaccurate.com/a-hackers-guide-to-git/)

## Contributing

All contributions are welcome! If you wish to contribute, please create an issue first so that your feature, problem or question can be discussed.

## Disclaimer

You should probably use this library with your own websites and repositories only.

## License

```
Copyright 2015 delight.im <info@delight.im>

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

  http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```
