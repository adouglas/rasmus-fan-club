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

class ContributorsController extends Controller {

  /**
  * @Rest\View
  * @QueryParam(name="package", description="Packagist package from which to start the query")
  * @QueryParam(name="page", description="The page of result to view (where 100 = per_page * page)")
  * @QueryParam(name="per_page", description="The number of results to have per page (where 100 = per_page * page)")
  */
  public function getAction(ParamFetcher $paramFetcher) {

    //
    $startPackage = $paramFetcher->get('package');

    $page = $paramFetcher->get('page');
    if(is_null($page)){
      $page = 1;
    }
    else{
      $page = intval($page);
    }
    $perPage = $paramFetcher->get('per_page');
    if(is_null($perPage)){
      $perPage = 10;
    }
    else{
      $perPage = intval($perPage);
    }

    // Initial query returning the current contributers for the initial package (startPackage)
    try {
      \EasyRdf_Namespace::set('ont', 'http://adouglas.github.io/onto/php-packages.rdf#');
      $sparql = new \EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?query=');
      $result = $sparql->query(
      'SELECT ?contributor ' .
      'WHERE'.
      '{ '.
        '?startPackage ont:packageName "'.$startPackage.'". '.
        '?startPackage ont:hasRepository ?sourceRepo. '.
        '?sourceRepo ont:hasCollaborator ?c. '.
        '?c ont:name ?contributor' .
        '}'
      );
    }
    catch (Exception $e) {
      // TODO: Logging/devteam notification here?

      // SPARQL endpoint unavalible?
      return $this->sendResponse(null, 'Internal Server Error', 500);
    }

    if ($result->numRows() == 0 ){
      // There is no package/no cobtributers
      return $this->sendResponse(null, 'No valid package/initial collaborators found');
    }

    try {
      $results = $this->search($result);
    }
    catch (Exception $e) {
      // TODO: Logging/devteam notification here?

      // SPARQL endpoint unavalible?
      return $this->sendResponse(null, 'Internal Server Error', 500);
    }

    //
    for ($i = 0; $i < count($result); $i++) {
      unset($results[md5($result[$i]->contributer->getValue())]);
    }

    //
    uasort($results,function($a, $b) {
      if ($a->getScore() == $b->getScore()) {
        return 0;
      }
      return ($a->getScore() < $b->getScore()) ? 1 : -1;
    });

    $total = count($results);
    $list = array();
    $order = 1;

    //
    $results = array_slice ( $results, ($page-1) * $perPage, $perPage, true );

    //
    foreach ($results as $key => $user) {
      $list[] = array(
        'type' => 'Github User',
        'username' => $user->getUserName(),
        'score' => $user->getScore(),
        'order' => $order++,
        'link' => array(
          'rel' => 'self',
          'href' => 'http://github.com/' . $user->getUserName()
        )
      );
    }

    //
    return $this->sendResponse($list, $page, $perPage, $total, 'Search complete');
  }

  /**
   * [search description]
   * @param  [type] $initialContributor [description]
   * @return [type]                     [description]
   */
  private function search($initialContributor) {

    //
    \EasyRdf_Namespace::set('ont', 'http://adouglas.github.io/onto/php-packages.rdf#');
    $sparql = new \EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?query=');

    $queue = new NodeQueue();
    $visited = array();
    $depth = 1;

    //
    $returnLimit = 100 + count($initialContributor);

    //
    for ($i = 0; $i < count($initialContributor); $i++) {
      $contributor = $initialContributor[$i]->contributer->getValue();
      $nodeHash = md5($contributor);
      $queue->enqueue($contributor);
      $visited[$nodeHash] = new RankedNode($nodeHash,$contributor, 1, 1);
    }

    //
    while (!$queue->isEmpty() && count($visited) <= $returnLimit) {
        $this->BFS_Ranked($sparql, $queue, $visited, $depth, false);
    }

    //
    while (!$queue->isEmpty()){
      $this->BFS_Ranked($sparql, $queue, $visited, $depth, true);
    }

    return $visited;
  }


  /**
   * [BFS_Ranked description]
   * @param [type] $sparql   [description]
   * @param [type] $queue    [description]
   * @param [type] $visited  [description]
   * @param [type] $depth    [description]
   * @param [type] $finalize [description]
   */
  private function BFS_Ranked($sparql, &$queue, &$visited, &$depth, $finalize) {

    //
    $currentNode = $queue->dequeue();
    $currentNodeHash = md5($currentNode);

    //
    $currentNodeScore = $visited[$currentNodeHash]->getScore();
    $currentNodeFinalScore = $currentNodeScore / $visited[$currentNodeHash]->getDepth();
    $visited[$currentNodeHash]->setScore($currentNodeFinalScore);

    //
    if($finalize){
      return true;
    }

    // Get next set of collaborators
    $result = $sparql->query(
    'SELECT ?startname ?endname (count(?name) as ?link_strength) '.
    'WHERE '.
    '{ '.
      '?start ont:name "'.$currentNode.'". '.
      '?start ont:name ?startname. '.
      '?end ont:name ?endname. '.
      '?start ont:contributorOn ?mid. '.
      '?mid ont:hasContributor ?end. '.
      '?mid ont:repostoryName ?name. '.
      'FILTER NOT EXISTS '.
      '{ '.
        '?end ont:name ?startname. '.
      '} '.
    '}GROUP BY ?startname ?endname '.
    'LIMIT 1000'
    );

    $parentScore = $currentNodeFinalScore;
    $newDepth = $depth + 1;

    //
    for ($i = 0; $i < count($result); $i++) {
      $nodeHash = md5($result[$i]->endname->getValue());
      if(!array_key_exists($nodeHash, $visited)){
        $visited[$nodeHash] = new RankedNode($nodeHash,$result[$i]->endname->getValue(), $newDepth, $parentScore + 1);
        $queue->enqueue($result[$i]->endname->getValue());
      }
      else{
        $visited[$nodeHash]->conditionalAddScore($result[$i]->link_strength->getValue(),$newDepth);
      }
    }

    //
    if($visited[$currentNodeHash]->isLast()){
      $visited[$nodeHash]->setLast(true);
      $depth++;
    }

    return true;
  }

  /**
   * [sendResponse description]
   * @param [type]  $list    [description]
   * @param [type]  $page    [description]
   * @param [type]  $perPage [description]
   * @param [type]  $total   [description]
   * @param string  $message [description]
   * @param integer $code    [description]
   */
  private function sendResponse($list, $page, $perPage, $total, $message = '', $code = 200) {
    //
    $link = array(array(
      'rel' => 'self',
      'href' => $this->getRequest()->getUri()
    ));
    if($page !== 1 && $total !== 0){
      $href = preg_replace("/\b(?!per)\w*page=[\d+]\b/", 'page='.($page+1), $this->getRequest()->getUri());
      $link[] = array(
        'rel' => 'previous',
        'href' => $href
      );
    }
    if(($page-1) * $perPage > $total){
      $href = preg_replace("/\b(?!per)\w*page=[\d+]\b/", 'page='.($page-1), $this->getRequest()->getUri());
      $link[] = array(
        'rel' => 'next',
        'href' => $href
      );
      }

    //
    $result = array(
    'meta' => array(
    'status' => $code,
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'link' => $link,),
    'data' => array(
    'message' => $message,
    'contributor' => $list
    )
    );
    
    //
    $view = View::create()->setStatusCode($code)->setData($result)->setFormat('json');
    return $this->get('fos_rest.view_handler')->handle($view);
  }
}
