<?php

namespace Rasmus\PackageMateBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View as View;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations\QueryParam;

use EasyRdf\Sparql\Client as SPARQL_CLient;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class CollaboratorController extends Controller
{
  /**
  * @Rest\View
  * @QueryParam(name="query", description="Query param")
  */
  public function getAction(ParamFetcher $paramFetcher)
  {
    $query = $paramFetcher->get('query');

    EasyRdf_Namespace::set('category', 'http://dbpedia.org/resource/Category:');
    EasyRdf_Namespace::set('dbpedia', 'http://dbpedia.org/resource/');
    EasyRdf_Namespace::set('dbo', 'http://dbpedia.org/ontology/');
    EasyRdf_Namespace::set('dbp', 'http://dbpedia.org/property/');
    $sparql = new EasyRdf_Sparql_Client('http://localhost:8080/openrdf-workbench/repositories/repo1/query?query=');
    $result = $sparql->query(
    'SELECT * WHERE {'.
      '  ?country rdf:type dbo:Country .'.
      '  ?country rdfs:label ?label .'.
      '  ?country dc:subject category:Member_states_of_the_United_Nations .'.
      '  FILTER ( lang(?label) = "en" )'.
      '} ORDER BY ?label'
    );
    // foreach ($result as $row) {
    //   echo "<li>".link_to($row->label, $row->country)."</li>\n";
    // }


    $view = View::create()
    ->setStatusCode(200)
    ->setData(array('query'=>$query))
    ->setFormat('json');
    return $this->get('fos_rest.view_handler')->handle($view);
  }
}
