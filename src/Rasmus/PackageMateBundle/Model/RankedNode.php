<?php

namespace Rasmus\PackageMateBundle\Model;

use Rasmus\PackageMateBundle\Model\Node;

/**
*
*/
class RankedNode extends Node {
  private $hash;
  private $userName;
  private $depth;
  private $score;
  private $last;

  public function __construct ($hash,$userName,$depth,$score){
    $this->hash = $hash;
    $this->userName = $userName;
    $this->depth = $depth;
    $this->score = $score;
  }

  public function getHash(){
    return $this->hash;
  }

  public function getUserName(){
    return $this->userName;
  }

  public function getDepth(){
    return $this->depth;
  }

  public function getScore(){
    return $this->score;
  }

  public function isLast(){
    return $this->last;
  }

  public function setHash($hash){
    $this->hash = $hash;
  }

  public function setUserName($userName){
    $this->userName = $userName;
  }

  public function setDepth($depth){
    $this->depth = $depth;
  }

  public function setScore($score){
    $this->score = $score;
  }

  public function setLast($last){
    $this->last = $last;
  }

  public function conditionalAddScore($score,$depth){
    if($depth > $this->depth){
      return false;
    }

    $this->depth += $depth;
  }
}
