<?php

/**
 * App settings array
 */

return [
  'settings' => [
    // Slim Settings
    'determineRouteBeforeAppMiddleware' => false,
    'displayErrorDetails' => true,

    // Error handler
    // Default: Display call stack in orignal slim error
    'error' => [
      // Enable / disable Whoops error
      'whoops' => true,
      // Enable / disable JSON error (if 'whoops' == false)
      'json' => false
    ],

    // View settings
    'view' => [
      'template_path' => __DIR__ . '/../../templates',
      'twig' => [
        'cache' => __DIR__ . '/../../../cache/twig',
        'debug' => true,
        'auto_reload' => true
      ],
    ],

    // Token policy
    'token' => [
      'length' => 25,
      'lifetime' => 20, // in minutes
    ],

    // Images storage
    'images' => [
      'from_root' => true, // if false - used absolute path
      'categories' => [
        'filesystem' => '/content/categories/',
        'web' => '/content/categories/',
      ],
      'items' => [
        'filesystem' => '/content/items/',
        'web' => '/content/items/',
      ],
      'attributes' => [
        'filesystem' => '/content/attributes/',
        'web' => '/content/attributes/',
      ],
      'content' => [
        'filesystem' => '/content/content/',
        'web' => '/content/content/',
      ],
      'pages' => [
        'filesystem' => '/content/pages/',
        'web' => '/content/pages/',
      ],
    ],

    'import' => [
      'rapaport' => [
        'username' => '*',
        'password' => '*',
        'authUrl' => 'https://technet.rapaport.com/HTTP/Authenticate.aspx',
        'feedUrl' => 'http://technet.rapaport.com/HTTP/DLS/GetFile.aspx?ticket='
      ],
      'idex' => [
        'feedUrl' => 'http://idexonline.com/Idex_Feed_API-Full_Inventory?String_Access=*'
      ],
      'independent' => [
        'filesystem' => '/vendors',
      ],
      'belgiumny' => [
        'filesystem' => '/vendors',
        'feedUrl' => 'https://belgiumny.com/api/DeveloperAPI?stock=&CERTKEY=*&APIKEY=*',
      ],
      'belgiumdia' => [
        'filesystem' => '/vendors',
        'feedUrl' => 'https://belgiumdia.com/api/DeveloperAPI?stock=&APIKEY=*',
      ],
      'diamondfoundry' => [
        'filesystem' => '/vendors',
        'feedUrl' => 'https://rest.diamondfoundry.com/api/v2/diamonds?user_email=*&user_token=*',
      ],
      'harikrishna' => [
        'filesystem' => '/vendors',
        'feedUrl' => 'https://service.hk.co/apihkstock?user=*&type=json',
      ],
    ],

    'limitAPI' => [
      'byContentType' => false
    ],

    //Debug Bar Setting
    'debugbar' => [
      'enabled' => true,
      // Enable or disable extra collectors
      'collectors' => [
        'config' => true,
        'monolog' => true,
        'pdo' => true
      ]
    ],

    // Assets Settings
    'assets' => [
      'css_url' => 'assets/css',
      'js_url' => 'assets/js',
      // Load minified CSS and JS files if exists
      'min' => true,
      'css_min_url' => 'assets/css/min',
      'js_min_url' => 'assets/js/min',
    ],

    // Monolog settings
    'logger' => [
      'name' => 'app',
      'path' => __DIR__ . '/../../../logs/',
      'filename' => date("Y-m-d") . '_app.log',
    ],

    // Mongo DB settings
    'mongo' => [
      'host' => '127.0.0.1',
      'port' => 27017,
      'options' => [
        //"username" => 'foo',
        //"password" => 'bar'
      ],
      'driverOptions' => [
        'typeMap' => [
          'root' => 'object',
          'document' => 'object',
          'array' => 'array'
        ]
      ],
      'default_db' => 'DreamStone'
    ],

    // Monolog settings
    'mailer' => [
      'host' => 'tls://smtp.gmail.com:587',
      'username' => '*',
      'password' => '*',
      'fromname' => 'Dream Stone',
      'adminMail' => '*'
    ],

    // Payment providers
    'affirm' => [
      'sandbox' => true,
      'dev' => [
        'public_key' => "RC4NMYWRDSHO6RUW",
        'private_key' => "WY5AFJslIc6SYYEQNaD7G2Ypnrk0gAXY",
        'base_url' => "https://sandbox.affirm.com/api/v2/",
        'js_script' => 'https://cdn1-sandbox.affirm.com/js/v2/affirm.js',
        'confirmation_path' => '/payment/affirm/successful',
        'cancel_path' => '/payment/affirm/cancelled',
      ],
      'prod' => [
        'public_key' => "*",
        'private_key' => "*",
        'base_url' => "https://api.affirm.com/api/v2/",
        'js_script' => 'https://cdn1.affirm.com/js/v2/affirm.js',
        'confirmation_path' => '/payment/affirm/successful',
        'cancel_path' => '/payment/affirm/cancelled',
      ],
    ],
    'paypal' => [
      'sandbox' => true,
      'dev' => [
        'email' => "sb-ohf2c2749357@business.example.com",
        'verify_uri' => 'https://www.sandbox.paypal.com/cgi-bin/webscr',
        'client_id' => "AGiqfEAwI5zzJFvJIvh92PZ3z6XsAmQkQ4SvYXnhYVrQl5SGx96Hnqjq",
        'success_path' => '/payment/paypal/successful',
        'cancel_path' => '/payment/paypal/cancelled',
        'notify_path' => '/payment/paypal/notify',
      ],
      'prod' => [
        'email' => "*",
        'verify_uri' => 'https://www.paypal.com/cgi-bin/webscr',
        'client_id' => "*",
        'success_path' => '/payment/paypal/successful',
        'cancel_path' => '/payment/paypal/cancelled',
        'notify_path' => '/payment/paypal/notify',
      ],
    ],
    'authorizenet' => [
      'sandbox' => true,
      'dev' => [
        'apiLoginId' => "*",
        'transactionKey' => '*',
      ],
      'prod' => [
        'apiLoginId' => "*",
        'transactionKey' => '*',
      ],
    ],

    // Fedex
    'fedex' => [
      'sandbox' => true,
      'dev' => [
        'key' => 'ezlxcoc0sUsa4ab9',
        'password' => 'fMaHk7LRJbXAniE5LOyhnEgqI',
        'account' => '510087780',
        'meter' => '114018063'
      ],
      'prod' => [
        'key' => '*',
        'password' => '*',
        'account' => '*',
        'meter' => '*'
      ],
    ],

    // reCAPTCHA
    'recaptcha' => [
      'siteKey' => '*',
      'projectId' => '*',
      'GOOGLE_APPLICATION_CREDENTIALS' => __DIR__ . '/../../../private/recaptcha/*.json',
    ],
  ]
];
