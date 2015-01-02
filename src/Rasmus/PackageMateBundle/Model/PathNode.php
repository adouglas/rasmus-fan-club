<?php

namespace Rasmus\PackageMateBundle\Model;

use Rasmus\PackageMateBundle\Model\Node;

/**
 *
 */
class PathNode extends Node {
  /**
   * [$path description]
   * @var [type]
   */
  private $path;
  /**
   * [$value description]
   * @var [type]
   */
  private $value;

  public function __construct ($value,$path){
    $this->value = $value;
    $this->path = $path;
  }

  /**
   * [getValue description]
   */
  public function getValue(){
    return $this->value;
  }

  /**
   * [getPath description]
   */
  public function getPath(){
    return $this->path;
  }

  /**
   * [setValue description]
   * @param [type] $value [description]
   */
  public function setValue($value){
    $this->value = $value;
  }

  /**
   * [setPath description]
   * @param [type] $path [description]
   */
  public function setPath($path){
    $this->path = $path;
  }

  public function addPath($p){
    $this->path->push($p);
  }
}
