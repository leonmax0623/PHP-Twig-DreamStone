<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class MailTemplate
 * @package App\Model
 */
class MailTemplate extends MongoModel
{

  /**
   * @var string Collection name
   */
    protected $collection = 'mailtemplate';

    /**
     * @var array Indexes
     */
    protected $indexes = [
      'type' => ['index' => 1, 'unique' => true],
      'subject' => ['index' => 1 ],
      // 'body' => ['index' => 1 ],
    ];

    /**
     * @var array Required fields
     */
    protected $validator = [
      'type' => ['$type' => 'string'],
      'subject' => ['$type' => 'string'],
      'body' => ['$type' => 'string'],
    ];
}
