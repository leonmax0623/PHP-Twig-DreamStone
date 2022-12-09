<?php
/*
 * CRON: 1 3 * * * php src/Core/cli.php app:import-shipping-statuses
 * Run at 3:01 am
 */

namespace DS\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DS\Core\Model\Shipping\Fedex;
use DS\Model\Order;

class ImportShippingStatuses extends Command
{
  private $finalStatuses = [
    'CA', // Shipment Cancelled
    'DE', // Delivery Exception
    'DL', // Delivered
    'RS', // Return to Shipper
    'SE', // Shipment Exception
  ];

  public function __construct(\Slim\Container $c)
  {
    $this->c = $c;
    parent::__construct();
  }

  protected function configure()
  {
    $this
      // the name of the command (the part after "src/Core/cli.php")
      ->setName('app:import-shipping-statuses')
      ->addArgument('print', InputArgument::OPTIONAL, 'Print result to console')

      // the short description shown while running "php src/Core/cli.php list"
      ->setDescription('Import Shipping Statuses')

      // the full command description shown when running the command with
      // the "--help" option
      ->setHelp('This command allows to import shipping statuses from FedEx API')
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $orders = $this->getOrders();
    if (empty($orders)) return;

    $numbers = [];
    foreach ($orders as $order)
      $numbers[] = $order->shipping->tracking->number;

    $StatusDetails = (new Fedex($this->c->settings['fedex']))->track($numbers);
    if ($input->getArgument('print') === 'print') {
      $output->writeln(var_export($StatusDetails) . '');
      return;
    }

    foreach ($StatusDetails as $i => $StatusDetail)
      if (
        isset($StatusDetail['Description'])
        && (
          empty($orders[$i]->shipping->tracking->status)
          || $orders[$i]->shipping->tracking->status !== $StatusDetail['Description']
        )
      ) {
          (new Order($this->c->mongodb))->updateOne(
            ['_id' => $orders[$i]->_id],
            ['$set' => [
              'shipping.tracking.status' => $StatusDetail['Description'],
              'shipping.tracking.raw' => $StatusDetail
            ]]
          );
      }
  }

  private function getOrders()
  {
    return (new Order($this->c->mongodb))->find(['$and' => [
      ['created' => ['$gt' => time() - 86400 * 90]], // last 3 months
      ['shipping.tracking.number' => ['$exists' => true, '$ne' => '']], // skip wo tracking number
      ['$or' => [
        ['shipping.tracking.raw.Code' => ['$exists' => false]],
        ['shipping.tracking.raw.Code' => ['$exists' => true, '$nin' => $this->finalStatuses]], // skip final statuses
      ]],
    ]]);
  }

}
