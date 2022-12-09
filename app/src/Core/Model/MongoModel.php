<?php

namespace DS\Core\Model;

use mongodb\BSON\ObjectID;
use mongodb\BSON\Decimal128;
use mongodb\BSON\Timestamp;

/**
 * Class MongoModel
 * @package DS\Core\Model
 */
class MongoModel
{
  /**
   * Field name to auto set a created timestamp
   */
  const FIELD_CREATE = 'created_at';

  /**
   * Field name to auto set an udpated timestamp
   */
  const FIELD_UPDATE = 'updated_at';
  /**
   * @var
   */
  public $search_fields;
  /**
   * @var string database name
   */
  protected $database = 'DreamStone';
  /**
   * @var string collection name
   */
  protected $collection;
  /**
   * @var array field list with type (_id is not compulsory)
   *            ex:
   *              [
   *                  'name' => [
   *                      'index' => 1,
   *                      'unique' => true,
   *                  ],
   *                  'phone' => [
   *                      'index' => -1,
   *                  ],
   *              ]
   */
  protected $indexes = [];
  /**
   * @var array Validate array for collection creation (Required fields)
   * @see http://mongodb.github.io/mongo-php-library/classes/database/#createcollection
   * @see https://docs.mongodb.com/manual/core/document-validation/
   */
  protected $validator = [];
  /**
   * @var \MongoDB\Database
   */
  protected $mongodb;
  /**
   * @var \MongoDB\Collection
   */
  protected $mongoCol;

  /**
   * Model constructor.
   *
   * @param \MongoDB\Client|\MongoDB\Database|\MongoDB\Collection $mongo
   *
   * @throws \Exception
   */
  public function __construct($mongo)
  {
    switch (get_class($mongo)) {
      case 'MongoDB\Client':
        if (isset($this->database) && !empty($this->database)) {
          $this->mongodb = $mongo->selectDatabase($this->database);
          $this->initCollection();
        } else {
          throw new \Exception('No database name. Please Set $database or provide a \MongoDB\Database.');
        }
        break;
      case 'MongoDB\Database':
        $this->mongodb = $mongo;
        $this->database = $this->mongodb->getDatabaseName();
        $this->initCollection();
        break;
      case 'MongoDB\Collection':
        $this->mongoCol = $mongo;
        $this->database = $this->mongoCol->getDatabaseName();
        $this->collection = $this->mongoCol->getCollectionName();
        break;
      default:
        throw new \Exception(
          'Invalid provided object (' . get_class($mongo) . ').' .
            'Must be instance of \MongoDB\Client or \MongoDB\Database'
        );
        break;
    }
  }

  /**
   * Initialize Mongo Collection from Mongo Database
   *
   * @throws \Exception
   */
  protected function initCollection()
  {
    if (isset($this->collection) && !empty($this->collection)) {
      $this->mongoCol = $this->mongodb->selectCollection($this->collection);
      $this->collection = $this->mongoCol->getCollectionName();
    } else {
      throw new \Exception('No collection name. Please Set $collection or provide a \MongoDB\Collection.');
    }
  }

  /**
   * Do check: current collection is exist in MongoDB?
   *
   * @return bool
   */
  public function isCollectionExists()
  {
    $colections = $this->mongodb->listCollections(['filter' => ['name' => $this->collection]]);

    foreach ($colections as $colection) {
      return true;
    }

    return false;
  }

  /**
   * Insert multiple documents into current collection.
   *
   * @param array $documents
   * @param array $options
   * @return \MongoDB\InsertManyResult
   */
  public function insertMany(array $documents, array $options = [])
  {
    return $this->mongoCol->insertMany($documents, $options);
  }

  /**
   * Finds a single document matching the query and updates it.
   *
   * @param array|object $filter The filter criteria that specifies the documents to update.
   * @param array|object $update Specifies the field and value combinations to update and any relevant update operators. $update uses MongoDB’s update operators
   * @param array $options Optional. An array specifying the desired options.
   * @return null|object
   */
  public function findOneAndUpdate($filter, $update, array $options = []): ?object
  {
    return $this->mongoCol->findOneAndUpdate($filter, $update, $options);
  }

  /**
   * Deletes all documents that match the filter criteria.
   *
   * @param $filter
   * @param array $options
   * @return \MongoDB\InsertOneResult
   */
  public function deleteMany($filter, array $options = [])
  {
    return $this->mongoCol->deleteMany($filter, $options);
  }

  /**
   * Insert one document.
   *
   * @param $document
   * @param array $options
   * @return \MongoDB\InsertOneResult
   */
  public function insertOne($document, array $options = [])
  {
    try {
      return $this->mongoCol->insertOne($document, $options);
    } catch (\Exception $e) {

      throw $e;
    }
  }

  /**
   * Update at most one document that matches the filter criteria.
   * If multiple documents match the filter criteria, only the first matching document will be updated.
   *
   * @param $filter
   * @param $update
   * @param array $options
   * @return MongoDB\UpdateResult
   */
  public function updateOne($filter, $update, array $options = []): \MongoDB\UpdateResult
  {
    return $this->mongoCol->updateOne($filter, $update, $options);
  }

  /**
   * Modifies an existing document or documents in a collection. The method can modify specific fields of an existing
   * document or documents or replace an existing document entirely, depending on the update parameter.
   *
   * @param $filter
   * @param $update
   * @param array $options
   * @return \MongoDB\UpdateResult
   */
  public function updateMany($filter, $update, array $options = []): \MongoDB\UpdateResult
  {
    return $this->mongoCol->updateMany($filter, $update, $options);
  }

  /**
   * Creates indexes from $this->fields definition
   * For multi indexes, override this method
   *
   * @throws \Exception
   */
  public function createIndexes()
  {
    $this->indexes += [
      self::FIELD_CREATE => 1,
      self::FIELD_UPDATE => 1,
    ];

    // composite text index
    if (isset($this->textIndex)) {
      $this->mongoCol->createIndex($this->textIndex);
    }

    // single indexes
    foreach ($this->indexes as $field => $opt) {
      if (isset($opt['index'])) {
        $indexOpt = [];
        if (isset($opt['unique']) && $opt['unique'] === true) {
          $indexOpt = ['unique' => true];
        }
        $this->mongoCol->createIndex([$field => $opt['index']], $indexOpt);
      }
    }
  }

  /**
   * Create collection using validator
   */
  public function createCollection()
  {
    $option = [];
    if (isset($this->validator) && !empty($this->validator)) {
      $option['validator'] = $this->validator;
      $option['validationAction'] = "error";
      $option['validationLevel'] = "strict";
    }

    return $this->mongodb->createCollection($this->collection, $option);
  }

  /**
   * Drop collection
   */
  public function dropCollection()
  {
    var_dump($this->collection);
    return $this->mongodb->dropCollection($this->collection);
  }

  /**
   * Update or insert a document or a part of a document
   * Update fields keeping existing values
   *
   * @param array $data
   * @param string|array $uspertField Field name to upsert or array of field name
   * @param bool $updateMostRecent If true => update only if updated_at passed to $data is the most recent
   *                                       In this case, $data['updated_at'] is compulsory
   *                                       If $updateMostRecent == true && !isset($data['updated_at']) => no updates performed
   *
   * @return \MongoDB\UpdateResult
   */
  public function upsert($data, $uspertField = null, $updateMostRecent = false)
  {
    $filter = [];
    if (is_array($uspertField)) {
      foreach ($uspertField as $field) {
        if (isset($data[$field])) {
          $filter[$field] = $data[$field];
        }
      }
    } else {
      if (isset($data[$uspertField])) {
        $filter = [$uspertField => $data[$uspertField]];
      }
    }

    $doc = $this->findOne($filter);
    if ($doc !== null) {
      if ($updateMostRecent) {
        if (isset($data['updated_at']) && $data['updated_at'] > $doc['updated_at']) {
          $data += (array)$doc;
        } else {
          return false;
        }
      } else {
        $data += (array)$doc;
      }
    } else {
      if (!isset($data[self::FIELD_CREATE]) || empty($data[self::FIELD_CREATE])) {
        $data[self::FIELD_CREATE] = time();
      }
    }
    if (!isset($data[self::FIELD_UPDATE]) || empty($data[self::FIELD_UPDATE])) {
      $data[self::FIELD_UPDATE] = time();
    }

    return $this->mongoCol->replaceOne($filter, $data, ['upsert' => true]);
  }

  /**
   * Mongo Collection findOne alias
   *
   * @param array $filter
   * @param array $options
   *
   * @return array|null|object
   */
  public function findOne($filter = [], array $options = [])
  {
    return $this->mongoCol->findOne($filter, $options);
  }

  /**
   * Find document by _id
   *
   * @param integer $id
   * @param array $options
   *
   * @return array|null|object
   */
  public function findById($id, array $options = [])
  {
    if (is_object($id) && get_class($id) === 'MongoDB\BSON\ObjectID') {
      $_id = $id;
    } else {
      $_id = new ObjectID($id);
    }
    $find = ['_id' => $_id];

    return $this->findOne($find, $options);
  }

  /**
   * Return the MongoCollection instance
   *
   * @return \MongoDB\Collection
   */
  public function getCollection()
  {
    return $this->mongoCol;
  }

  /**
   * Gets the number of documents matching the filter.
   *
   * @param array|object $filter Query by which to filter documents
   * @param array $options Command options
   * @return integer
   * @throws UnexpectedValueException if the command response was malformed
   * @throws UnsupportedException if options are not supported by the selected server
   * @throws InvalidArgumentException for parameter/option parsing errors
   * @throws DriverRuntimeException for other driver errors (e.g. connection errors)
   *
   * @see Count::__construct() for supported options
   * @deprecated 1.4
   */
  public function count($filter = [], array $options = [])
  {
    return $this->mongoCol->count($filter, $options);
  }
  public function countDocuments($filter = [], array $options = [])
  {
    return $this->mongoCol->countDocuments($filter, $options);
  }

  /**
   * @param null $search
   * @param string|array $fields
   * @return array
   * @throws \Exception
   */
  public function allWhere($search = [], $limit = null, $offset = null)
  {
    $options = [];
    if ($limit)
      $options['limit'] = $limit;

    if ($offset)
      $options['skip'] = $offset;

    if (isset($search['_id']) && is_string($search['_id'])) {
      $search['_id'] = new \MongoDB\BSON\ObjectId($search['_id']);
    }

    if (count($search) > 0) {
      $searchOr = [];
      $searchAnd = [];
      foreach ($search as $k => $v) {
        if ($k === '$and') $searchAnd[] = $v;
        else $searchOr[] = [$k => $v];
      }
      $search = [];
      if (!empty($searchOr)) $search['$or'] = $searchOr;
      if (!empty($searchAnd)) $search['$and'] = $searchAnd;
    }

    $res = $this->find($search, $options);

    $this->stringifyValues($res);
    foreach ($res as $item)
      if (isset($item->password))
        $item->password = '*****';

    return $res;
  }

  /**
   * @param null $limit
   * @param null $offset
   * @param null $search
   * @param string|array $fields
   * @param null|array $sort
   * @return array
   * @throws \Exception
   */
  public function all($limit = null, $offset = null, $search = null, $fields = '*', $sort = null)
  {
    $options = [];
    $projection = [];
    if ($fields !== '*')
      foreach ($fields as $field)
        $projection[$field] = 1;

    if (count($projection) > 0)
      $options = ['projection' => $projection];

    if ($limit)
      $options['limit'] = $limit;

    if ($offset)
      $options['skip'] = $offset;

    if (!$search)
      $search = [];

    if (isset($search['_id']) && is_string($search['_id'])) {
      $search['_id'] = new \MongoDB\BSON\ObjectId($search['_id']);
    }

    if (count($search) > 0) {
      $searchOr = [];
      $searchAnd = [];
      foreach ($search as $k => $v) {
        if ($k === '$and') $searchAnd[] = $v;
        else $searchOr[] = [$k => $v];
      }
      $search = [];
      if (!empty($searchOr)) $search['$or'] = $searchOr;
      if (!empty($searchAnd)) $search['$and'] = $searchAnd;
    }

    if (!empty($sort)) $options['sort'] = $sort;

    $res = $this->find($search, $options);
    unset($options['limit']);
    unset($options['skip']);
    $cnt = $this->mongoCol->count($search, $options);

    $this->stringifyValues($res);
    foreach ($res as &$item)
      if (isset($item->password))
        $item->password = '*****';

    return ['total' => $cnt, 'records' => $res];
  }

  /**
   * Return one record from collection based on passed Where array
   *
   * @param array $where
   * @return mixed
   * @throws \Exception
   */
  public function getOneWhere(array $where)
  {
    if (isset($where['_id']) && !$where['_id'] instanceof ObjectId)
      $where['_id'] = new ObjectId($where['_id']);

    $r = $this->findOne($where);

    if ($r) {
      $this->stringifyValues($r);
      if (isset($r->password))
        $r->password = '*****';
    }

    return $r;
  }

  public function stringifyValues(&$r)
  {
    foreach ($r as &$value)
      if (is_array($value) || $value instanceof \stdClass)
        $this->stringifyValues($value);
      else
        $this->stringifyInternalValue($value);
  }

  private function stringifyInternalValue(&$value)
  {
    if ($value instanceof ObjectId)
      $value = $value->__toString();
    else if ($value instanceof Decimal128)
      $value = (float)$value->__toString();
    else if ($value instanceof Timestamp) {
      $value = (array)$value;
      $value = $value['timestamp'];
    }
  }

  /**
   * Update record in table based on passed array
   *
   * @param array $items
   * @param array $where
   * @return mixed
   * @throws \Exception
   */
  public function updateWhere(array $items, array $where)
  {
    if (isset($where['_id']) && !$where['_id'] instanceof \MongoDB\BSON\ObjectId)
      $where['_id'] = new \MongoDB\BSON\ObjectId($where['_id']);

    foreach ($items as $key => $value)
      if ($value === '*****')
        unset($items[$key]);

    $r = $this->updateMany($where, ['$set' => $items]);

    return $r->getModifiedCount();
  }

  /**
   * Create record in table based on passed array
   *
   * @param $items
   * @param $queryParams
   * @return mixed
   * @throws \Exception
   */
  public function create($items, $queryParams)
  {
    if (!$items || !is_array($items))
      throw new \Exception('parameter "$items" should be array');

    return $this->insertOne($items)->getInsertedId();
  }


  /**
   * Delete record based on passed Where array
   *
   * @param array $where
   * @return mixed
   * @throws \Exception
   */
  public function deleteWhere(array $where)
  {
    if (isset($where['_id']) && !$where['_id'] instanceof ObjectId)
      $where['_id'] = new ObjectId($where['_id']);

    return $this->deleteMany($where);
  }


  /**
   * Do check - exists record in table with passed Where array values?
   *
   * @param array $where
   * @return mixed
   * @throws \Exception
   */
  public function isExistWhere(array $where)
  {
    if (isset($where['_id']) && is_string($where['_id']))
      $where['_id'] = new ObjectId($where['_id']);

    return $this->mongoCol->count($where) > 0;
  }


  /**
   * Mongo Collection find alias
   *
   * @param array $filter
   * @param array $options
   *
   * @return \MongoDB\Driver\Cursor
   */
  public function find($filter = [], array $options = [])
  {
    return $this->mongoCol->find($filter, $options)->toArray();
  }


  /**
   * Mongo Collection aggregate
   *
   * @param array $filter
   * @param array $options
   *
   * @return \MongoDB\Driver\Cursor
   */
  public function aggregate($filter = [], array $options = [])
  {
    $r = $this->mongoCol->aggregate($filter, $options)->toArray();
    $this->stringifyValues($r);
    return $r;
  }


  /**
   *  SUBDOCUMENTS
   */

  /**
   * Insert subdocument into specific document
   *
   * @param string $parent_id
   * @param string $subdocuments the name of array of subdocuments
   * @param array $fields to create new element
   *
   * @return \MongoDB\UpdateResult
   */
  public function createSubdocument($parent_id, $subdocuments, $fields = [])
  {
    $fields['_id'] = new ObjectID();

    $this->mongoCol->updateMany(
      ['_id' => new ObjectId($parent_id)],
      ['$push' => [$subdocuments => $fields]]
    );

    return $fields['_id']->__toString();
  }

  /**
   * Search subdocument in all documents
   *
   * @param $subdocuments
   * @param $sub_id
   * @return |null
   */
  public function getSubdocumentsById($subdocuments, $sub_id)
  {
    // TODO: find another way to get single subdocument
    $parentDocument = $this->findOne(
      ["{$subdocuments}._id" => new ObjectId($sub_id)],
      ['projection' => ['_id' => 0, $subdocuments => 1]]
    );
    if (!$parentDocument) return null;

    foreach ($parentDocument->{$subdocuments} as $subdocument)
      if (($subdocument->_id = $subdocument->_id->__toString()) === $sub_id)
        return $subdocument;
  }

  public function updateSubdocumentsById($subdocuments, $sub_id, $fields)
  {
    $newFields = [];
    foreach ($fields as $key => $value) {
      $newFields["{$subdocuments}.$.{$key}"] = $value;
    }
    return $this->updateMany(
      ["{$subdocuments}._id" => new ObjectId($sub_id)],
      ['$set' => $newFields]
    );
  }

  public function deleteSubdocumentsById($subdocuments, $sub_id)
  {
    return $this->updateMany(
      ["{$subdocuments}._id" => new ObjectId($sub_id)],
      ['$pull' => [$subdocuments => ['_id' => new ObjectId($sub_id)]]]
    );
  }
}
