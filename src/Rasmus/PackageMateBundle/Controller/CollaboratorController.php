<?php

namespace Rasmus\PackageMateBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View as View;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations\QueryParam;

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
    $view = View::create()
    ->setStatusCode(200)
    ->setData(array('query'=>$query))
    ->setFormat('json');
    return $this->get('fos_rest.view_handler')->handle($view);
  }
}
