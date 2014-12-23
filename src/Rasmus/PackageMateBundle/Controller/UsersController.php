<?php

namespace Rasmus\PackageMateBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View as View;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations\QueryParam;

use EasyRdf;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class UsersController extends Controller
{
  /**
  * @Rest\View
  * @QueryParam(name="user1", description="User from which to start the query")
  * @QueryParam(name="user2", description="User from which to end the query")
  */
  public function getAction(ParamFetcher $paramFetcher)
  {

    $user1 = $paramFetcher->get('user1');
    $user2 = $paramFetcher->get('user2');

    //
    // var_dump($queryString);
    // var_dump($query);
    // die();

    // Initial query asking the triple store if a path between user 1 and user 2 exists.
    // This is a quick query but will not return a path. This is used to speed up the query for
    // the end user/and reduce server overheads when no path exists.
    try{
      \EasyRdf_Namespace::set('ont', 'http://adouglas.github.io/onto/php-packages.rdf#');
      $sparql = new \EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?query=');
      $result = $sparql->query(
        'ASK' .
        '{'.
          '?start ont:name "'.$user1.'".'.
          '?end ont:name "'.$user2.'".'.
          '?start (ont:collaboratesOn/ont:hasCollaborator)* ?end.'.
        '}'
      );
    }
    catch(Exception $e){
      // TODO: Logging/devteam notification here?

      // SPARQL endpoint unavalible?
      return $this->sendResponse([],'Internal Server Error',500);
    }

    if($result->isFalse()){
      // There is no possible path between the two users provided
      return $this->sendResponse([],'No valid path was found');
    }

    try{
      $get = $this->BFS($user1,$user2);
    }
    catch(Exception $e){
      // TODO: Logging/devteam notification here?
      
      // SPARQL endpoint unavalible?
      return $this->sendResponse([],'Internal Server Error',500);
    }

    var_dump($get);
    die;

    // foreach ($result as $row) {
    //   echo "<li>".link_to($row->label, $row->country)."</li>\n";
    // }
    return $this->sendResponse(["p1"=>$get],'Search complete');

  }

  private function BFS($start,$end){
    $queue = new NodeQueue();
    $visited = array();

    \EasyRdf_Namespace::set('ont', 'http://adouglas.github.io/onto/php-packages.rdf#');
    $sparql = new \EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?query=');

    $path = new Path();

    $queue->enqueue(new Node($start,$path));
    $visited[md5($start)] = true;
    while(!$queue->isEmpty()){
      $currentNode = $queue->dequeue();

      if($currentNode->getValue() === $end){
        return $currentNode->getPath();
      }

      // Get next set of collaborators
      $result = $sparql->query(
      'SELECT ?startname ?endname (group_concat(?name) as ?paths)'.
      'WHERE'.
      '{'.
        '?start ont:name "'.$currentNode->getValue().'".'.
        '?start ont:name ?startname.'.
        '?end ont:name ?endname.'.
        '?start ont:collaboratesOn ?mid.'.
        '?mid ont:hasCollaborator ?end.'.
        '?mid ont:repostoryName ?name.'.
        'FILTER NOT EXISTS'.
        '{'.
          '?end ont:name ?startname.'.
          '}'.
          '}GROUP BY ?startname ?endname'
        );

        for($i = 0; $i < count($result); $i++){
          $nodeHash = md5($result[$i]->endname->getValue());
          if(!array_key_exists($nodeHash,$visited)){
            $path = clone $currentNode->getPath();
            $path->push(new Hop($result[$i]->paths->getValue(),$result[$i]->endname->getValue()));

            $queue->enqueue(new Node($result[$i]->endname->getValue(),$path));
            $visited[$nodeHash] = true;
          }
        }
      }
    return false;
  }

  private function sendResponse($pathObject,$message='',$code=200){

    $result = array(
      'message' => $message,
      'path_found' => (empty($pathObject) ? false : true),
      'total_count' => count($pathObject),
      'paths' => $pathObject
    );

    $view = View::create()
    ->setStatusCode($code)
    ->setData(array('result'=>$result))
    ->setFormat('json');
    return $this->get('fos_rest.view_handler')->handle($view);
  }
}


class NodeQueue extends \SplQueue {}

class Path extends \SplDoublyLinkedList {}

class Hop {
  public $repo;
  public $contributer;
  public function __construct ($repo,$contributer){
    $this->repo = $repo;
    $this->contributer = $contributer;
  }
}

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
