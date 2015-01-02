<?php

namespace Rasmus\PackageMateBundle\Model;

/**
 *
 */
class Hop {
  /**
   * [$repo description]
   * @var [type]
   */
  public $repo;
  /**
   * [$contributor description]
   * @var [type]
   */
  public $contributor;
  public function __construct ($repo,$contributor){
    $this->repo = $repo;
    $this->contributor = $contributor;
  }
}
