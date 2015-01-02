<?php

namespace Rasmus\PackageMateBundle\Model;

use Rasmus\PackageMateBundle\Model\Node;

/**
*
*/
class RankedNode extends Node {
  /**
   * [$hash description]
   * @var [type]
   */
  private $hash;
  /**
   * [$userName description]
   * @var [type]
   */
  private $userName;
  /**
   * [$depth description]
   * @var [type]
   */
  private $depth;
  /**
   * [$score description]
   * @var [type]
   */
  private $score;
  /**
   * [$last description]
   * @var [type]
   */
  private $last;

  /**
   * [__construct description]
   * @param [type] $hash     [description]
   * @param [type] $userName [description]
   * @param [type] $depth    [description]
   * @param [type] $score    [description]
   */
  public function __construct ($hash,$userName,$depth,$score){
    $this->hash = $hash;
    $this->userName = $userName;
    $this->depth = $depth;
    $this->score = $score;
  }

  /**
   * [getHash description]
   */
  public function getHash(){
    return $this->hash;
  }

  /**
   * [getUserName description]
   */
  public function getUserName(){
    return $this->userName;
  }

  /**
   * [getDepth description]
   */
  public function getDepth(){
    return $this->depth;
  }

  /**
   * [getScore description]
   */
  public function getScore(){
    return $this->score;
  }

  /**
   * [isLast description]
   */
  public function isLast(){
    return $this->last;
  }

  /**
   * [setHash description]
   * @param [type] $hash [description]
   */
  public function setHash($hash){
    $this->hash = $hash;
  }

  /**
   * [setUserName description]
   * @param [type] $userName [description]
   */
  public function setUserName($userName){
    $this->userName = $userName;
  }

  /**
   * [setDepth description]
   * @param [type] $depth [description]
   */
  public function setDepth($depth){
    $this->depth = $depth;
  }

  /**
   * [setScore description]
   * @param [type] $score [description]
   */
  public function setScore($score){
    $this->score = $score;
  }

  /**
   * [setLast description]
   * @param [type] $last [description]
   */
  public function setLast($last){
    $this->last = $last;
  }

  /**
   * [conditionalAddScore description]
   * @param [type] $score [description]
   * @param [type] $depth [description]
   */
  public function conditionalAddScore($score,$depth){
    if($depth > $this->depth){
      return false;
    }

    $this->depth += $depth;
  }
}
