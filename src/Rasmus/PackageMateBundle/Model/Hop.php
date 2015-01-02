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

  /**
   * [__construct description]
   * @param [type] $repo        [description]
   * @param [type] $contributor [description]
   */
  public function __construct ($repo,$contributor){
    $this->repo = $repo;
    $this->contributor = $contributor;
  }
}
