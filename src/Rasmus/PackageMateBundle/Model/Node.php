<?php

namespace Rasmus\PackageMateBundle\Model;

class Node {
  private $path;
  private $value;

  public function __construct ($value,$path){
    $this->value = $value;
    $this->path = $path;
  }

  public function getValue(){
    return $this->value;
  }

  public function getPath(){
    return $this->path;
  }

  public function setValue($value){
    $this->value = $value;
  }

  public function setPath($path){
    $this->path = $path;
  }

  public function addPath($p){
    $this->path->push($p);
  }
}
