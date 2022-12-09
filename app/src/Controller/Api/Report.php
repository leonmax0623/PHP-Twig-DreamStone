<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use MongoDB\BSON\Regex;
use Slim\Http\Request;
use Slim\Http\Response;
use DS\Model\Order;
use mongodb\BSON\ObjectID;
use mongodb\BSON\UTCDateTime;
use DS\Core\Utils;

/**
 * Class User
 * @package DS\Controller\Api
 */
final class Report extends ApiController
{
  /**
   * Get one entity
   *
   * @param Request $request
   * @param Response $response
   * @param          $args
   *
   * @return Response
   *
   * @throws ContainerException
   */
  public function getOrdersAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $query = $this->request->getQueryParams();

    $match = [];
    if (!empty($query['from']))
      $match['created']['$gte'] = strtotime($query['from']);
    if (!empty($query['to']))
      $match['created']['$lt'] = strtotime($query['to']);

    $conditions = [];
    if (!empty($match))
      $conditions[] = ['$match' => $match];

    $conditions[] = ['$group' => [
      '_id' => '$status',
      'subtotals' => ['$sum' => '$amount.subtotal'],
      'shippings' => ['$sum' => '$amount.shipping'],
      'taxes' => ['$sum' => '$amount.tax'],
      'totals' => ['$sum' => '$amount.total'],
      'count' => ['$sum' => 1],
    ]];
    $conditions[] = ['$sort' => ['totals' => -1]];

    $report = (new Order($this->mongodb))->aggregate($conditions);

    $total = [
      '_id' => 'Total',
      'subtotals' => 0,
      'shippings' => 0,
      'taxes' => 0,
      'totals' => 0,
      'count' => 0,
    ];
    foreach ($report as $row) {
      if (in_array($row->_id, ['Canceled', 'Returned'])) {
        $total['subtotals'] -= $row->subtotals;
        $total['shippings'] -= $row->shippings;
        $total['taxes'] -= $row->taxes;
        $total['totals'] -= $row->totals;
        $total['count'] -= $row->count;
      } else {
        $total['subtotals'] += $row->subtotals;
        $total['shippings'] += $row->shippings;
        $total['taxes'] += $row->taxes;
        $total['totals'] += $row->totals;
        $total['count'] += $row->count;
      }
    }

    $report = (array) $report;
    $report[] = $total;

    return $response->withJson(['report' => $report]);
  }
}
