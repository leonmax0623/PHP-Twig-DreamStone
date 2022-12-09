<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Core\Utils;
use Interop\Container\Exception\ContainerException;
use MongoDB\BSON\Regex;
use Slim\Http\Request;
use Slim\Http\Response;
use DS\Model\Diamond;
use DS\Model\Product;
use DS\Model\Coupon;
use DS\Model\Category;
use DS\Model\MailTemplate;
use DS\Model\User;
use DS\Model\Color;
use DS\Model\Clarity;
use mongodb\BSON\ObjectID;

/**
 * Class Order
 * @package DS\Controller\Api
 */
final class Order extends ApiController
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
    $this->model = new \DS\Model\Order($c->mongodb);
  }

  /**
   * Get all entities
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   *
   * @return Response
   * @throws \Exception
   */
  public function allAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $User = new User($this->mongodb);
    $filter = $this->filterFromQuery();

    $search = [];
    if (!empty($filter['search'])) {
      $input = new Regex($filter['search'], 'i');
      $search = array_fill_keys($this->model->search_fields, $input);

      $orderNumber = intval($filter['search']);
      if (strval($orderNumber) === $filter['search']) {
        $search['number'] = $orderNumber;
      }

      $inputValues = preg_split("/\W+/", trim($filter['search']));

      $inputFind = [];
      if (count($inputValues) == 2) {
        $inputFind[] =
          [
            'first_name' => new Regex($inputValues[0], 'i'),
            'last_name' => new Regex($inputValues[1], 'i'),
          ];
        $inputFind[] =
          [
            'first_name' => new Regex($inputValues[1], 'i'),
            'last_name' => new Regex($inputValues[0], 'i'),
          ];
      } else {
        $inputFind = array_map(function ($key) use ($input) {
          return [$key => $input];
        }, ['first_name', 'last_name', 'email']);
      }

      $users = $User->find([
        '$or' => $inputFind,
      ]);
      if (!empty($users)) {
        $search['user_id'] = ['$in' => array_map(function ($user) {
          return $user->_id;
        }, $users)];
      }
    }
    $query = $this->request->getQueryParams();
    if (isset($query['isRush'])) {
      $search['rush'] = ['$exists' => true, '$ne' => ''];
    }
    if (!empty($query['from']))
      $search['created']['$gte'] = strtotime($query['from']);
    if (!empty($query['to']))
      $search['created']['$lt'] = strtotime($query['to']);
    if (!empty($query['s']))
      $search['status']['$in'] = explode(',', $query['s']);
    if (!empty($query['sort'])) {
      if (substr($query['sort'], 0, 1) == '-') {
        $sort = substr($query['sort'], 1);
        $direction = -1;
      } else {
        $sort = $query['sort'];
        $direction = 1;
      }
      $sort = [$sort => $direction];
    }
    if ($filter['page'])
      $filter['offset'] = ($filter['page'] - 1) * $filter['limit'];

    // $this->logger->info(json_encode($search));
    $res = $this->model->all(
      $filter['limit'],
      $filter['offset'],
      empty($search) ? [] : $search,
      // empty($search) ? [] : ['$and' => $search],
      // empty($search) ? [] : ['$and' => ['user_id' => ['$in' => array_map(function($user){ return $user->_id; }, $users)]]],
      '*',
      $sort ?? ['created' => -1]
    );

    $colors = (new Color($this->mongodb))->find();
    $clarities = (new Clarity($this->mongodb))->find();

    foreach ($colors as $color) $colorCodes[$color->_id->__toString()] = $color->code;
    foreach ($clarities as $clarity) $clarityCodes[$clarity->_id->__toString()] = $clarity->code;

    $res['records'] = array_map(function ($order) use ($User, $colorCodes, $clarityCodes) {
      $order->user = $User->getOneWhere(['_id' => $order->user_id]);
      unset($order->user_id);

      if ($order->diamonds) {
        foreach ($order->diamonds as &$diamond) {
          if ($diamond->color_id) {
            $diamond->colorCode = $colorCodes[$diamond->color_id];
          }
          if ($diamond->clarity_id) {
            $diamond->clarityCode = $clarityCodes[$diamond->clarity_id];
          }
        }
      }

      return $order;
    }, $res['records']);
    // $this->logger->info(json_encode($res));

    return $response->withJson($res);
  }

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
  public function getAction(Request $request, Response $response, $args)
  {
    $res = $this->model->getOneWhere(['_id' => $args['id']]);
    if ($res) {
      $res->user = (new User($this->mongodb))->getOneWhere(['_id' => $res->user_id]);
      unset($res->user_id);
    }

    $colors = (new Color($this->mongodb))->find();
    $clarities = (new Clarity($this->mongodb))->find();

    foreach ($colors as $color) $colorCodes[$color->_id->__toString()] = $color->code;
    foreach ($clarities as $clarity) $clarityCodes[$clarity->_id->__toString()] = $clarity->code;

    if ($res->diamonds) {
      foreach ($res->diamonds as &$diamond) {
        if ($diamond->color_id) {
          $diamond->colorCode = $colorCodes[$diamond->color_id];
        }
        if ($diamond->clarity_id) {
          $diamond->clarityCode = $clarityCodes[$diamond->clarity_id];
        }
      }
    }

    return $response->withJson($res);
  }

  /**
   * Update exists entity
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   *
   * @return Response
   *
   * @throws ContainerException
   */
  public function updateAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $uploadMode = 'multipart/form-data' == $request->getMediaType();

    if ($uploadMode)
      $d = json_decode($request->getParsedBodyParam('json', '{}'), true);
    else
      $d = $request->getParsedBody();

    if (empty($d))
      return $this->errorResponse($response, ApiController::NOTHING_TO_UPDATE, 'at least one field should be changed');

    $entity_id = $args['id'];

    $order = $this->model->getOneWhere(['_id' => $entity_id]);

    $oldStatus = $order->status;

    if (!$order)
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    $Diamond = new Diamond($this->mongodb);

    $products = $order->products;
    if (isset($d['products'])) {
      if (!is_array($d['products'])) {
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'products should be array');
      } else {
        $toChangeProducts = [];
        foreach ($d['products'] as $product) {
          if (!isset($product['action'])) continue;
          switch ($product['action']) {
            case 'ADD':
              $toChangeProducts[$product['_id']] = ['action' => 'ADD', 'qty' => $product['qty'], 'price' => $product['price']];
              break;
            case 'CHANGE':
              $toChangeProducts[$product['_id']] = ['action' => 'CHANGE', 'qty' => $product['qty'], 'price' => $product['price']];
              break;
            case 'DELETE':
              $toChangeProducts[$product['_id']] = ['action' => 'DELETE'];
              break;
          }
        }
        foreach ($products as $key => $product) {
          if (isset($toChangeProducts[$product->_id])) {
            if ($toChangeProducts[$product->_id]['action'] === 'DELETE') {
              unset($products[$key]);
            } else {
              $products[$key]->qty = $toChangeProducts[$product->_id]['qty'];
              $products[$key]->price = $toChangeProducts[$product->_id]['price'];
            }
          }
        }
        $d['products'] = array_values($products); // reset indexes because of "unset" used above
        foreach ($toChangeProducts as $productId => $toAdd) {
          if ($toAdd['action'] === 'ADD') {
            $product = (new Product($this->mongodb))->getOneWhere(['_id' => $productId]);
            if (!isset($product)) continue;
            $product->category = (new Category($this->mongodb))->getOneWhere(['_id' => $product->category_id]);
            $product->qty = $toAdd['qty'];
            $product->price = $toAdd['price'];
            $d['products'][] = $product;
          }
        }
      }
    }

    $diamonds = $order->diamonds;
    if (isset($d['diamonds'])) {
      if (!is_array($d['diamonds'])) {
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'diamonds should be array');
      } else {
        $toDelete = [];
        $toChangeDiamonds = [];
        foreach ($d['diamonds'] as $diamond) {
          if (!isset($diamond['action'])) continue;
          switch ($diamond['action']) {
            case 'ADD':
              $toChangeDiamonds[$diamond['_id']] = ['action' => 'ADD', 'qty' => $diamond['qty'], 'priceInternal' => $diamond['priceInternal']];
              break;
            case 'CHANGE':
              $toChangeDiamonds[$diamond['_id']] = ['action' => 'CHANGE', 'priceInternal' => $diamond['priceInternal']];
              break;
            case 'DELETE':
              $toDelete[] = $diamond['_id'];
              // Enabling removed from order diamonds in the stock
              $Diamond->updateWhere(['isEnabled' => true], ['_id' => $diamond['_id']]);
              break;
          }
        }
        foreach ($diamonds as $key => $diamond) {
          if (isset($toChangeDiamonds[$diamond->_id])) {
            if ($toChangeDiamonds[$diamond->_id]['action'] === 'DELETE') {
              unset($diamonds[$key]);
            } else {
              $diamonds[$key]->priceInternal = $toChangeDiamonds[$diamond->_id]['priceInternal'];
            }
          }
        }
        $diamonds = array_filter($diamonds, function ($diamond) use ($toDelete) {
          return !in_array($diamond->_id, $toDelete);
        });
        $d['diamonds'] = array_values($diamonds); // reset indexes because of "array_filter" used above
        foreach ($toChangeDiamonds as $diamondId => $toAdd) {
          if ($toAdd['action'] === 'ADD') {
            $diamond = (new Diamond($this->mongodb))->getOneWhere(['_id' => $diamondId]);
            if (!isset($diamond)) continue;
            $diamond->qty = $toAdd['qty'];
            $diamond->priceInternal = $toAdd['priceInternal'];
            $d['diamonds'][] = $diamond;
          }
        }
      }
    }

    $composites = $order->composite;
    if (isset($d['composite'])) {
      if (!is_array($d['composite'])) {
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'composites should be array');
      } else {
        $toDelete = [];
        foreach ($d['composite'] as $composite) {
          if (!isset($composite['action'])) continue;
          switch ($composite['action']) {
            case 'CHANGE':
              $toChangeComposites[$composite['product']['_id'] . $composite['diamond']['_id']] = [
                'action' => 'CHANGE',
                'productPrice' => $composite['product']['price'],
                'diamondPrice' => $composite['diamond']['priceInternal'],
              ];
              break;
            case 'DELETE':
              $toDelete[] = $composite;
              // Enabling removed from order diamonds in the stock
              $Diamond->updateWhere(['isEnabled' => true], ['_id' => $composite['diamond']['_id']]);
              break;
          }
        }
        foreach ($composites as $key => $composite) {
          $compositeKey = $composite->product->_id . $composite->diamond->_id;
          if (isset($toChangeComposites[$compositeKey])) {
            if ($toChangeComposites[$compositeKey]['action'] === 'CHANGE') {
              $composites[$key]->product->price = $toChangeComposites[$compositeKey]['productPrice'];
              $composites[$key]->diamond->priceInternal = $toChangeComposites[$compositeKey]['diamondPrice'];
            }
          }
        }
        $composites = array_filter($composites, function ($composite) use ($toDelete) {
          return empty(array_filter($toDelete, function ($toDel) use ($composite) {
            return Utils::isEqualProducts($composite->product, (object) $toDel['product'])
              && Utils::isEqualProducts($composite->diamond, (object) $toDel['diamond']);
          }));
        });
        $d['composite'] = array_values($composites); // reset indexes because of "array_filter" used above
      }
    }

    $productsSum = 0;
    foreach (empty($d['products']) ? $products : $d['products'] as $item) {
      $productsSum += $item->qty * $item->price;
    }
    foreach (empty($d['diamonds']) ? $diamonds : $d['diamonds'] as $item) {
      // Disabling reserved diamonds in the stock
      $Diamond->updateWhere(['isEnabled' => false], ['_id' => $item->_id]);
      $productsSum += $item->priceInternal;
    }
    foreach (empty($d['composite']) ? $composites : $d['composite'] as $item) {
      // Disabling reserved diamonds in the stock
      $Diamond->updateWhere(['isEnabled' => false], ['_id' => $item->diamond->_id]);
      $productsSum += $item->product->price + $item->diamond->priceInternal;
    }

    $d['amount'] = (array) $order->amount;
    $d['amount']['subtotal'] = $productsSum;
    // $d['coupon'] = $d['coupon'] ?? (array) $order->coupon ?? [];

    if (isset($d['coupon']) && isset($d['coupon']['code'])) {
      if (!$d['coupon']['code']) {
        $d['coupon'] = ['code' => ''];
      } else {
        $Coupon = new Coupon($this->mongodb);
        $coupon = $Coupon->findOne(['code' => new Regex('^' . $d['coupon']['code'] . '$', 'i')]);
        if ($coupon) {
          $d['coupon'] = [
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => $coupon->value
          ];
          $d['amount']['subtotal'] = $Coupon->applyDiscount($d['coupon']['code'], $productsSum);
        } else {
          unset($d['coupon']);
        }
      }
    }

    // Recalculate total
    $d['totalCorrection'] = $d['totalCorrection'] ?? (array) $order->totalCorrection;
    if (empty($d['totalCorrection'])) {
      $d['amount']['total'] =
        ($d['amount']['subtotal'] + $d['amount']['shipping'] + $d['amount']['tax']) *
        (1 - ($d['bankDiscount'] ?? $order->bankDiscount ?? 0) * 0.01);
    } else {
      switch ($d['totalCorrection']['type']) {
        case 'percent':
          $d['amount']['total'] =
            ($d['totalCorrection']['value'] * 0.01 + 1) *
            ($d['amount']['subtotal'] + $d['amount']['shipping'] + $d['amount']['tax']) *
            (1 - ($d['bankDiscount'] ?? 0) * 0.01);
          break;
        case 'fixed':
          $d['amount']['total'] =
            $d['totalCorrection']['value'] +
            ($d['amount']['subtotal'] + $d['amount']['shipping'] + $d['amount']['tax']) *
            (1 - ($d['bankDiscount'] ?? 0) * 0.01);
          break;
        default:
          $d['amount']['total'] =
            ($d['amount']['subtotal'] + $d['amount']['shipping'] + $d['amount']['tax']) *
            (1 - ($d['bankDiscount'] ?? 0) * 0.01);
      }
    }

    if (!empty($d['factoryLog'])) {
      $d['factoryLog']['totalCost'] = 0;
      foreach ((array) $d['factoryLog'] as $item) {
        $d['factoryLog']['totalCost'] += empty($item['value']) ? 0 : $item['value'];
      }
    }

    $d['factoryLog']['totalSale'] = $d['amount']['total'];
    $d['factoryLog']['profit'] = $d['factoryLog']['totalSale'] - $d['factoryLog']['totalCost'];
    $d['factoryLog']['profitPercent'] = round(
      $d['factoryLog']['totalCost']
        ? (100 * $d['factoryLog']['profit']) / $d['factoryLog']['totalCost']
        : 0,
      2
    );

    if (!empty($d)) {
      $this->model->updateWhere($d, ['_id' => $entity_id]);

      $uploadedFiles = $request->getUploadedFiles();
      if (!empty($uploadedFiles['file'])) { // NOTE: should be before doUploadFile
        $this->doUploadFile($d, $uploadedFiles, $entity_id);
      }

      $user = (new User($this->mongodb))->findOne(['_id' => new ObjectID($order->user_id)]);

      if (empty($user->notSendNotifications)) {
        $res = $this->model->getOneWhere(['_id' => $entity_id]);
        if ($res) {
          $res->user = (new User($this->mongodb))->getOneWhere(['_id' => $res->user_id]);
          unset($res->user_id);
        }

        $this->endConnection(json_encode($res));

        // sending "changed status" mail to user
        $sendStatusEmail = isset($d['statusChangeEmail']) ? $d['statusChangeEmail'] : $order->statusChangeEmail;
        if ($sendStatusEmail) {
          if (!empty($d['status']) && $oldStatus != $d['status']) {
            $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'user_order_status_changed']);
            $bodyTemplate = $template->body;
            $bodyData = [
              '%userFirstname%' => $user->first_name,
              '%userLastname%' => $user->last_name,
              '%orderNumber%' => $order->number,
              '%oldStatus%' => $oldStatus,
              '%newStatus%' => $d['status'],
            ];
            $email = $user->email;
            $subject = $template->subject;
            $mailResult = $this->mailer->send($bodyTemplate, $bodyData, function ($message) use ($email, $subject) {
              $message->to($email);
              $message->subject($subject);
            });
          }
        }
      }
    }

    $res = $this->model->getOneWhere(['_id' => $entity_id]);
    if ($res) {
      $res->user = (new User($this->mongodb))->getOneWhere(['_id' => $res->user_id]);
      unset($res->user_id);
    }

    return $response->withJson($res);
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

    $basePath .= $this->settings['images']['items']['filesystem'] . $entity_id . '/';
    $webPath = $this->settings['images']['items']['web'] . $entity_id . '/';

    if (!file_exists($basePath))
      mkdir($basePath);

    if ($entity_id) {
      $item = $this->model->getOneWhere(['_id' => $entity_id]);

      if (!$item->images)
        $item->images = [];

      foreach ($uploadedFiles as $key => $f) {
        $filename = str_replace(' ', '_', $f->getClientFilename());
        $f->moveTo($basePath . $filename);

        if (!in_array($webPath . $filename, $item->images))
          $item->images[] = $webPath . $filename;
      }

      $this->model->updateWhere(['images' => $item->images], ['_id' => $entity_id]);
    }
  }
}
