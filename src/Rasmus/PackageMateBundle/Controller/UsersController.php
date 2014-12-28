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
      $results = $this->search($user1,$user2);
    }
    catch(Exception $e){
      // TODO: Logging/devteam notification here?

      // SPARQL endpoint unavalible?
      return $this->sendResponse([],'Internal Server Error',500);
    }

    $pathObject = array();

    $order = 0;

    for($i = 0; $i < count($results); $i++ ) {
      if(!is_null($results[$i]->repo)){
        $pathObject[] = array(
          'type'=> 'repository',
          'id'=> $results[$i]->repo,
          'order' => $order++,
          'link'=> array(
            'rel' => 'self',
            'href' => 'http://github.com/'.$results[$i]->repo
          )
        );
      }
      if(!(is_null($results[$i]->contributer) || ($i > 0 && $results[$i-1]->contributer == $results[$i]->contributer))){
        $pathObject[] = array(
          'type'=> 'collaborator',
          'id'=> $results[$i]->contributer,
          'order' => $order++,
          'link'=> array(
            'rel' => 'self',
            'href' => 'http://github.com/'.$results[$i]->contributer
          )
        );
      }

    }
    return $this->sendResponse($pathObject,'Search complete');

  }

  private function search($start,$end){
    $logger = $this->get('logger');

    \EasyRdf_Namespace::set('ont', 'http://adouglas.github.io/onto/php-packages.rdf#');
    $sparql = new \EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?query=');

    $queueFF = new NodeQueue();
    $queueBF = new NodeQueue();

    $visited = array();
    $visitedRepos = array();

    $pathFF = new Path();
    $pathFF->push(new Hop(null,$start));
    $pathBF = new Path();
    $pathBF->push(new Hop(null,$end));

    $finalPath = false;

    $queueFF->enqueue(new Node($start,$pathFF));
    $queueBF->enqueue(new Node($end,$pathBF));

    $visited[md5($start)] = md5($start);

    $found = false;

    while((!$queueFF->isEmpty() || !$queueBF->isEmpty()) && ($found === false)){
      if((!$queueFF->isEmpty() && ($found === false))){
        $found = $this->BFS($start,$end,$sparql,$queueFF,$pathFF,$visited,$visitedRepos);
        if($found !== false){
          $logger->info('Found in A: ');
          if($found === Outcome::PART_SOLUTION){
              // Need to parse the other queue to find the linking point and add to path
              while(!$queueBF->isEmpty()){
                $current = $queueBF->dequeue();
                if($current->getValue() == $pathFF->top()->contributer){
                  $finalPath = $pathFF;
                  $pathBF->setIteratorMode(\SplDoublyLinkedList::IT_MODE_LIFO | \SplDoublyLinkedList::IT_MODE_DELETE);
                  $pathBF->rewind();
                  while(!$pathBF->isEmpty()){
                    $finalPath->push($pathBF->current());
                    $pathBF->next();
                  }
                  break;
                }
              }
          }
          else{
            $finalPath = $pathFF;
          }
        }
      }
      if((!$queueBF->isEmpty() && ($found === false))){
        $found = $this->BFS($end,$start,$sparql,$queueBF,$pathBF,$visited,$visitedRepos);
        if($found !== false){
          if($found === Outcome::PART_SOLUTION){
            // Need to parse the other queue to find the linking point and add to path
            while(!$queueFF->isEmpty()){
              $current = $queueFF->dequeue();
              if($current->getValue() == $pathBF->top()->contributer){
                $finalPath = $current->getPath();
                $pathBF->setIteratorMode(\SplDoublyLinkedList::IT_MODE_LIFO | \SplDoublyLinkedList::IT_MODE_DELETE);
                $pathBF->rewind();
                while(!$pathBF->isEmpty()){
                  $finalPath->push($pathBF->current());
                  $pathBF->next();
                }
                break;
              }
            }
          }
          else{
            $finalPath = $pathBF;
          }
        }
      }
    }

    return $finalPath;
  }

  private function BFS($start,$end,$sparql,&$queue,&$finalPath,&$visited,&$visitedRepos){
    $logger = $this->get('logger');
    $tmpPath = new Path();
    $tmpVisitedRepos = array();

    $startHash = md5($start);

    $currentNode = $queue->dequeue();

    if($currentNode->getValue() === $end){
      $finalPath = $currentNode->getPath();
      return Outcome::WHOLE_SOLUTION;
    }

    // Get next set of collaborators
    $result = $sparql->query(
    'SELECT ?startname ?endname ?repo '.
    'WHERE'.
    '{'.
      '?start ont:name "'.$currentNode->getValue().'".'.
      '?start ont:name ?startname.'.
      '?end ont:name ?endname.'.
      '?start ont:collaboratesOn ?mid.'.
      '?mid ont:hasCollaborator ?end.'.
      '?mid ont:repostoryName ?repo.'.
      'FILTER NOT EXISTS'.
      '{'.
        (!$currentNode->getPath()->isEmpty()? '{ ?end ont:name ?startname } UNION { ?repo ont:repostoryName "' . $currentNode->getPath()->top()->repo . '" }' : '' ).
        '}'.
        '}LIMIT 1000'
      );



      for($i = 0; $i < count($result); $i++){
        $nodeHash = md5($result[$i]->endname->getValue());
        $repoHash = md5($result[$i]->repo->getValue());
        if((!array_key_exists($nodeHash,$visited)) && (!array_key_exists($repoHash,$visitedRepos))){
          $tmpPath = clone $currentNode->getPath();

          $tmpPath->push(new Hop($result[$i]->repo->getValue(),$result[$i]->endname->getValue()));

          $queue->enqueue(new Node($result[$i]->endname->getValue(),$tmpPath));
          $visited[$nodeHash] = md5($start);
          $tmpVisitedRepos[$repoHash] = md5($start);
        }
        else{
          if((array_key_exists($nodeHash,$visited) && $visited[$nodeHash] != md5($start)) || (array_key_exists($repoHash,$visitedRepos) && $visitedRepos[$repoHash] != md5($start))){
            $logger->info('In found path');
            $logger->info((array_key_exists($nodeHash,$visited) && $visited[$nodeHash] != md5($start)));
            $logger->info((array_key_exists($repoHash,$visitedRepos) && $visitedRepos[$repoHash] != md5($start)));
            $currentNode->getPath()->push(new Hop($result[$i]->repo->getValue(),$result[$i]->endname->getValue()));
            $finalPath = $currentNode->getPath();
            return Outcome::PART_SOLUTION;
          }
        }
      }
      $visitedRepos = array_merge($tmpVisitedRepos,$visitedRepos);
    return false;
  }

  private function sendResponse($pathObject,$message='',$code=200){
    $result = array(
      'meta' => array(
        'status' => $code,
        'link' => array(
          array(
            'rel' => 'self',
            'href' => $this->getRequest()->getUri()
          )
        )
      ),
      'data' => array(
        'message' => $message,
        'path_found' => (empty($pathObject) ? false : true),
        'paths' => $pathObject
    ));

    $view = View::create()
    ->setStatusCode($code)
    ->setData($result)
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

class Outcome {
  const WHOLE_SOLUTION = 1;
  const PART_SOLUTION = 2;
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
