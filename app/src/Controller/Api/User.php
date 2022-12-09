<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Core\Utils;
use DS\Model\Import;
use DS\Model\User as UserModel;
use DS\Model\Order;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\BSON\Timestamp;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class User
 * @package DS\Controller\Api
 */
final class User extends ApiController
{
  private $processed = 0;
  private $broken = 0;
  private $rowId = null;

  /**
   * Default controller construct
   * @param \Slim\Container $c Slim App Container
   *
   * @throws \Interop\Container\Exception\ContainerException
   */
  public function __construct(\Slim\Container $c)
  {
    parent::__construct($c);
    $this->model = new UserModel($c->mongodb);
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
    $user = $this->model->getOneWhere(['_id' => $args['id']]);
    $orders = (new Order($this->mongodb))->allWhere(['user_id' => new ObjectID($args['id'])]);

    return $response->withJson(array_merge((array)$user, ['orders' => $orders]));
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

    $filter = $this->filterFromQuery();

    $search = [];
    if (!empty($filter['search'])) {
      $input = new Regex($filter['search'], 'i');
      $search = array_fill_keys($this->model->search_fields, $input);
      $users = (new UserModel($this->mongodb))->find([
        '$or' => array_map(function ($key) use ($input) {
          return [$key => $input];
        }, ['first_name', 'last_name', 'email'])
      ]);
      if (!empty($users)) {
        $search['user_id'] = ['$in' => array_map(function ($user) {
          return $user->_id;
        }, $users)];
      }
    }

    $query = $this->request->getQueryParams();
    if ($filter['page']) $filter['offset'] = ($filter['page'] - 1) * $filter['limit'];

    return $response->withJson($this->model->all(
      $filter['limit'],
      $filter['offset'],
      $search
    ));
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

    $user = $this->model->getOneWhere(['_id' => $entity_id]);

    if (!$user)
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    if (!empty($d['password']))
      $d['password'] = Utils::hashPassword($d['password']);

    if (!empty($d))
      $this->model->updateWhere($d, ['_id' => $entity_id]);

    return $response->withJson(
      $this->model->getOneWhere(['_id' => $entity_id])
    );
  }

  public function importAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $import = new Import($this->mongodb);
    try {
      $fileName = __DIR__ . '/../../../../private/users/list.csv';
      if (!file_exists($fileName))
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'file is absent');

      $this->rowId = $import->startNewImport('users');

      $this->parseListingCsv($fileName);

      $import->stopImport($this->rowId);
      $this->logger->info('users import ended');
    } catch (\Exception $e) {
      $this->logger->error('users import failed');
      $this->logger->error($e->getMessage());

      $import->stopImport($this->rowId, Import::STATUS_ERROR);

      return $this->errorResponse($response, 1002, 'users import failed');
    }

    return $this->emptyJson($response);
  }
  private function parseListingCsv($fileName)
  {
    $this->logger->info('listing downloaded, start parsing');

    if (($handle = fopen($fileName, 'r')) !== FALSE) {
      try {
        $columns = fgetcsv($handle, 3000, ",");

        while (($data = fgetcsv($handle, 3000, ",")) !== FALSE) {
          $this->parseRecord(array_combine($columns, $data));
        }
      } catch (\Exception $e) {
        $this->logger->error(' parse failed');
        $this->logger->error(' ' . $e->getMessage());
      } finally {
        fclose($handle);
      }
    }

    (new Import($this->mongodb))->updateStatus($this->rowId, $this->processed, $this->broken);
    $this->logger->info('  parsing ended');
  }
  private function parseRecord($record)
  {
    $this->processed++;

    if ($this->processed % 2000 == 0) {
      $this->logger->info("already parsed {$this->processed} records..");
      (new Import($this->mongodb))->updateStatus($this->rowId, $this->processed, $this->broken);
    }

    $User = new UserModel($this->mongodb);
    $user = $User->getOneWhere(['email' => $record['Email']]);
    if (!empty($user)) return;

    try {
      $User->insertOne([
        'first_name' => $record['First Name'],
        'last_name' => $record['Last Name'],
        'email' => $record['Email'],
        'password' => Utils::getRandomPassword(),
        'sex' => '',
        'created' => new Timestamp(0, time()),
        'customer_id' => $record['Customer ID'],
        'company' => $record['Company'],
        'phone' => $record['Phone'],
        'address' => $record['Address'],
        'address2' => $record['Address 2'],
        'city' => $record['City'],
        'state' => $record['State/Province'],
        'country' => $record['Country'],
        'zip' => $record['Zip'],
      ]);
    } catch (\Exception $e) {
      $this->broken++;
    }
  }
}
