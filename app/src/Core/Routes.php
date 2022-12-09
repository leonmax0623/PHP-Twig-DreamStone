<?php

namespace DS\Core;

use DS\Model\JewelryType;
use Slim\App;
use DS\Core\Middlewares\AuthenticatedAdminRoute;
use DS\Core\Middlewares\AuthenticatedProdRoute;
use DS\Core\Middlewares\AuthenticatedUserRoute;
use DS\Core\Middlewares\WithUser;
use DS\Core\Middlewares\WithTemplates;
use DS\Core\Middlewares\ValidatePost;
use DS\Core\Middlewares\ValidateJson;

/**
 * Class Routes
 * Register Slim routes into provided App object
 *
 * @package DS\Core
 */
class Routes
{
  /**
   * @var App Slim App instance
   */
  private $app;

  /**
   * Routes constructor.
   *
   * @param App $app Slim App Instance
   */
  public function __construct(App $app)
  {
    $this->app = $app;
  }

  /**
   * Load all Routes
   */
  public function loadAll()
  {
    $this->loadFrontendRoutes();
    $this->loadBackendRoutes();
    $this->loadApiRoutes();
  }

  /**
   * Load front-office routes
   */
  public function loadFrontendRoutes()
  {
    $this->app->get('/', 'DS\Controller\Web\Frontend\Main:homeAction')->setName('homepage')
      ->add(WithUser::class)->add(WithTemplates::class);

    $this->app->get('/search/suggestions', 'DS\Controller\Web\Frontend\Search:suggestionsAction')->setName('suggestions')
      ->add(WithUser::class)->add(WithTemplates::class);

    $this->app->get('/search', 'DS\Controller\Web\Frontend\Search:searchAction')->setName('search')
      ->add(WithUser::class)->add(WithTemplates::class);

    // Loose Diamonds Group
    $this->app->group('/loose-diamonds', function () {
      $this->get('', 'DS\Controller\Web\Frontend\LooseDiamonds:indexAction')->setName('looseDiamonds');
      $this->post('/book-form', 'DS\Controller\Web\Frontend\LooseDiamonds:bookFormAction')->setName('looseDiamonds-BookForm');
      $this->post('/match-form', 'DS\Controller\Web\Frontend\LooseDiamonds:matchFormAction')->setName('looseDiamonds-MatchForm');
      $this->post('/share-form', 'DS\Controller\Web\Frontend\LooseDiamonds:shareFormAction')->setName('looseDiamonds-ShareForm');
      $this->post('/mail-form', 'DS\Controller\Web\Frontend\LooseDiamonds:mailFormAction')->setName('looseDiamonds-MailForm');
      $this->post('/sku-form', 'DS\Controller\Web\Frontend\LooseDiamonds:skuFormAction')->setName('looseDiamonds-SkuForm');
      $this->post('/request-form', 'DS\Controller\Web\Frontend\LooseDiamonds:RequestImageFormAction')->setName('looseDiamonds-RequestImageForm');
      $this->post('/contact-form', 'DS\Controller\Web\Frontend\LooseDiamonds:contactFormAction')->setName('looseDiamonds-ContactForm');
      $this->post('/subscription-form', 'DS\Controller\Web\Frontend\LooseDiamonds:subscriptionFormAction')->setName('looseDiamonds-subscriptiontForm');
      $this->post('/custom-form', 'DS\Controller\Web\Frontend\LooseDiamonds:customFormAction')->setName('looseDiamonds-customForm');
      $this->get('/item/{diamond}', 'DS\Controller\Web\Frontend\LooseDiamonds:detailsAction')->setName('looseDiamonds-Details');
      $this->get('/{filter}', 'DS\Controller\Web\Frontend\LooseDiamonds:searchAction')->setName('looseDiamonds-Search');
    })->add(WithUser::class)->add(WithTemplates::class);

    // Composite
    $this->app->get('/builder', 'DS\Controller\Web\Frontend\Composite:detailsAction')->setName('composite-Details')
      ->add(WithUser::class)->add(WithTemplates::class);

    // shoule be use as filter for compare in diamond page
    $this->app->get('/compare/diamonds', 'DS\Controller\Web\Frontend\LooseDiamonds:compareAction')->setName('looseDiamonds-Compare')
      ->add(WithUser::class)->add(WithTemplates::class);

    $this->app->get('/compare/engagement-rings', 'DS\Controller\Web\Frontend\EngagementRings:compareEngagementRingsAction')->setName('engagementRings-Compare')
      ->add(WithUser::class)->add(WithTemplates::class);

    // Wedding Rings Group
    $this->app->group('/wedding-rings', function () {
      $this->get('', 'DS\Controller\Web\Frontend\WeddingRings:indexAction')->setName('weddingRings');
      $this->get('/{filter}/{product}', 'DS\Controller\Web\Frontend\WeddingRings:detailsAction')->setName('weddingRings-Details');
      $this->get('/{filter}', 'DS\Controller\Web\Frontend\WeddingRings:searchAction')->setName('weddingRings-Search');
    })->add(WithUser::class)->add(WithTemplates::class);

    $this->app->get('/customer_creation', 'DS\Controller\Web\Frontend\Jewelry:customerAction')
      ->setName('customer')->add(WithUser::class)->add(WithTemplates::class);

    // Gemstones Group
    $this->app->group('/gemstones', function () {
      $this->get('', 'DS\Controller\Web\Frontend\Gemstones:indexAction')->setName('gemstones');
      $this->get('/{filter}/{product}', 'DS\Controller\Web\Frontend\Gemstones:detailsAction')->setName('gemstones-Details');
      $this->get('/{filter}', 'DS\Controller\Web\Frontend\Gemstones:searchAction')->setName('gemstones-Search');
    })->add(WithUser::class)->add(WithTemplates::class);

    // Education Group
    $this->app->group('/education', function () {
      $this->get('', 'DS\Controller\Web\Frontend\Education:indexAction')->setName('education');
      $this->get('/{filter}', 'DS\Controller\Web\Frontend\Education:pageAction')->setName('education-Page');
      $this->get('/{filter}/{secondfilter}', 'DS\Controller\Web\Frontend\Education:pageAction')->setName('education-Page');
      $this->get('/{filter}/{secondfilter}/{thirdfilter}', 'DS\Controller\Web\Frontend\Education:pageAction')->setName('education-Page');
    })->add(WithUser::class)->add(WithTemplates::class);

    // Static Pages Group
    $this->app->group('/staticpages', function () {
      $this->get('', 'DS\Controller\Web\Frontend\StaticPage:indexAction')->setName('staticpages');
      $this->get('/{filter}', 'DS\Controller\Web\Frontend\StaticPage:pageAction')->setName('staticpages-Page');
    })->add(WithUser::class)->add(WithTemplates::class);

    // Pages Group
    $this->app->group('/pages', function () {
      $this->get('', 'DS\Controller\Web\Frontend\Page:indexAction')->setName('pages');
      $this->get('/{url}', 'DS\Controller\Web\Frontend\Page:pageAction')->setName('pages-Page');
    })->add(WithUser::class)->add(WithTemplates::class);

    // Orders Group
    $this->app->group('/orders', function () {
      $this->get('', 'DS\Controller\Web\Frontend\Order:indexAction')->setName('orders');
      $this->get('/{filter}', 'DS\Controller\Web\Frontend\Order:pageAction')->setName('orders-Page');
      $this->delete('/favorites', 'DS\Controller\Web\Frontend\Order:deleteAction')->setName('deleteFavorite');
    })->add(WithUser::class)->add(WithTemplates::class);

    // For anonymous Users
    $this->app->group('/user', function () {
      $this->map(['POST', 'GET'], '/login', 'DS\Controller\Web\Frontend\User:loginAction')->setName('login');
      $this->post('/register', 'DS\Controller\Web\Frontend\User:registerAction')->setName('register');
      $this->post('/forgot_password', 'DS\Controller\Web\Frontend\User:forgotPasswordAction')->setName('forgot_password');
      $this->map(['POST', 'GET'], '/logout', 'DS\Controller\Web\Frontend\User:logoutAction')->setName('logout');
      // $this->post('/get-score', 'DS\Controller\Web\Frontend\Verification:getScoreAction')->setName('score');
    })->add(WithUser::class)->add(WithTemplates::class)->add(ValidatePost::class);

    // For authorised Users
    $this->app->group('/user', function () {
      $this->map(['POST', 'GET'], '/profile', 'DS\Controller\Web\Frontend\User:profileAction')->setName('profile');
      $this->get('/favorites', 'DS\Controller\Web\Frontend\Favorite:allAction')->setName('allFavorite');
      $this->post('/favorites', 'DS\Controller\Web\Frontend\Favorite:addAction')->setName('addFavorite');
      $this->delete('/favorites', 'DS\Controller\Web\Frontend\Favorite:deleteAction')->setName('deleteFavorite');
    })->add(WithUser::class)->add(WithTemplates::class)->add(AuthenticatedUserRoute::class)->add(ValidatePost::class);

    // Shopping Cart
    $this->app->group('/cart', function () {
      $this->get('', 'DS\Controller\Web\Frontend\Cart:getAction')->setName('cart');
      $this->post('', 'DS\Controller\Web\Frontend\Cart:addAction')->setName('cartAdd');
      $this->put('', 'DS\Controller\Web\Frontend\Cart:updateAction')->setName('cartUpdate');
      $this->delete('', 'DS\Controller\Web\Frontend\Cart:deleteAction')->setName('cartDelete');
      $this->post('/count', 'DS\Controller\Web\Frontend\Cart:getCountAction')->setName('getCount');
      $this->post('/coupon', 'DS\Controller\Web\Frontend\Cart:postCouponAction')->setName('postCoupon');
      $this->delete('/coupon', 'DS\Controller\Web\Frontend\Cart:deleteCouponAction')->setName('deleteCoupon');
    })->add(WithUser::class)->add(WithTemplates::class);

    // Payments
    $this->app->map(['POST', 'GET'], '/payment-method', 'DS\Controller\Web\Frontend\Cart:paymentMethodAction')
      ->setName('paymentMethod')->add(WithUser::class)->add(WithTemplates::class);
    // PayPal
    $this->app->map(['POST', 'GET'], '/payment/paypal', 'DS\Controller\Web\Frontend\Payment\Paypal:paymentAction')
      ->setName('payment')->add(WithUser::class)->add(WithTemplates::class);
    $this->app->map(['POST', 'GET'], '/payment/paypal/successful', 'DS\Controller\Web\Frontend\Payment\Paypal:paymentSuccessful')
      ->setName('paypalSuccessful')->add(WithUser::class)->add(WithTemplates::class);
    $this->app->map(['POST', 'GET'], '/payment/paypal/cancelled', 'DS\Controller\Web\Frontend\Payment\Paypal:paymentCancelled')
      ->setName('paypalCancelled')->add(WithUser::class)->add(WithTemplates::class);
    $this->app->post('/payment/paypal/notify', 'DS\Controller\Web\Frontend\Payment\Paypal:paymentNotify')
      ->setName('paypalNotify');
    // Affirm
    $this->app->map(['POST', 'GET'], '/payment/affirm', 'DS\Controller\Web\Frontend\Payment\Affirm:paymentAction')
      ->setName('payment')->add(WithUser::class)->add(WithTemplates::class);
    $this->app->map(['POST', 'GET'], '/payment/affirm/successful', 'DS\Controller\Web\Frontend\Payment\Affirm:paymentSuccessful')
      ->setName('affirmSuccessful')->add(WithUser::class)->add(WithTemplates::class);
    $this->app->map(['POST', 'GET'], '/payment/affirm/cancelled', 'DS\Controller\Web\Frontend\Payment\Affirm:paymentCancelled')
      ->setName('affirmCancelled')->add(WithUser::class)->add(WithTemplates::class);

    $this->app->map(['POST', 'GET'], '/payment', 'DS\Controller\Web\Frontend\Cart:paymentAction')
      ->setName('payment')->add(WithUser::class)->add(WithTemplates::class);

    $this->app->group('/my-orders', function () {
      $this->get('', 'DS\Controller\Web\Frontend\Order:allAction')->setName('myOrders');
      $this->get('/{number}', 'DS\Controller\Web\Frontend\Order:getAction')->setName('myOrder');
    })->add(WithUser::class)->add(WithTemplates::class)->add(AuthenticatedUserRoute::class);

    //$this->app->get('/wedding-rings', 'DS\Controller\Web\Frontend\Main:weddingRingsAction')->setName('weddingRings');
    // $this->app->get('/gemstones', 'DS\Controller\Web\Frontend\Main:gemstonesAction')->setName('gemstones');
    // $this->app->get('/about-us', 'DS\Controller\Web\Frontend\Main:aboutUsAction')->setName('aboutUs');
    $this->app->get('/about', 'DS\Controller\Web\Frontend\FAQ:aboutAction')
      ->setName('about')->add(WithUser::class)->add(WithTemplates::class);
    $this->app->get('/viewed', 'DS\Controller\Web\Frontend\Main:viewedAction')
      ->setName('viewed')->add(WithUser::class)->add(WithTemplates::class);
    $this->app->get('/wish-list', 'DS\Controller\Web\Frontend\Favorite:allAction')->setName('wishList')
      ->add(WithUser::class)->add(WithTemplates::class);
    $this->app->map(['POST', 'GET'], '/billing-shipping', 'DS\Controller\Web\Frontend\Cart:billingAction')
      ->setName('billingShipping')->add(WithUser::class)->add(WithTemplates::class);
    $this->app->get('/confirmation', 'DS\Controller\Web\Frontend\Cart:confirmationAction')
      ->setName('confirmation')->add(WithUser::class)->add(WithTemplates::class);
    $this->app->get('/order', 'DS\Controller\Web\Frontend\Main:OrderAction')
      ->setName('order')->add(WithUser::class)->add(WithTemplates::class);

    //Jewelry Group
    $this->app->group('/jewelry', function () {
      $this->get('', 'DS\Controller\Web\Frontend\Jewelry:indexAction')->setName('jewelry');
      $this->get('/{filter}/{product}', 'DS\Controller\Web\Frontend\Jewelry:detailsAction')->setName('jewelry-Details');
      $this->get('/{filter}', 'DS\Controller\Web\Frontend\Jewelry:searchAction')->setName('jewelry-Search');
    })->add(WithUser::class)->add(WithTemplates::class);

    // Engagement Rings Group
    $this->app->group('/engagement-rings', function () {
      $this->get('', 'DS\Controller\Web\Frontend\EngagementRings:indexAction')->setName('engagementRings');
      $this->get('/item/{product}', 'DS\Controller\Web\Frontend\Jewelry:detailsAction')->setName('engagementRings-Details');
      $this->get('/search', 'DS\Controller\Web\Frontend\EngagementRings:searchAction')->setName('engagementRings-Search');
    })->add(WithUser::class)->add(WithTemplates::class);

    $container = $this->app->getContainer();
    // Dynamic jewelry types
    foreach ((new JewelryType($container->get('mongodb')))->getEditableTypes() as $type) {
      $this->app->group('/' . $type->code, function () use ($type) {
        $this->get('', 'DS\Controller\Web\Frontend\DynamicTypes:searchAction')
          ->setName('dynamic-' . $type->code . '-Search');
        $this->get('/item/{product}', 'DS\Controller\Web\Frontend\DynamicTypes:detailsAction')
          ->setName('dynamic-' . $type->code . '-Details');
      })->add(WithUser::class)->add(WithTemplates::class);
    }

    // Override the default Not Found Handler after App
    unset($container['notFoundHandler']);
    $container['notFoundHandler'] = function ($c) {
      return function ($request, $response) use ($c) {
        // Handle case insensitive file requests
        $internalFilePath = __DIR__ . '/../../../public';
        $fileName = $internalFilePath . $request->getUri()->getPath();
        $directoryName = dirname($fileName);
        $fileArray = glob($directoryName . '/*', GLOB_NOSORT);
        $fileNameLowerCase = strtolower($fileName);
        foreach ($fileArray as $file) {
          if (strtolower($file) == $fileNameLowerCase) {
            if ($response && $fileHandle = fopen($file, 'rb')) {
              $streamFileContents = new \Slim\Http\Stream($fileHandle);
              $output = $response
                ->withHeader('Content-Type', mime_content_type(basename($fileName)))
                ->withHeader('Content-Transfer-Encoding', 'binary')
                ->withHeader('Expires', '0')
                ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                ->withHeader('Pragma', 'public')
                ->withHeader('Content-Length', filesize($file))
                ->withBody($streamFileContents);

              return $output;
            }
          }
        }

        return $c['view']->render($response->withStatus(404), 'pages/frontend/404.twig', [
          'user' => (new WithUser($c))->get(),
          'templates' => (new WithTemplates($c))->get(),
        ]);
      };
    };
  }

  /**
   * Load back-office routes
   */
  public function loadBackendRoutes()
  {
    $this->app->get('/admin', 'DS\Controller\Web\Backend\Dashboard:dashboardAction')->setName('dashboard');
  }

  /**
   * Load API routes
   */
  public function loadApiRoutes()
  {
    $this->app->group('/api', function () {
      // System and development actions
      $this->group('/v1', function () {
        $this->get('/database/recreate[/{truncate}]', 'DS\Controller\Api\Database:recreateAction')->setName('api-database-recreate');
        $this->get('/referencebooks/reload', 'DS\Controller\Api\ReferenceBooks:reloadAction')->setName('api-referencebooks-reload');

        $this->get('/import/rapaport', 'DS\Controller\Api\Import:rapaportAction')->setName('api-import-rapaport');
        $this->get('/import/idex', 'DS\Controller\Api\Import:idexAction')->setName('api-import-idex');
        $this->get('/import/independent/belgiumny', 'DS\Controller\Api\Import:belgiumnyAction')->setName('api-import-independent-belgiumny');
        $this->get('/import/independent/apibelgiumdia', 'DS\Controller\Api\Import:belgiumdiaAction')->setName('api-import-independent-apibelgiumdia');
        $this->get('/import/independent/apibelgiumdialabgrown', 'DS\Controller\Api\Import:belgiumdialabAction')->setName('api-import-independent-apibelgiumdialabgrown');
        $this->get('/import/independent/apiharikrishna', 'DS\Controller\Api\Import:hkAction')->setName('api-import-independent-apiharikrishna');
        $this->get('/import/independent/diamondfoundry', 'DS\Controller\Api\Import:diamondfoundryAction')->setName('api-import-independent-diamondfoundry');
        $this->get('/import/independent/{id}', 'DS\Controller\Api\Import:independentAction')->setName('api-import-independent');
      });

      // API handlers without authorization
      $this->group('/v1', function () {
        $this->post('/admins/login', 'DS\Controller\Api\Admin:loginAction');
      });

      // API handlers what be called only by authorised users

      $this->group('/v1', function () {
        $this->group('/products', function () {
          $this->get('', '\DS\Controller\Api\Product:allAction');
          $this->post('', '\DS\Controller\Api\Product:createAction');
          $this->get('/export', '\DS\Controller\Api\Product:exportAction');
          $this->get('/{id}', '\DS\Controller\Api\Product:getAction');
          $this->post('/{id}', '\DS\Controller\Api\Product:updateAction');
          $this->delete('/{id}', '\DS\Controller\Api\Product:deleteAction');
          $this->post('/{id}/duplicate', '\DS\Controller\Api\Product:duplicateAction');
          $this->get('/sku/{sku}', '\DS\Controller\Api\Product:getBySkuAction');
          $this->get('/multiple-sku/{sku}', '\DS\Controller\Api\Product:getMultipleBySkuAction');
        });

        $this->group('/categories', function () {
          $this->get('/tree', '\DS\Controller\Api\Category:treeAction');
        });

        $this->group('/attributes', function () {
          $this->get('', '\DS\Controller\Api\Attribute:allAction');
        });

        $this->group('/references', function () {
          $this->group('/gemstonecolor', function () {
            $this->get('', '\DS\Controller\Api\References\GemstoneColor:allAction');
          });

          $this->group('/gemstoneshape', function () {
            $this->get('', '\DS\Controller\Api\References\GemstoneShape:allAction');
          });

          $this->group('/clarity', function () {
            $this->get('', '\DS\Controller\Api\References\Clarity:allAction');
          });

          $this->group('/color', function () {
            $this->get('', '\DS\Controller\Api\References\Color:allAction');
          });

          $this->group('/culet', function () {
            $this->get('', '\DS\Controller\Api\References\Culet:allAction');
          });

          $this->group('/cut', function () {
            $this->get('', '\DS\Controller\Api\References\Cut:allAction');
          });

          $this->group('/flourence', function () {
            $this->get('', '\DS\Controller\Api\References\Flourence:allAction');
          });

          $this->group('/girdle', function () {
            $this->get('', '\DS\Controller\Api\References\Girdle:allAction');
          });

          $this->group('/polish', function () {
            $this->get('', '\DS\Controller\Api\References\Polish:allAction');
          });

          $this->group('/birthstone', function () {
            $this->get('', '\DS\Controller\Api\References\BirthStone:allAction');
          });

          $this->group('/fancycolor', function () {
            $this->get('', '\DS\Controller\Api\References\Fancycolor:allAction');
          });

          $this->group('/symmetry', function () {
            $this->get('', '\DS\Controller\Api\References\Symmetry:allAction');
          });

          $this->group('/jewelrypearl', function () {
            $this->get('', '\DS\Controller\Api\References\JewelryPearl:allAction');
          });

          $this->group('/jewelrystones', function () {
            $this->get('', '\DS\Controller\Api\References\JewelryStones:allAction');
          });

          $this->group('/jewelrytype', function () {
            $this->get('', '\DS\Controller\Api\References\JewelryType:allAction');
          });

          $this->group('/jewelrytypestyle', function () {
            $this->get('', '\DS\Controller\Api\References\JewelryTypeStyle:allAction');
          });

          $this->group('/metal', function () {
            $this->get('', '\DS\Controller\Api\References\Metal:allAction');
          });

          $this->group('/ringstyle', function () {
            $this->get('', '\DS\Controller\Api\References\RingStyle:allAction');
          });

          $this->group('/shape', function () {
            $this->get('', '\DS\Controller\Api\References\Shape:allAction');
          });
        });

        $this->get('/dashboard', '\DS\Controller\Api\Dashboard:getAction');
      })->add(AuthenticatedProdRoute::class);

      $this->group('/v1', function () {
        $this->group('/admins', function () {
          $this->get('', '\DS\Controller\Api\Admin:allAction');
          $this->post('', '\DS\Controller\Api\Admin:createAction');
          $this->get('/profile', '\DS\Controller\Api\Admin:getProfileAction');
          $this->post('/profile', '\DS\Controller\Api\Admin:updateProfileAction');
          $this->get('/{id}', '\DS\Controller\Api\Admin:getAction');
          $this->put('/{id}', '\DS\Controller\Api\Admin:updateAction');
          $this->delete('/{id}', '\DS\Controller\Api\Admin:deleteAction');
        });

        $this->group('/categories', function () {
          $this->get('', '\DS\Controller\Api\Category:allAction');
          $this->post('', '\DS\Controller\Api\Category:createAction');
          $this->post('/{id}', '\DS\Controller\Api\Category:updateAction');
          // $this->get('/tree', '\DS\Controller\Api\Category:treeAction');
          $this->get('/{id}', '\DS\Controller\Api\Category:getAction');
          $this->delete('/{id}', '\DS\Controller\Api\Category:deleteAction');
        });

        // $this->group('/products', function () {
        //   $this->get('', '\DS\Controller\Api\Product:allAction');
        //   $this->post('', '\DS\Controller\Api\Product:createAction');
        //   $this->get('/export', '\DS\Controller\Api\Product:exportAction');
        //   $this->get('/{id}', '\DS\Controller\Api\Product:getAction');
        //   $this->post('/{id}', '\DS\Controller\Api\Product:updateAction');
        //   $this->delete('/{id}', '\DS\Controller\Api\Product:deleteAction');
        //   $this->post('/{id}/duplicate', '\DS\Controller\Api\Product:duplicateAction');
        //   $this->get('/sku/{sku}', '\DS\Controller\Api\Product:getBySkuAction');
        //   $this->get('/multiple-sku/{sku}', '\DS\Controller\Api\Product:getMultipleBySkuAction');
        // });

        $this->group('/attributes', function () {
          // $this->get('', '\DS\Controller\Api\Attribute:allAction');
          $this->post('', '\DS\Controller\Api\Attribute:createAction');
          $this->get('/{id}', '\DS\Controller\Api\Attribute:getAction');
          $this->post('/{id}', '\DS\Controller\Api\Attribute:updateAction');
          $this->delete('/{id}', '\DS\Controller\Api\Attribute:deleteAction');

          $this->group('/{attribute_id}/values', function () {
            $this->post('', '\DS\Controller\Api\Attribute:createValueAction');
            $this->post('/{id}', '\DS\Controller\Api\Attribute:updateValueAction');
            $this->get('/{id}', '\DS\Controller\Api\Attribute:getValueAction');
            $this->delete('/{id}', '\DS\Controller\Api\Attribute:deleteValueAction');
          });
        });

        $this->group('/vendors', function () {
          $this->get('', '\DS\Controller\Api\Vendor:allAction');
          $this->post('', '\DS\Controller\Api\Vendor:createAction');
          $this->get('/{id}', '\DS\Controller\Api\Vendor:getAction');
          $this->post('/{id}', '\DS\Controller\Api\Vendor:updateAction');
          $this->delete('/{id}', '\DS\Controller\Api\Vendor:deleteAction');
        });

        $this->group('/taxes', function () {
          $this->get('', '\DS\Controller\Api\Tax:allAction');
          $this->get('/{id}', '\DS\Controller\Api\Tax:getAction');
          $this->post('/{id}', '\DS\Controller\Api\Tax:updateAction');
        });

        $this->group('/settings', function () {
          $this->get('', '\DS\Controller\Api\Settings:allAction');
          $this->get('/{id}', '\DS\Controller\Api\Settings:getAction');
          $this->post('/{id}', '\DS\Controller\Api\Settings:updateAction');
        });

        $this->group('/coupons', function () {
          $this->get('', '\DS\Controller\Api\Coupon:allAction');
          $this->post('', '\DS\Controller\Api\Coupon:createAction');
          $this->get('/{id}', '\DS\Controller\Api\Coupon:getAction');
          $this->post('/{id}', '\DS\Controller\Api\Coupon:updateAction');
          $this->delete('/{id}', '\DS\Controller\Api\Coupon:deleteAction');
          $this->post('/{id}/duplicate', '\DS\Controller\Api\Coupon:duplicateAction');
        });

        $this->group('/diamonds', function () {
          $this->get('', '\DS\Controller\Api\Diamond:allAction');
          $this->post('', '\DS\Controller\Api\Diamond:createAction');
          $this->get('/{id}', '\DS\Controller\Api\Diamond:getAction');
          $this->post('/{id}', '\DS\Controller\Api\Diamond:updateAction');
          $this->delete('/{id}', '\DS\Controller\Api\Diamond:deleteAction');
          $this->get('/certificate/{certificateNumber}', '\DS\Controller\Api\Diamond:getByCertificateNumberAction');
        });

        $this->group('/diamonds-price', function () {
          $this->get('', '\DS\Controller\Api\DiamondPrice:allAction');
          $this->post('', '\DS\Controller\Api\DiamondPrice:createAction');
          $this->get('/{id}', '\DS\Controller\Api\DiamondPrice:getAction');
          $this->post('/{id}', '\DS\Controller\Api\DiamondPrice:updateAction');
          $this->delete('/{id}', '\DS\Controller\Api\DiamondPrice:deleteAction');
        });

        $this->group('/references', function () {
          $this->group('/gemstonecolor', function () {
            // $this->get('', '\DS\Controller\Api\References\GemstoneColor:allAction');
            $this->post('', '\DS\Controller\Api\References\GemstoneColor:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\GemstoneColor:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\GemstoneColor:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\GemstoneColor:deleteAction');
          });

          $this->group('/gemstoneshape', function () {
            // $this->get('', '\DS\Controller\Api\References\GemstoneShape:allAction');
            $this->post('', '\DS\Controller\Api\References\GemstoneShape:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\GemstoneShape:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\GemstoneShape:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\GemstoneShape:deleteAction');
          });

          $this->group('/clarity', function () {
            // $this->get('', '\DS\Controller\Api\References\Clarity:allAction');
            $this->post('', '\DS\Controller\Api\References\Clarity:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\Clarity:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\Clarity:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\Clarity:deleteAction');
          });

          $this->group('/color', function () {
            // $this->get('', '\DS\Controller\Api\References\Color:allAction');
            $this->post('', '\DS\Controller\Api\References\Color:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\Color:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\Color:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\Color:deleteAction');
          });

          $this->group('/culet', function () {
            // $this->get('', '\DS\Controller\Api\References\Culet:allAction');
            $this->post('', '\DS\Controller\Api\References\Culet:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\Culet:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\Culet:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\Culet:deleteAction');
          });

          $this->group('/cut', function () {
            // $this->get('', '\DS\Controller\Api\References\Cut:allAction');
            $this->post('', '\DS\Controller\Api\References\Cut:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\Cut:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\Cut:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\Cut:deleteAction');
          });

          $this->group('/flourence', function () {
            // $this->get('', '\DS\Controller\Api\References\Flourence:allAction');
            $this->post('', '\DS\Controller\Api\References\Flourence:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\Flourence:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\Flourence:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\Flourence:deleteAction');
          });

          $this->group('/girdle', function () {
            // $this->get('', '\DS\Controller\Api\References\Girdle:allAction');
            $this->post('', '\DS\Controller\Api\References\Girdle:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\Girdle:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\Girdle:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\Girdle:deleteAction');
          });

          $this->group('/polish', function () {
            // $this->get('', '\DS\Controller\Api\References\Polish:allAction');
            $this->post('', '\DS\Controller\Api\References\Polish:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\Polish:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\Polish:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\Polish:deleteAction');
          });

          $this->group('/birthstone', function () {
            // $this->get('', '\DS\Controller\Api\References\BirthStone:allAction');
            $this->post('', '\DS\Controller\Api\References\BirthStone:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\BirthStone:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\BirthStone:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\BirthStone:deleteAction');
          });

          $this->group('/fancycolor', function () {
            // $this->get('', '\DS\Controller\Api\References\Fancycolor:allAction');
            $this->post('', '\DS\Controller\Api\References\Fancycolor:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\Fancycolor:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\Fancycolor:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\Fancycolor:deleteAction');
          });

          $this->group('/symmetry', function () {
            // $this->get('', '\DS\Controller\Api\References\Symmetry:allAction');
            $this->post('', '\DS\Controller\Api\References\Symmetry:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\Symmetry:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\Symmetry:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\Symmetry:deleteAction');
          });

          $this->group('/jewelrypearl', function () {
            // $this->get('', '\DS\Controller\Api\References\JewelryPearl:allAction');
            $this->post('', '\DS\Controller\Api\References\JewelryPearl:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\JewelryPearl:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\JewelryPearl:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\JewelryPearl:deleteAction');
          });

          $this->group('/jewelrystones', function () {
            // $this->get('', '\DS\Controller\Api\References\JewelryStones:allAction');
            $this->post('', '\DS\Controller\Api\References\JewelryStones:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\JewelryStones:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\JewelryStones:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\JewelryStones:deleteAction');
          });

          $this->group('/jewelrytype', function () {
            // $this->get('', '\DS\Controller\Api\References\JewelryType:allAction');
            $this->post('', '\DS\Controller\Api\References\JewelryType:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\JewelryType:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\JewelryType:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\JewelryType:deleteAction');
          });

          $this->group('/jewelrytypestyle', function () {
            // $this->get('', '\DS\Controller\Api\References\JewelryTypeStyle:allAction');
            $this->post('', '\DS\Controller\Api\References\JewelryTypeStyle:createAction');
            $this->post('/{id}', '\DS\Controller\Api\References\JewelryTypeStyle:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\JewelryTypeStyle:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\JewelryTypeStyle:deleteAction');
          });

          $this->group('/metal', function () {
            // $this->get('', '\DS\Controller\Api\References\Metal:allAction');
            $this->post('', '\DS\Controller\Api\References\Metal:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\Metal:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\Metal:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\Metal:deleteAction');
          });

          $this->group('/ringstyle', function () {
            // $this->get('', '\DS\Controller\Api\References\RingStyle:allAction');
            $this->post('', '\DS\Controller\Api\References\RingStyle:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\RingStyle:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\RingStyle:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\RingStyle:deleteAction');
          });

          $this->group('/shape', function () {
            // $this->get('', '\DS\Controller\Api\References\Shape:allAction');
            $this->post('', '\DS\Controller\Api\References\Shape:createAction');
            $this->put('/{id}', '\DS\Controller\Api\References\Shape:updateAction');
            $this->get('/{id}', '\DS\Controller\Api\References\Shape:getAction');
            $this->delete('/{id}', '\DS\Controller\Api\References\Shape:deleteAction');
          });
        });

        /*  $this->app->get('/faq', 'DS\Controller\Web\Frontend\FAQ:faqAction')->setName('faq');*/

        $this->group('/faq', function () {
          $this->get('', '\DS\Controller\Api\FAQ:allAction');
          $this->post('', '\DS\Controller\Api\FAQ:createAction');
          $this->post('/{id}', '\DS\Controller\Api\FAQ:updateAction');
          $this->get('/{id}', '\DS\Controller\Api\FAQ:getAction');
          $this->delete('/{id}', '\DS\Controller\Api\FAQ:deleteAction');

          $this->group('/{faqid}/questions', function () { // ---------
            $this->post('', '\DS\Controller\Api\FAQ:createQuestionAction');
            $this->post('/{id}', '\DS\Controller\Api\FAQ:updateQuestionAction');
            $this->get('/{id}', '\DS\Controller\Api\FAQ:getQuestionAction');
            $this->delete('/{id}', '\DS\Controller\Api\FAQ:deleteQuestionAction');
          });
        });

        $this->group('/staticpages', function () {
          $this->get('', '\DS\Controller\Api\StaticPage:allAction');
          $this->post('', '\DS\Controller\Api\StaticPage:createAction');
          $this->post('/{id}', '\DS\Controller\Api\StaticPage:updateAction');
          $this->get('/{id}', '\DS\Controller\Api\StaticPage:getAction');
          $this->delete('/{id}', '\DS\Controller\Api\StaticPage:deleteAction');
        });

        $this->group('/pages', function () {
          $this->get('', '\DS\Controller\Api\Page:allAction');
          $this->post('', '\DS\Controller\Api\Page:createAction');
          $this->post('/{id}', '\DS\Controller\Api\Page:updateAction');
          $this->get('/{id}', '\DS\Controller\Api\Page:getAction');
          $this->delete('/{id}', '\DS\Controller\Api\Page:deleteAction');
        });

        $this->group('/education', function () {
          $this->get('', '\DS\Controller\Api\Education:allAction');
          $this->post('', '\DS\Controller\Api\Education:createAction');
          $this->get('/{id}', '\DS\Controller\Api\Education:getAction');
          $this->post('/{id}', '\DS\Controller\Api\Education:updateAction');
          $this->delete('/{id}', '\DS\Controller\Api\Education:deleteAction');
        });

        $this->group('/contents/{area}', function () {
          $this->get('', '\DS\Controller\Api\Content:allAction');
          $this->get('/{id}', '\DS\Controller\Api\Content:getAction');
          $this->post('/{id}', '\DS\Controller\Api\Content:updateAction');
          $this->post('/{id}/reset', '\DS\Controller\Api\Content:resetAction');
        });

        $this->group('/orders', function () {
          $this->get('', '\DS\Controller\Api\Order:allAction');
          $this->post('', '\DS\Controller\Api\Order:createAction');
          $this->post('/{id}', '\DS\Controller\Api\Order:updateAction');
          $this->get('/{id}', '\DS\Controller\Api\Order:getAction');
          $this->delete('/{id}', '\DS\Controller\Api\Order:deleteAction');
        });

        $this->group('/users', function () {
          $this->get('', '\DS\Controller\Api\User:allAction');
          // $this->post('', '\DS\Controller\Api\User:createAction');
          $this->post('/import', '\DS\Controller\Api\User:importAction');
          $this->post('/{id}', '\DS\Controller\Api\User:updateAction');
          $this->get('/{id}', '\DS\Controller\Api\User:getAction');
          $this->delete('/{id}', '\DS\Controller\Api\User:deleteAction');
        });

        $this->group('/reports', function () {
          $this->get('/orders', '\DS\Controller\Api\Report:getOrdersAction');
        });

        // $this->get('/dashboard', '\DS\Controller\Api\Dashboard:getAction');
      })->add(AuthenticatedAdminRoute::class);
    })->add(ValidateJson::class);
  }
}
