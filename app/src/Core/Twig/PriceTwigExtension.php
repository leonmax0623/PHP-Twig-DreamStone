<?php

namespace DS\Core\Twig;

use DS\Core\Model\Currency;
use Slim\Container;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Class PriceTwigExtension
 * @package DS\Core\Twig
 */
class PriceTwigExtension extends AbstractExtension
{
  /**
   * @var Container Slim DI Container
   */
  private $currency;

  public function __construct(Container $c)
  {
    $currency = filter_input(INPUT_COOKIE, 'currency');
    $this->currency = (new Currency($c->get('mongodb')))->getDetails($currency === 'euro' ? 'euro' : 'dollar');
  }

  public function getFilters()
  {
    return [
      new TwigFilter('price', [$this, 'formatPrice']),
    ];
  }

  public function formatPrice($number, $decimals = 0, $decPoint = '', $thousandsSep = ',')
  {
    $price = number_format(
      $this->currency->rate === 1 ? $number : $number / $this->currency->rate,
      $decimals,
      $decPoint,
      $thousandsSep
    );

    return $this->currency->sign . $price;
  }
}