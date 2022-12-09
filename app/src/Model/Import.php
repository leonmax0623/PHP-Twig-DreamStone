<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;
use MongoDB\BSON\UTCDateTime;

/**
 * Class User
 * @package App\Model
 */
class Import extends MongoModel {
  const STATUS_STARTED = 'started';
  const STATUS_ENDED = 'ended';
  const STATUS_ERROR = 'error';
  const STATUS_UNKNOWN = 'unknown';

  /**
   * @var string Collection name
   */
  protected $collection = 'import';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'status' => ['index' => 1],
    'started' => ['index' => 1],
    'ended' => ['index' => 1],
    'type' => ['index' => 1],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    '$jsonSchema' => [
      'bsonType' => 'object',
      'required' => ['type', 'status', 'started'],
      'properties' => [
        'type' => [
          'bsonType' => 'string'
        ],
        'status' => [
          'enum' => ['started', 'ended', 'unknown', 'error']
        ],
        'started' => [
          'bsonType' => 'date'
        ],
        'ended' => [
          'bsonType' => 'date'
        ],
        'processed' => [
          'bsonType' => 'int'
        ],
        'downloaded' => [
          'bsonType' => 'int'
        ],
        'broken' => [
          'bsonType' => 'int'
        ]
      ]
    ]
  ];


  /**
   * @param string $type
   * @return bool
   */
  public function importRunNow(string $type) {
    foreach ($this->find(['type' => $type, 'status' => Import::STATUS_STARTED]) as $r)
      return true;

    return false;
  }

  /**
   * @param string $type
   * @return \MongoDB\InsertOneResult
   */
  public function startNewImport(string $type) {
    return $this->insertOne(
      [
        'type' => $type,
        'status' => Import::STATUS_STARTED,
        'started' => new UTCDateTime()
      ]
    )->getInsertedId();
  }

  /**
   * @param $rowId
   * @param string $status
   * @return \DS\Core\Model\MongoDB\UpdateResult|\MongoDB\UpdateResult
   */
  public function stopImport($rowId, string $status = Import::STATUS_ENDED) {
    return $this->updateOne(
      ['_id' => $rowId],
      ['$set' => [
        'status' => $status,
        'ended' => new UTCDateTime()
      ]]
    );
  }

  /**
   * @param $rowId
   * @param int $processed
   * @param int $broken
   * @return \DS\Core\Model\MongoDB\UpdateResult|\MongoDB\UpdateResult
   */
  public function updateStatus($rowId, int $processed, int $broken) {
    return $this->updateOne(
      ['_id' => $rowId],
      ['$set' => [
        'processed' => $processed,
        'broken' => $broken
      ]]
    );
  }

}