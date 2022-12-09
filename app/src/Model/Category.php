<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;

/**
 * Class Category
 * @package App\Model
 */
class Category extends MongoModel
{
  /**
   * @var array
   */
  public $search_fields = [
    'title',
    'url',
    'description'
  ];

  /**
   * @var string Category name
   */
  protected $collection = 'category';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'title' => ['index' => 1],
    'url' => ['index' => 1],
    'parent_id' => ['index' => 1 ],
    'level' => ['index' => 1 ],
    'template' => ['index' => 1 ],
    'subcategories' => ['index' => 1 ],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'title' => ['$type' => 'string'],
    'url' => ['$type' => 'string'],
    'description' => ['$type' => 'string'],
    'parent_id' => ['$type' => ['objectId', 'null']],
    'level' => ['$type' => 'int'],
    'template' => ['$type' => ['string', 'null']],
    'attributes' => ['$type' => ['array']],
    'has_images' => ['$type' => 'bool'],
    'images' => ['$type' => 'array'],
    'subcategories' => ['$type' => 'int'],
  ];

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

    $items['level'] = 0;
    $items['subcategories'] = 0;
    $items['has_images'] = false;
    $items['template'] = null;
    $items['attributes'] = empty($items['attributes']) ? [] : $this->prepareAttributes($items['attributes']);
    $items['images'] = [];
    $items['parent_id'] = null;

    if (isset($queryParams['parent_id'])) {
      $_id = new ObjectId($queryParams['parent_id']);
      $parent = $this->getOneWhere(['_id' =>$_id]);

      if ($parent) {
        $items['level'] = $parent->level + 1;
        $items['parent_id'] = $_id;

        $parent->subcategories = $this->mongoCol->count(['parent_id' => $_id]);
        $this->updateWhere(['subcategories' => $parent->subcategories+1], ["_id" => $_id]);
      }
    }

    return $this->insertOne($items)->getInsertedId();
  }

  public function updateWhere(array $items, array $where)
  {
    if (isset($items['attributes']))
      $items['attributes'] = $this->prepareAttributes($items['attributes']);

    return parent::updateWhere($items, $where);
  }

  private function prepareAttributes(array $attributes)
  {
    foreach ($attributes as &$attribute) {
      if (is_array($attribute)) {
        $attribute['_id'] =  new ObjectId($attribute['_id']);
        foreach ($attribute['values'] as &$value) {
          $value['_id'] =  new ObjectId($value['_id']);
          $value['price'] =  new Decimal128($value['price']);
        }
      } else {
        $attribute->_id =  new ObjectId($attribute->_id);
        foreach ($attribute->values as &$value) {
          $value->_id =  new ObjectId($value->_id);
          $value->price =  new Decimal128($value->price);
        }
      }
    }
    return $attributes;
  }

  /**
   * Get _id for each category in tree
   *
   * @param $parent_id
   * @return array
   * @throws \Exception
   */
  public function getAllSubcategoriesIds($parent_id)
  {
    $ids = [];
    $subcategories = $this->allWhere(['parent_id' => $parent_id]);
    if (!$subcategories) {
      return $ids;
    }
    foreach ($subcategories as $cat) {
      $_id = new ObjectId($cat->_id);
      $ids[] = $_id;
      $ids = array_merge($ids, $this->getAllSubcategoriesIds($_id));
    }
    return $ids;
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

    $Categories = $this->find($where);
    if (empty($Categories)) return null;

    $ids = [];
    foreach ($Categories as $Category)
      $ids = array_merge($ids, [$Category->_id], $this->getAllSubcategoriesIds($Category->_id));

    $res = parent::deleteWhere(['$or' => array_map(function($_id){ return ['_id' => $_id]; }, $ids)]);

    foreach ($Categories as $Category)
      if ($Category->parent_id)
        $this->updateWhere(
          ['subcategories' => $this->mongoCol->count(['parent_id' => $Category->parent_id])],
          ['_id' => $Category->parent_id]
        );

    (new Product($this->mongodb))->deleteWhere([
      '$or' => array_map(function($_id){ return ['category_id' => $_id]; }, $ids)
    ]);

    return $res;
  }
}