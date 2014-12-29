<?php

namespace Rasmus\PackageMateBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View as View;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations\QueryParam;

use EasyRdf;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Rasmus\PackageMateBundle\Model\BFSOutcome;
use Rasmus\PackageMateBundle\Model\NodeQueue;
use Rasmus\PackageMateBundle\Model\Path;
use Rasmus\PackageMateBundle\Model\Hop;
use Rasmus\PackageMateBundle\Model\Node;

class UsersController extends Controller {
  /**
  * @Rest\View
  * @QueryParam(name="user1", description="User from which to start the query")
  * @QueryParam(name="user2", description="User from which to end the query")
  */
  public function getAction(ParamFetcher $paramFetcher) {

    $user1 = $paramFetcher->get('user1');
    $user2 = $paramFetcher->get('user2');

    // Initial query asking the triple store if a path between user 1 and user 2 exists.
    // This is a quick query but will not return a path. This is used to speed up the query for
    // the end user/and reduce server overheads when no path exists.
    try {
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
    catch (Exception $e) {
      // TODO: Logging/devteam notification here?

      // SPARQL endpoint unavalible?
      return $this->sendResponse(null, 'Internal Server Error', 500);
    }

    if ($result->isFalse()) {
      // There is no possible path between the two users provided
      return $this->sendResponse(null, 'No valid path was found');
    }

    try {
      $results = $this->search($user1, $user2);
    }
    catch (Exception $e) {
      // TODO: Logging/devteam notification here?

      // SPARQL endpoint unavalible?
      return $this->sendResponse(null, 'Internal Server Error', 500);
    }

    /**
     * [$pathObject description]
     * @var array
     */
    $pathObject = array();

    /**
     * [$order description]
     * @var integer
     */
    $order = 0;

    for ($i = 0; $i < count($results); $i++) {
      if (!is_null($results[$i]->repo)) {
        $pathObject[] = array(
          'type' => 'repository',
          'id' => $results[$i]->repo,
          'order' => $order++,
          'link' => array(
            'rel' => 'self',
            'href' => 'http://github.com/' . $results[$i]->repo
          )
        );
      }
      if (!(is_null($results[$i]->contributer) || ($i > 0 && $results[$i - 1]->contributer == $results[$i]->contributer))) {
        $pathObject[] = array(
          'type' => 'collaborator',
          'id' => $results[$i]->contributer,
          'order' => $order++,
          'link' => array(
            'rel' => 'self',
            'href' => 'http://github.com/' . $results[$i]->contributer
          )
        );
      }

    }
    return $this->sendResponse($pathObject, 'Search complete');
  }

  /**
   * [search description]
   * @param  [type] $start [description]
   * @param  [type] $end   [description]
   * @return [type]        [description]
   */
  private function search($start, $end) {

    \EasyRdf_Namespace::set('ont', 'http://adouglas.github.io/onto/php-packages.rdf#');
    $sparql = new \EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?query=');

    /**
     * [$queueFF description]
     * @var NodeQueue
     */
    $queueFF = new NodeQueue();
    /**
     * [$queueBF description]
     * @var NodeQueue
     */
    $queueBF = new NodeQueue();

    /**
     * [$visited description]
     * @var array
     */
    $visited = array();
    /**
     * [$visitedRepos description]
     * @var array
     */
    $visitedRepos = array();

    /**
     * [$pathFF description]
     * @var Path
     */
    $pathFF = new Path();
    $pathFF->push(new Hop(null, $start));
    /**
     * [$pathBF description]
     * @var Path
     */
    $pathBF = new Path();
    $pathBF->push(new Hop(null, $end));

    /**
     * [$finalPath description]
     * @var [type]
     */
    $finalPath = false;

    //
    $queueFF->enqueue(new Node($start, $pathFF));
    $queueBF->enqueue(new Node($end, $pathBF));

    //
    $visited[md5($start)] = md5($start);

    /**
     * [$found description]
     * @var [type]
     */
    $found = false;

    while ((!$queueFF->isEmpty() || !$queueBF->isEmpty()) && ($found === false)) {
      if ((!$queueFF->isEmpty() && ($found === false))) {
        $found = $this->searchStep($start, $end, $sparql, $queueFF, $pathFF, $queueBF, $pathBF, $visited, $visitedRepos, $finalPath);
      }
      if ((!$queueBF->isEmpty() && ($found === false))) {
        $found = $this->searchStep($end, $start, $sparql, $queueBF, $pathBF, $queueFF, $pathFF, $visited, $visitedRepos, $finalPath);
      }
    }

    return $finalPath;
  }


  /**
   * [searchStep description]
   * @param [type] $start        [description]
   * @param [type] $end          [description]
   * @param [type] $sparql       [description]
   * @param [type] $queueA       [description]
   * @param [type] $pathA        [description]
   * @param [type] $queueB       [description]
   * @param [type] $pathB        [description]
   * @param [type] $visited      [description]
   * @param [type] $visitedRepos [description]
   * @param [type] $finalPath    [description]
   */
  private function searchStep($start, $end, $sparql, &$queueA, &$pathA, &$queueB, &$pathB, &$visited, &$visitedRepos, &$finalPath) {
    $found = $this->BFS($start, $end, $sparql, $queueA, $pathA, $visited, $visitedRepos);
    if ($found !== false) {
      if ($found === BFSOutcome::PART_SOLUTION) {
        // Need to parse the other queue to find the linking point and add to path
        while (!$queueB->isEmpty()) {
          $current = $queueB->dequeue();
          if ($current->getValue() == $pathA->top()->contributer) {
            $finalPath = $current->getPath();
            $pathA->setIteratorMode(\SplDoublyLinkedList::IT_MODE_LIFO | \SplDoublyLinkedList::IT_MODE_DELETE);
            $pathA->rewind();
            while (!$pathA->isEmpty()) {
              $finalPath->push($pathA->current());
              $pathA->next();
            }
            break;
          }
        }
      } else {
        $finalPath = $pathA;
      }
    }
    return $found;
  }


  /**
   * [BFS description]
   * @param [type] $start        [description]
   * @param [type] $end          [description]
   * @param [type] $sparql       [description]
   * @param [type] $queue        [description]
   * @param [type] $finalPath    [description]
   * @param [type] $visited      [description]
   * @param [type] $visitedRepos [description]
   */
  private function BFS($start, $end, $sparql, &$queue, &$finalPath, &$visited, &$visitedRepos) {
    /**
     * [$tmpPath description]
     * @var Path
     */
    $tmpPath = new Path();
    $tmpVisitedRepos = array();

    /**
     * [$startHash description]
     * @var [type]
     */
    $startHash = md5($start);

    /**
     * [$currentNode description]
     * @var [type]
     */
    $currentNode = $queue->dequeue();

    if ($currentNode->getValue() === $end) {
      $finalPath = $currentNode->getPath();
      return BFSOutcome::WHOLE_SOLUTION;
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

    for ($i = 0; $i < count($result); $i++) {
      $nodeHash = md5($result[$i]->endname->getValue());
      $repoHash = md5($result[$i]->repo->getValue());
      if ((!array_key_exists($nodeHash, $visited)) && (!array_key_exists($repoHash, $visitedRepos))) {
        $tmpPath = clone $currentNode->getPath();

        $tmpPath->push(new Hop($result[$i]->repo->getValue(), $result[$i]->endname->getValue()));
        $queue->enqueue(new Node($result[$i]->endname->getValue(), $tmpPath));

        $visited[$nodeHash] = md5($start);
        $tmpVisitedRepos[$repoHash] = md5($start);
      } else {
        if ((array_key_exists($nodeHash, $visited) && $visited[$nodeHash] != md5($start)) || (array_key_exists($repoHash, $visitedRepos) && $visitedRepos[$repoHash] != md5($start))) {
          $currentNode->getPath()->push(new Hop($result[$i]->repo->getValue(), $result[$i]->endname->getValue()));
          $finalPath = $currentNode->getPath();
          return BFSOutcome::PART_SOLUTION;
        }
      }
    }
    /**
     * [$visitedRepos description]
     * @var array
     */
    $visitedRepos = array_merge($tmpVisitedRepos, $visitedRepos);
    return false;
  }

  /**
   * [sendResponse description]
   * @param [type]  $pathObject [description]
   * @param string  $message    [description]
   * @param integer $code       [description]
   */
  private function sendResponse($pathObject, $message = '', $code = 200) {
    /**
     * [$result description]
     * @var array
     */
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
      )
    );

    /**
     * [$view description]
     * @var [type]
     */
    $view = View::create()->setStatusCode($code)->setData($result)->setFormat('json');
    return $this->get('fos_rest.view_handler')->handle($view);
  }
}
