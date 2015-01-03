<?php

namespace Rasmus\PackageMateBundle\Model;

/**
* A single "Hop" from contributer to repo (or visa versa)
*/
class Hop {
  /**
  * The repo
  * @var string
  */
  public $repo;
  /**
  * A single contributer to the given repository
  * @var string
  */
  public $contributor;

  /**
  * @param string $repo        A single repository
  * @param string $contributor An associated user (colaborator)
  */
  public function __construct($repo, $contributor) {
    $this->repo = $repo;
    $this->contributor = $contributor;
  }
}
