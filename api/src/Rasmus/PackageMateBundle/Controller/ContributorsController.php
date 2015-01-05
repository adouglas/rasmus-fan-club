<?php

namespace Rasmus\PackageMateBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View as View;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations\QueryParam;

use EasyRdf;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Rasmus\PackageMateBundle\Model\NodeQueue;
use Rasmus\PackageMateBundle\Model\RankedNode;

/**
 * Controller utilizing the FOSRestBundle to provide functionality for the RESTful service
 * @ /find-contributors?
 *
 * This service utilises a Breadth-First Search approch to traverse the graph of Github repos and
 * contributors stored as triples. Contributors are given scores depending the the strength of the
 * associations which separate them from the contributors of a given repo. This is then used to
 * produce a ranked list of possible new contributors of the repo.
 */
class ContributorsController extends Controller {

  /**
  * @Rest\View
  * @QueryParam(name="package", description="Packagist package from which to start the query")
  * @QueryParam(name="page", description="The page of result to view (where 100 = per_page * page)")
  * @QueryParam(name="per_page", description="The number of results to have per page (where 100 = per_page * page)")
  */
  public function getAction(ParamFetcher $paramFetcher) {

    // See description above
    $startPackage = $paramFetcher->get('package');

    $page = $paramFetcher->get('page');
    if (is_null($page)) {
      // If all else start on page 1
      $page = 1;
    } else {
      $page = intval($page);
    }
    $perPage = $paramFetcher->get('per_page');
    if (is_null($perPage)) {
      // By default show max 10 per page
      $perPage = 10;
    } else {
      $perPage = intval($perPage);
    }

    // Initial query returning the current contributers for the initial package (startPackage)
    try {
      \EasyRdf_Namespace::set('ont', 'http://adouglas.github.io/onto/php-packages.rdf#');
      $sparql = new \EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?limit=0&query=');
      $result = $sparql->query(
      'SELECT ?contributor ' .
      'WHERE' .
      '{ ' .
        '?startPackage ont:packageName "' . $startPackage . '". ' .
        '?startPackage ont:hasRepository ?sourceRepo. ' .
        '?sourceRepo ont:hasContributor ?c. ' .
        '?c ont:name ?contributor' .
        '}LIMIT 2000');
    }
    catch (Exception $e) {
      // TODO: Logging/devteam notification here?

      // SPARQL endpoint unavalible?
      return $this->sendResponse(null, 'Internal Server Error', 500);
    }

    if ($result->numRows() == 0) {
      // There is no package/no contributors
      return $this->sendResponse(null, 'No valid package/initial contributors found');
    }

    try {
      // Search for new contributors using the exisiting contributors as a starting point
      $results = $this->search($result);
    }
    catch (Exception $e) {
      // TODO: Logging/devteam notification here?

      // SPARQL endpoint unavalible?
      return $this->sendResponse(null, 'Internal Server Error', 500);
    }

    // Remove the original contributors (as they are already associated with this package/repo)
    for ($i = 0; $i < count($result); $i++) {
      unset($results[md5($result[$i]->contributor->getValue())]);
    }

    // Sort new contributors by scores
    uasort($results, function($a, $b) {
      if ($a->getScore() == $b->getScore()) {
        return 0;
      }
      return ($a->getScore() < $b->getScore()) ? 1 : -1;
    });

    $total = count($results);
    $list = array();
    $order = 1;

    // Paging
    // TODO: Cache graph; allow more extensive/faster results by storing the created graph for a short time period
    $results = array_slice($results, ($page - 1) * $perPage, $perPage, true);

    // Look the resuults and generate a list of possible contributors
    foreach ($results as $key => $user) {
      $list[] = array(
      'type' => 'contributor',
      'username' => $user->getUserName(),
      'order' => $order++,
      'link' => array(
        'rel' => 'self',
        'href' => 'http://github.com/' . $user->getUserName()
      )
      );
    }
    return $this->sendResponse($list, $page, $perPage, $total, 'Search complete');
  }

  /**
  * Searches for a set of Github users connected to the users provided in the param.
  * User are connected by being contributors to same repositories. Thus this function
  * will return immediate connections as well as ones separated by several
  * collaborator -> repo -> collaborator relationships.
  *
  * This function serves to inititate the search as well and to control the length of the
  * search using a maximum return limit or ceasing if no more direct relationships are possible.
  *
  * @param  array $initialContributor The initial collaborators from which to start the search
  * @return array                     An array of found collaborators (unsorted)
  */
  private function search($initialContributor) {

    // Initialise our SPARQL client
    \EasyRdf_Namespace::set('ont', 'http://adouglas.github.io/onto/php-packages.rdf#');
    $sparql = new \EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?limit=0&query=');

    $queue = new NodeQueue();
    $visited = array();
    $depth = 1;

    // Set the maximum number of new collaborators to be discovered (100)
    $returnLimit = 100 + count($initialContributor);

    // Load the current collaborators
    for ($i = 0; $i < count($initialContributor); $i++) {
      $contributor = $initialContributor[$i]->contributor->getValue();
      $nodeHash = md5($contributor);
      $queue->enqueue($contributor);
      $visited[$nodeHash] = new RankedNode($nodeHash, $contributor, 1, 1);
    }

    // While there are still collaborators to invetsigate or the number of
    // new collaborators is less that the max keep calling the BFS algorythm
    while (!$queue->isEmpty() && count($visited) <= $returnLimit) {
      $this->BFS_Ranked($sparql, $queue, $visited, $depth, false);
    }

    // Finish up by finalizing the ranking for all leaf nodes
    while (!$queue->isEmpty()) {
      $this->BFS_Ranked($sparql, $queue, $visited, $depth, true);
    }

    return $visited;
  }


  /**
  * A simple Breadth-First Search funcition. Nodes are dequeued one at a time and
  * all connecting collaborators are discovered and (if new) added to the queue (FIFO).
  * An attempt has been made to speed up the search process by treating repoitories as
  * non-visible, thus muitiple repositories which connect the same two collaborators
  * are seen as multiple edges to the same node. Rather than returning a set of repositories
  * The internal (SPARQL) search returns a count of the number of different paths to the
  * new collaborator.
  *
  * This funciton takes a ranked approach as collabrators are given scores based on the number
  * of incoming edges (when the distance (d in number of collaborators from the root) of each new edge is
  * d <= existing(min)). The score of any nodes parent is then added to it's own score and
  * the total is multiplied by 1/d.
  *
  * @param EasyRdf_Sparql_Client $sparql   a SPARQL client instance
  * @param NodeQueue $queue    The current queue
  * @param array $visited  An array of visited nodes and data about them
  * @param int $depth    The current depth from root
  * @param boolean $finalize If true then the remaining nodes are treated as leaves and only scores are updated
  */
  private function BFS_Ranked($sparql, &$queue, &$visited, &$depth, $finalize) {

    // Take the first element off the queue
    $currentNode = $queue->dequeue();
    $currentNodeHash = md5($currentNode);

    // Process the final scores for this collaborator
    $currentNodeScore = $visited[$currentNodeHash]->getScore();
    $currentNodeFinalScore = $currentNodeScore / $visited[$currentNodeHash]->getDepth();
    $visited[$currentNodeHash]->setScore($currentNodeFinalScore);

    // Go no further if finalize is set to true
    if ($finalize) {
      return true;
    }

    // Get next set of collaborators
    $result = $sparql->query(
    'SELECT ?startname ?endname (count(?name) as ?link_strength) ' .
    'WHERE ' .
    '{ ' .
      '?start ont:name "' . $currentNode . '". ' .
      '?start ont:name ?startname. ' .
      '?end ont:name ?endname. ' .
      '?start ont:contributorOn ?mid. ' .
      '?mid ont:hasContributor ?end. ' .
      '?mid ont:repostoryName ?name. ' .
      'FILTER NOT EXISTS ' .
      '{ ' .
        '?end ont:name ?startname. ' .
      '} ' .
    '}GROUP BY ?startname ?endname ' .
    'LIMIT 4000');

    $parentScore = $currentNodeFinalScore;
    $newDepth = $depth + 1;

    // Process the new collaborators (children)
    for ($i = 0; $i < count($result); $i++) {
      $nodeHash = md5($result[$i]->endname->getValue());
      // If the collaborator is new add to the queue and set as visited
      if (!array_key_exists($nodeHash, $visited)) {
        $visited[$nodeHash] = new RankedNode($nodeHash, $result[$i]->endname->getValue(), $newDepth, $parentScore + 1);
        $queue->enqueue($result[$i]->endname->getValue());
      } else {
        // If the collaborator is know add to the rank score (only if the depth is <= the current min)
        $visited[$nodeHash]->conditionalAddScore($result[$i]->link_strength->getValue(), $newDepth);
      }
    }

    // If this is the last final node in this tree depth to be processed increment
    // the depth.
    if ($visited[$currentNodeHash]->isLast()) {
      $visited[$nodeHash]->setLast(true);
      $depth++;
    }

    return true;
  }

  /**
  * Build a response for use by fos_rest.view_handler
  *
  * @param array  $list    The list of new collaborators to return
  * @param int  $page    The current "page" of collaborators
  * @param int  $perPage The number of collaborators to return in a single request
  * @param int  $total   The total number of new collaborators found
  * @param string  $message A response message
  * @param integer $code    The HTTP response code to use
  */
  private function sendResponse($list, $page, $perPage, $total, $message = '', $code = 200) {
    // HATEOS links
    $link = array(
    array(
    'rel' => 'self',
    'href' => $this->getRequest()->getUri()
    )
    );
    if ($page !== 1 && $total !== 0) {
      $href = preg_replace("/\b(?!per)\w*page=[\d+]\b/", 'page=' . ($page + 1), $this->getRequest()->getUri());
      $link[] = array(
      'rel' => 'previous',
      'href' => $href
      );
    }
    if (($page - 1) * $perPage > $total) {
      $href = preg_replace("/\b(?!per)\w*page=[\d+]\b/", 'page=' . ($page - 1), $this->getRequest()->getUri());
      $link[] = array(
      'rel' => 'next',
      'href' => $href
      );
    }

    // Return data split in to meta (inc status and response level HATEOS related links), and a message body.
    $result = array(
    'meta' => array(
    'status' => $code,
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'link' => $link
    ),
    'data' => array(
    'message' => $message,
    'contributor' => $list
    )
    );

    // Handle respopnse with the fos_rest.view_handler
    $view = View::create()->setStatusCode($code)->setData($result)->setFormat('json');
    return $this->get('fos_rest.view_handler')->handle($view);
  }
}
