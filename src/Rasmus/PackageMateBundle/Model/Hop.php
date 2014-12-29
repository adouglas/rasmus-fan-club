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
   * [$contributer description]
   * @var [type]
   */
  public $contributer;
  public function __construct ($repo,$contributer){
    $this->repo = $repo;
    $this->contributer = $contributer;
  }
}
