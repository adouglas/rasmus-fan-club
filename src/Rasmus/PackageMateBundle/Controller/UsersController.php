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
  * @QueryParam(name="query", description="Query param")
  */
  public function getAction(ParamFetcher $paramFetcher)
  {

    $queryString = $paramFetcher->get('query');

    $query=json_decode($queryString);

    var_dump($queryString);
    var_dump($query);
    die();


    \EasyRdf_Namespace::set('ont', 'http://adouglas.github.io/onto/php-packages.rdf#');
    $sparql = new \EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?query=');
    $result = $sparql->query(
    'ASK' .
    '{'.
      '?start ont:name "'.$query["user1"].'".'.
      '?end ont:name "'.$query["user2"].'".'.
      '?start (ont:collaboratesOn/ont:hasCollaborator)* ?end.'.
    '}'
    );
    // foreach ($result as $row) {
    //   echo "<li>".link_to($row->label, $row->country)."</li>\n";
    // }

    $view = View::create()
    ->setStatusCode(200)
    ->setData(array('query'=>$queryString,'result'=>$result))
    ->setFormat('json');
    return $this->get('fos_rest.view_handler')->handle($view);
  }
}
