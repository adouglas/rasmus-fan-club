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

class CollaboratorsController extends Controller {
  /**
  * @Rest\View
  * @QueryParam(name="package", description="Packagist package from which to start the query")
  */
  public function getAction(ParamFetcher $paramFetcher) {

    $startPackage = $paramFetcher->get('package');

    // Initial query returning the current contributers for the initial package (startPackage)
    try {
      \EasyRdf_Namespace::set('ont', 'http://adouglas.github.io/onto/php-packages.rdf#');
      $sparql = new \EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?query=');
      $result = $sparql->query(
      'SELECT ?contributer ' .
      'WHERE'.
      '{ '.
        '?startPackage ont:packageName "'.$startPackage.'". '.
        '?startPackage ont:hasRepository ?sourceRepo. '.
        '?sourceRepo ont:hasCollaborator ?c. '.
        '?c ont:name ?contributer' .
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

    for ($i = 0; $i < count($result); $i++) {
      unset($results[md5($result[$i]->contributer->getValue())]);
    }

    uasort($results,function($a, $b) {
      if ($a->getScore() == $b->getScore()) {
        return 0;
      }
      return ($a->getScore() < $b->getScore()) ? 1 : -1;
    });


    $list = array();

    $order = 1;

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
    return $this->sendResponse($list, 'Search complete');
  }


  private function search($initialCollaborators) {

    \EasyRdf_Namespace::set('ont', 'http://adouglas.github.io/onto/php-packages.rdf#');
    $sparql = new \EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?query=');

    $queue = new NodeQueue();

    $visited = array();

    $depth = 1;

    $maxDepth = 5;

    for ($i = 0; $i < count($initialCollaborators); $i++) {
      $collaborator = $initialCollaborators[$i]->contributer->getValue();
      $nodeHash = md5($collaborator);
      $queue->enqueue($collaborator);
      $visited[$nodeHash] = new RankedNode($nodeHash,$collaborator, 1, 1);
    }

    while (!$queue->isEmpty() && $depth < $maxDepth) {
        $this->BFS_Ranked($sparql, $queue, $visited, $depth, false);
    }

    while (!$queue->isEmpty()){
      $this->BFS_Ranked($sparql, $queue, $visited, $depth, true);
    }

    return $visited;
  }


  private function BFS_Ranked($sparql, &$queue, &$visited, &$depth, $finalize) {
    /**
    * [$currentNode description]
    * @var [type]
    */
    $currentNode = $queue->dequeue();

    $currentNodeHash = md5($currentNode);

    $currentNodeScore = $visited[$currentNodeHash]->getScore();
    $currentNodeFinalScore = $currentNodeScore / $visited[$currentNodeHash]->getDepth();
    $visited[$currentNodeHash]->setScore($currentNodeFinalScore);

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
      '?start ont:collaboratesOn ?mid. '.
      '?mid ont:hasCollaborator ?end. '.
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

    if($visited[$currentNodeHash]->isLast()){
      $visited[$nodeHash]->setLast(true);
      $depth++;
    }

    return true;
  }


  private function sendResponse($list, $message = '', $code = 200) {
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
    'contributers' => $list
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
