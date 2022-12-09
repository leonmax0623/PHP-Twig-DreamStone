<?php

namespace DS\Controller\Api;

use DS\Model\Admin as AdminModel;
use DS\Core\Controller\ApiController;
use DS\Core\Utils;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Timestamp;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class User
 * @package DS\Controller\Api
 */
final class Database extends ApiController
{
  /**
   * Recreate collections (with indexes) in MongoDB
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return Response
   * @throws \Exception
   */
  public function recreateAction(Request $request, Response $response, $args)
  {
    $this->logger->info("database recreation started");

    $truncateMode = isset($args['truncate']) && 'truncate' == $args['truncate'];

    $models = [
      'Import',
      'Diamond',
      'DiamondPrice',
      'Gemstone',
      'GemstoneColor',
      'GemstoneShape',
      'Education',
      'Cart',
      'Clarity',
      'Color',
      'Culet',
      'Cut',
      'Favorite',
      'Flourence',
      'Girdle',
      'Polish',
      'Shape',
      'Symmetry',
      'RingStyle',
      'Metal',
      'Fancycolor',
      'WeddingMen',
      'WeddingWomen',
      'BirthStone',
      'JewelryType',
      'JewelryTypeStyle',
      'JewelryStones',
      'JewelryPearl',
      'FAQs',
      'Favorite',
      'User',
      'Order',
      'Token',
      'Admin',
      'Category',
      'Product',
      'StaticPages',
      'Coupon',
      'Attribute',
      'Options',
      'Settings',
      'Content',
      'Page',
      'Vendor',
      'User',
      'MailTemplate',
      'Tax',
      'Matching',
    ];

    foreach ($models as $model)
      $this->recreateModel($model, $truncateMode);

    return $this->emptyJson($response);
  }

  /**
   * Drop, create and fill collection by default values
   *
   * @param string $modelName
   * @param bool $truncateMode
   */
  private function recreateModel(string $modelName, $truncateMode = false)
  {
    $fullModelName = 'DS\\Model\\' . $modelName;
    $smallModelName = strtolower($modelName);

    $this->logger->info("  process {$fullModelName} model");
    $model = new $fullModelName($this->mongodb);
    if ($truncateMode && $model->isCollectionExists()) {
      $model->dropCollection();
      $this->logger->info("    truncate mode, try drop {$smallModelName} model");
    }

    if (!$model->isCollectionExists()) {
      $model->createCollection();
      $this->logger->info('    create collection ' . $smallModelName);
    }

    $referencebooksFileName = __DIR__ . "/../../../../private/referencebooks/{$smallModelName}.json";
    if (file_exists($referencebooksFileName)) {
      $this->logger->info("    founded default values for {$smallModelName} into {$referencebooksFileName}");
      $j = json_decode(file_get_contents($referencebooksFileName), true);

      if ($j) {
        $this->logger->info('      size of referencebook is ' . count($j) . ' elements');

        foreach ($j as &$node) {
          if (isset($node['password']))
            $node['password'] = Utils::hashPassword($node['password']);

          if (isset($node['created']))
            $node['created'] = new Timestamp(0, time());

          foreach (['_id', 'parent_id', 'category_id', 'ringstyle_id', 'jewelrytype_id'] as $key)
            if (isset($node[$key]))
              $node[$key] = $node[$key] ? new ObjectID($node[$key]) : new ObjectID();

          foreach (['price'] as $key)
            if (isset($node[$key]))
              $node[$key] = new Decimal128($node[$key]);

          foreach ($node as &$subnode)
            if (is_array($subnode))
              foreach ($subnode as &$item) {
                if ($smallModelName === 'content' && isset($item['file'])) {
                  $filepath = $this->settings['view']['template_path'] . '/' . $item['file'];
                  if (file_exists($filepath))
                    $item['content'] = file_get_contents($filepath);
                }
                foreach (['_id', 'parent_id', 'category_id'] as $key)
                  if (isset($item[$key]))
                    $item[$key] = $item[$key] ? new ObjectID($item[$key]) : new ObjectID();
              }
        }

        $model->insertMany($j);
      } else {
        $this->logger->warn("      cannot parse {$referencebooksFileName} as JSON");
      }
    }

    $this->logger->info('    re-create indexes for ' . $smallModelName);
    $model->createIndexes();
  }
}
