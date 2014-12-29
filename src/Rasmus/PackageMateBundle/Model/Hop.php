<?php

namespace Rasmus\PackageMateBundle\Model;

class Hop {
  public $repo;
  public $contributer;
  public function __construct ($repo,$contributer){
    $this->repo = $repo;
    $this->contributer = $contributer;
  }
}
