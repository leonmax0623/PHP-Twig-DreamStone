<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Core\Utils;
use DS\Model\Diamond;
use DS\Model\Product as ProductModel;
use DS\Model\Category;
use DS\Model\JewelryType;
use DS\Model\Matching;
use DS\Model\Vendor;
use MongoDB\BSON\Regex;
use MongoDB\BSON\ObjectID;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Product
 * @package DS\Controller\Api
 */
final class Product extends ApiController
{
  /**
   * Default controller construct
   * @param \Slim\Container $c Slim App Container
   *
   * @throws \Interop\Container\Exception\ContainerException
   */
  public function __construct(\Slim\Container $c)
  {
    parent::__construct($c);
    $this->model = new ProductModel($c->mongodb);
  }


  /**
   * Get all entities
   *
   * @param Request $request
   * @param Response $response
   * @param          $args
   *
   * @return Response
   * @throws \Exception
   */
  public function allAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $filter = $this->filterFromQuery();
    $category_id = $request->getQueryParam('category_id');
    $jewelrytype_id = $request->getQueryParam('jewelrytype_id');

    $search = empty($filter['search']) ? [] : array_fill_keys($this->model->search_fields, new Regex($filter['search'], 'i'));
    if ($filter['page'])
      $filter['offset'] = ($filter['page'] - 1) * $filter['limit'];

    if ($category_id)
      $search['$and'] = ['category_id' => new ObjectID($category_id)];

    if ($jewelrytype_id)
      $search['$and'] = ['jewelrytype_id' => new ObjectID($jewelrytype_id)];

    $res = $this->model->all($filter['limit'], $filter['offset'], $search);
    $res['records'] = array_map(function ($product) {
      $this->model->populate($product);
      $product->url = $this->model->getPermalink($product);
      return $product;
    }, $res['records']);

    return $response->withJson($res);
  }

  public function exportAction(Request $request, Response $response, $args)
  {
    set_time_limit(3600);
    // ini_set('memory_limit', '16G');
    $this->request = $request;

    $origin = $request->getUri()->getBaseUrl();
    $group = $request->getQueryParam('group');
    if (!in_array($group, ['products', 'diamonds']))
      return $response->withStatus(404)->withJson(['status' => 'not found']);

    $list = [];
    switch ($group) {
      case 'products':
        $list = $this->model->getToExport([], $origin);
        break;
      case 'diamonds':
        $vendors = (new Vendor($this->mongodb))->getEnabledVendors();
        $fileName = $this->settings['logger']['path'] . date("YmdHis") . '.csv';
        if (($handle = fopen($fileName, "w")) !== FALSE) {
          $limit = 10000;
          $offset = 0;
          $fillKeys = true;
          foreach ($vendors as $vendor) {
            do {
              $list = (new Diamond($this->mongodb))->getToExport([
                '$and' => [
                  'isEnabled' => true,
                  'vendor' => $vendor->code,
                ]
              ], $origin, $limit, $offset, $fillKeys);
              $fillKeys = false;
              foreach ($list as $row) {
                if (!empty($row)) fputcsv($handle, $row);
              }
              if (count($list) >= $limit - 1) {
                $offset += $limit;
              } else {
                $offset = 0;
                break;
              }
              unset($list);
            } while ($offset);
          }
          fclose($handle);
          unset($list);
          if ($fileHandle = fopen($fileName, 'rb')) {
            $streamFileContents = new \Slim\Http\Stream($fileHandle);
            $output = $response
              ->withHeader('Content-Type', 'application/download')
              // ->withHeader('Content-Type', mime_content_type($fileName))
              ->withHeader('Content-Transfer-Encoding', 'binary')
              ->withHeader('Expires', '0')
              ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
              ->withHeader('Pragma', 'public')
              ->withHeader('Content-Length', filesize($fileName))
              ->withBody($streamFileContents);
            return $output;
          }
        }
        // $list = (new Diamond($this->mongodb))->getToExport(['$and' => [
        //   'isEnabled' => true,
        //   '$or' => array_map(function ($vendor) {
        //     return ['vendor' => $vendor->code];
        //   }, $vendors),
        // ]], $origin);
        break;
      default:
        return $response->withStatus(404)->withJson(['status' => 'not found']);
    }

    Utils::downloadSendHeaders($group . '_export_' . date('Y-m-d') . '.csv');
    echo Utils::arrayToCsv($list);

    exit();
  }

  /**
   * @param $d
   * @param array $uploadedFiles
   * @param null $entity_id
   * @throws \Exception
   */
  protected function doUploadFile(&$d, array $uploadedFiles, $entity_id = null): void
  {
    $basePath = "";

    if ($this->settings['images']['from_root'])
      $basePath .= $_SERVER['DOCUMENT_ROOT'];

    $basePath .= $this->settings['images']['items']['filesystem'];
    if (!file_exists($basePath))
      mkdir($basePath);

    $basePath .= $entity_id . '/';
    $webPath = $this->settings['images']['items']['web'] . $entity_id . '/';

    if (!file_exists($basePath))
      mkdir($basePath);

    if (!file_exists($basePath . 'attributes/'))
      mkdir($basePath . 'attributes/');

    if (!file_exists($basePath . 'banner/'))
      mkdir($basePath . 'banner/');

    if (!file_exists($basePath . 'customer/'))
      mkdir($basePath . 'customer/');

    $item = $this->model->getOneWhere(['_id' => $entity_id]);

    if (!$item->images)
      $item->images = [];

    $imageShapes = json_decode($this->request->getParsedBodyParam('imageShapes', '{}'), true);
    foreach ($uploadedFiles as $key => $f) {
      $filename = str_replace(' ', '_', $f->getClientFilename());

      if (strpos($key, 'file_attr_') === 0) {
        $parts = explode('_', $key);
        $attrValueId = $parts[2];

        if (!file_exists($basePath . 'attributes/' . $attrValueId . '/'))
          mkdir($basePath . 'attributes/' . $attrValueId . '/');

        $f->moveTo($basePath . 'attributes/' . $attrValueId . '/' . $filename);

        foreach ($item->attributes as &$attribute)
          foreach ($attribute->values as &$value)
            if ($attrValueId === $value->_id) {
              $obj = (object) [
                'type' => 'img',
                'value' => $webPath . 'attributes/' . $attrValueId . '/' . $filename,
              ];
              if (!empty($imageShapes[$key]))
                $obj->shapes = array_map(function ($imageShape) {
                  $imageShape['_id'] = new ObjectId($imageShape['_id']);
                  return $imageShape;
                }, $imageShapes[$key]);
              $value->images[] = $obj;
            }

        continue;
      }

      if (strpos($key, 'file_customer_') === 0) {
        $f->moveTo($basePath . 'customer/' . $filename);
        $item->customer_images[] = (object) [
          'type' => 'img',
          'value' => $webPath . 'customer/' . $filename,
          'text' => '',
        ];
        continue;
      }

      if ($key === 'file_banner') {
        $f->moveTo($basePath . 'banner/' . $filename);
        $item->banner_image = $webPath . 'banner/' . $filename;
        continue;
      }

      if (in_array($webPath . $filename, array_map(function ($image) {
        return $image->value;
      }, $item->images))) continue;

      $f->moveTo($basePath . $filename);
      $obj = (object) [
        'type' => 'img',
        'value' => $webPath . $filename,
      ];
      if (!empty($imageShapes[$key]))
        $obj->shapes = array_map(function ($imageShape) {
          $imageShape['_id'] = new ObjectId($imageShape['_id']);
          return $imageShape;
        }, $imageShapes[$key]);
      $item->images[] = $obj;
    }

    $this->model->updateWhere([
      'attributes' => $item->attributes,
      'banner_image' => $item->banner_image,
      'images' => $item->images,
      'customer_images' => $item->customer_images,
    ], ['_id' => $entity_id]);
  }

  public function getBySkuAction(Request $request, Response $response, $args)
  {
    $product = $this->model->getOneWhere(['sku' => new Regex($args['sku'], 'i')]);
    if ($product) {
      $product->category = (new Category($this->mongodb))->findById($product->category_id);
      $product->jewelryType = (new JewelryType($this->mongodb))->findById($product->jewelrytype_id);
    }
    return $response->withJson($product);
  }

  public function getMultipleBySkuAction(Request $request, Response $response, $args)
  {
    $products = $this->model->allWhere(['sku' => new Regex('^' . $args['sku'] . '$', 'i')]);
    if ($products) {
      foreach ($products as &$product) {
        $product->category = (new Category($this->mongodb))->findById($product->category_id);
        $product->jewelryType = (new JewelryType($this->mongodb))->findById($product->jewelrytype_id);
      }
    }
    return $response->withJson($products);
  }

  public function updateAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $uploadMode = 'multipart/form-data' == $request->getMediaType();

    if ($uploadMode)
      $d = json_decode($request->getParsedBodyParam('json', '{}'), true);
    else
      $d = $request->getParsedBody();

    $uploadedFiles = $request->getUploadedFiles();

    if (empty($d) && empty($uploadedFiles))
      return $this->errorResponse(
        $response,
        ApiController::NOTHING_TO_UPDATE,
        'at least one field should be changed' . ($uploadMode ? ' or file upload' : '')
      );

    $entity_id = $args['id'];

    if (!$this->model->isExistWhere(['_id' => $entity_id]))
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    if (!empty($uploadedFiles['file']) && $uploadedFiles['file']->getError() !== UPLOAD_ERR_OK) {
      return $this->errorResponse($response, ApiController::UPLOAD_ISSUE, 'cannot upload file');
    }

    $Matching = new Matching($this->mongodb);

    $exists = $Matching->getOneWhere(["items" => ['$elemMatch' => ['$eq' => $entity_id]]]);
    $matchingItems = [];
    if (!empty($d['matchingItems']) && count($d['matchingItems'])) {
      $matchingItems = array_merge($d['matchingItems'], [$entity_id]);
      sort($matchingItems);
      $matchingItems = array_values(array_unique($matchingItems));

      unset($d['matchingItems']);
    }

    if (empty($exists)) {
      $newId = $Matching->insertOne([
        'items' => $matchingItems,
      ])->getInsertedId()->__toString();
    } else {
      $Matching->updateWhere(
        ['items' => $matchingItems],
        ['_id' => $exists->_id]
      );
    }

    $this->model->updateWhere($d, ['_id' => $entity_id]); // NOTE: should be before doUploadFile
    if (!empty($uploadedFiles)) {
      $this->doUploadFile($d, $uploadedFiles, $entity_id);
    }

    return $response->withJson($this->getById($entity_id));
  }

  public function getById($id)
  {
    $product = $this->model->getOneWhere(['_id' => $id]);

    $Matching = new Matching($this->mongodb);

    $matchingItems = $Matching->getOneWhere(["items" => ['$elemMatch' => ['$eq' => $id]]]);

    $matchingProducts = [];
    if (!empty($matchingItems)) {
      foreach ($matchingItems->items as $matchingItem) {
        if ($matchingItem != $id) {
          $matchingProduct = $this->model->getOneWhere(['_id' => $matchingItem]);
          if ($matchingProduct) {
            $matchingProduct->category = (new Category($this->mongodb))->findById($matchingProduct->category_id);
            $matchingProduct->jewelryType = (new JewelryType($this->mongodb))->findById($matchingProduct->jewelrytype_id);
            $matchingProducts[] = $matchingProduct;
          }
        }
      }

      if (!empty($matchingProducts)) {
        $product->matchingItems = $matchingItems->items;
        $product->matchingProducts = $matchingProducts;
      }
    } else {
      $product->matchingItems = [];
      $product->matchingProducts = [];
    }

    return $product;
  }

  public function getAction(Request $request, Response $response, $args)
  {
    return $response->withJson($this->getById($args['id']));
  }
}
