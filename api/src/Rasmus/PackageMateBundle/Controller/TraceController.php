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
use Rasmus\PackageMateBundle\Model\PathNode;

/**
* Controller utilizing the FOSRestBundle to provide functionality for the RESTful service
* @ /trace-user?
*
* This service utilises a Breadth-First Search approch to traverse the graph of Github repos and
* contributors stored as triples. The intention is to then return an ordered set of collaborators
* and repositories which link to form the shortest graph possible, leading from a start user to
* an end user. If no path exists then an empty path is returned
*/

class TraceController extends Controller {
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
      $result = $sparql->query('ASK' . '{' . '?start ont:name "' . $user1 . '".' . '?end ont:name "' . $user2 . '".' . '?start (ont:contributorOn/ont:hasContributor)* ?end.' . '}');
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
      // A path is possible so call search to find it
      $results = $this->search($user1, $user2);
    }
    catch (Exception $e) {
      // TODO: Logging/devteam notification here?

      // SPARQL endpoint unavalible?
      return $this->sendResponse(null, 'Internal Server Error', 500);
    }


    $pathObject = array();
    $order = 0;

    // Loop over the result and populate the path array
    for ($i = 0; $i < count($results); $i++) {

      // This node is a repository
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

      // This node is a contributer
      if (!(is_null($results[$i]->contributor) || ($i > 0 && $results[$i - 1]->contributor == $results[$i]->contributor))) {
        $pathObject[] = array(
          'type' => 'contributor',
          'id' => $results[$i]->contributor,
          'order' => $order++,
          'link' => array(
            'rel' => 'self',
            'href' => 'http://github.com/' . $results[$i]->contributor
          )
        );
      }
    }
    return $this->sendResponse($pathObject, 'Search complete');
  }

  /**
  * Search attempts to find the shortest path from one user to the next. It utilises a
  * bi-directional Breadth-First Search approch to discover the path while keeping the
  * number of visited nodes to a minimum.
  *
  * @param  string $start The name of the user at the start of the path
  * @param  string $end   The name of the user at the end of the path
  * @return array        The basic path from $start to $end
  */
  private function search($start, $end) {

    \EasyRdf_Namespace::set('ont', 'http://adouglas.github.io/onto/php-packages.rdf#');
    $sparql = new \EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?query=');

    // Queues to store nodes to be visited
    $queueFF = new NodeQueue();
    $queueBF = new NodeQueue();

    // Nodes already visited
    $visited = array();
    $visitedRepos = array();
    $visited[md5($start)] = md5($start);

    // Paths from the root node to the current position
    $pathFF = new Path();
    $pathFF->push(new Hop(null, $start));
    $pathBF = new Path();
    $pathBF->push(new Hop(null, $end));

    // The final full path
    $finalPath = false;

    // Load the $start and $end nodes onto queues
    $queueFF->enqueue(new PathNode($start, $pathFF));
    $queueBF->enqueue(new PathNode($end, $pathBF));

    // Has a path been found?
    $found = false;

    // TODO This is a basic bi-directional appraoch (one step for each approch at a time) this could possibly be improved using a shortest queue first approch
    // Loop over the two queues until both are empty or a path is found
    while ((!$queueFF->isEmpty() || !$queueBF->isEmpty()) && ($found === false)) {

      // Process the front first (starting at $start) queue
      if ((!$queueFF->isEmpty() && ($found === false))) {
        $found = $this->searchStep($start, $end, $sparql, $queueFF, $pathFF, $queueBF, $pathBF, $visited, $visitedRepos, $finalPath);
      }

      // Process the back first (starting at $end) queue
      if ((!$queueBF->isEmpty() && ($found === false))) {
        $found = $this->searchStep($end, $start, $sparql, $queueBF, $pathBF, $queueFF, $pathFF, $visited, $visitedRepos, $finalPath);
      }
    }
    return $finalPath;
  }


  /**
  * [searchStep description]
  * @param String $start        The name of the user from which this graph starts
  * @param String $end          The name of the user at which this graph ends
  * @param EasyRdf_Sparql_Client $sparql       An instance of the SPARQL client
  * @param NodeQueue $queueA       The queue for this search
  * @param Path $pathA        The current path for this search
  * @param NodeQueue $queueB       The queue for the other direction of search
  * @param Path $pathB        The current path for the other direction of search
  * @param array $visited      Current visited users
  * @param array $visitedRepos Current visited repositories
  * @param array $finalPath    The final path
  */
  private function searchStep($start, $end, $sparql, &$queueA, &$pathA, &$queueB, &$pathB, &$visited, &$visitedRepos, &$finalPath) {

    // Process the Breadth-First Search for this step
    $found = $this->BFS($start, $end, $sparql, $queueA, $pathA, $visited, $visitedRepos);

    // If a solution has be found
    if ($found !== false) {
      // If the solution is a partial solution then the current search direction has overlapped with the opposite search
      // direction and so the two must be joined
      if ($found === BFSOutcome::PART_SOLUTION) {
        // Need to parse the other queue to find the linking point and add to path
        while (!$queueB->isEmpty()) {
          $current = $queueB->dequeue();
          if ($current->getValue() == $pathA->top()->contributor) {
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
        // If the path is a complete path then sinply return the path from this dimension
        $finalPath = $pathA;
      }
    }
    return $found;
  }


  /**
  * A basic Breadth-First Search algoythm
  *
  * @param string $start        [description]
  * @param string $end          [description]
  * @param EasyRdf_Sparql_Client $sparql       [description]
  * @param NodeQueue $queue        [description]
  * @param array $finalPath    [description]
  * @param array $visited      [description]
  * @param array $visitedRepos [description]
  */
  private function BFS($start, $end, $sparql, &$queue, &$finalPath, &$visited, &$visitedRepos) {

    // Temporary stores for this search step
    $tmpPath = new Path();
    $tmpVisitedRepos = array();

    // Hashes are used to besure that an array key can be gained
    $startHash = md5($start);

    // INspect the node at the top of the queue
    $currentNode = $queue->dequeue();

    // If this node = the end return a whole soltion
    if ($currentNode->getValue() === $end) {
      $finalPath = $currentNode->getPath();
      return BFSOutcome::WHOLE_SOLUTION;
    }

    // Get next set of collaborators
    $result = $sparql->query('SELECT ?startname ?endname ?repo ' . 'WHERE' . '{' . '?start ont:name "' . $currentNode->getValue() . '".' . '?start ont:name ?startname.' . '?end ont:name ?endname.' . '?start ont:contributorOn ?mid.' . '?mid ont:hasContributor ?end.' . '?mid ont:repostoryName ?repo.' . 'FILTER NOT EXISTS' . '{' . (!$currentNode->getPath()->isEmpty() ? '{ ?end ont:name ?startname } UNION { ?repo ont:repostoryName "' . $currentNode->getPath()->top()->repo . '" }' : '') . '}' . '}LIMIT 1000');

    // Loopover the users found
    for ($i = 0; $i < count($result); $i++) {

      // Hashes are used to besure that an array key can be gained
      $nodeHash = md5($result[$i]->endname->getValue());
      $repoHash = md5($result[$i]->repo->getValue());

      // If this node has not been visited before then add it to the queue and add it to the visted list
      if ((!array_key_exists($nodeHash, $visited)) && (!array_key_exists($repoHash, $visitedRepos))) {
        $tmpPath = clone $currentNode->getPath();
        $tmpPath->push(new Hop($result[$i]->repo->getValue(), $result[$i]->endname->getValue()));

        $queue->enqueue(new PathNode($result[$i]->endname->getValue(), $tmpPath));

        $visited[$nodeHash] = md5($start);
        $tmpVisitedRepos[$repoHash] = md5($start);
      } else {

        // If the node has been visited (by the other dimension) this is a partial solution
        if ((array_key_exists($nodeHash, $visited) && $visited[$nodeHash] != md5($start)) || (array_key_exists($repoHash, $visitedRepos) && $visitedRepos[$repoHash] != md5($start))) {
          $currentNode->getPath()->push(new Hop($result[$i]->repo->getValue(), $result[$i]->endname->getValue()));
          $finalPath = $currentNode->getPath();
          return BFSOutcome::PART_SOLUTION;
        }
      }
    }

    $visitedRepos = array_merge($tmpVisitedRepos, $visitedRepos);
    return false;
  }


  /**
  * Build a response for use by fos_rest.view_handler
  *
  * @param [type]  $pathObject The path to be returned
  * @param string  $message A response message
  * @param integer $code    The HTTP response code to use
  */
  private function sendResponse($pathObject, $message = '', $code = 200) {

    // Return data split in to meta (inc status and response level HATEOS related links), and a message body.
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

    $view = View::create()->setStatusCode($code)->setData($result)->setFormat('json');
    return $this->get('fos_rest.view_handler')->handle($view);
  }
}
