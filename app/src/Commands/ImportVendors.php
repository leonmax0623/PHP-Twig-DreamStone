<?php
/*
 * CRON: 30 00/12 * * * php src/Core/cli.php app:import-vendors
 */

namespace DS\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DS\Model\Vendor as VendorModel;
use DS\Model\Admin as AdminModel;
use DS\Model\Token as TokenModel;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;

class ImportVendors extends Command
{
    public function __construct(\Slim\Container $c)
    {
        $this->c = $c;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            // the name of the command (the part after "src/Core/cli.php")
            ->setName('app:import-vendors')
            ->addArgument('print', InputArgument::OPTIONAL, 'Print result to console')

            // the short description shown while running "php src/Core/cli.php list"
            ->setDescription('Import Vendors')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("This command allows to import products/diamonds from vendor's API");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $VendorModel = new VendorModel($this->c->mongodb);
        $vendors = $VendorModel->allWhere(['isEnabled' => true]);

        if (empty($vendors)) return;

        // $vendors = array_reverse($vendors);

        if ($input->getArgument('print') === 'print') {
            $output->writeln('');

            return;
        }

        $vendorsUrl = 'https://dreamstone.com/api/v1/import';
        // $vendorsUrl = 'http://ds.local/api/v1/import';

        $this->c->logger->info('Auto Import Started: ' . json_encode(array_map(function ($vendor) {
            return $vendor->type . '.' . $vendor->code;
        }, $vendors)));

        $adminEmail = 'sergey@quadecco.com';
        // $adminEmail = 'admin@admin.com';

        $admin = (new AdminModel($this->c->mongodb))->findOne(['email' => $adminEmail]);
        if (empty($admin)) {
            return;
        }
        $token = uniqid('DS', true);
        (new TokenModel($this->c->mongodb))->insertOne([
            'value' => $token,
            'user_id' => $admin->_id,
            'created' => new \MongoDB\BSON\UTCDateTime(time() * 1000 + 3600000 /* 60 min */),
        ]);

        $headers = [
            'headers' => [
                'Content-Type' => 'application/json',
                'token' => $token,
            ],
        ];

        $this->c->logger->info('Auto Import Vendor Started: Logged in');

        $result = [];
        $client = new \GuzzleHttp\Client($headers);
        foreach ($vendors as $k => $vendor) {
            $type = $vendor->type;
            $code = $vendor->code;
            try {
                $url = $vendorsUrl . ($type == 'independent' ? '/' . $type : '') . '/' . $code;
                // $ret = @fopen($url, 'r', false, stream_context_create([
                //     'ssl' => [
                //         'verify_peer' => false,
                //         'verify_peer_name' => false,
                //     ],
                // ]));
                $this->c->logger->info('Auto Import Vendor Started: ' . $type . ' - ' . $code);
                try {
                    $resp = $client->get($url, [
                        'debug' => true,
                        'timeout' => 300,
                    ]);
                } catch (ClientException $e) {
                    $this->c->logger->error(Psr7\Message::toString($e->getRequest()));
                    $this->c->logger->error(Psr7\Message::toString($e->getResponse()));
                }
                $this->c->logger->info('Auto Import Vendor Finished: ' . $type . ' - ' . $code);

                $status = $resp->getStatusCode();
                $ret = $resp->getBody();

                if ($status != 200) {
                    throw new \Exception('API connection error', 7777);
                }
            } catch (\Exception $e) {
                $result[$k] = $e->getCode() . ' - ' . $e->getMessage();
                $this->c->logger->error('Auto Import Error: ' . $result[$k] . ' ( ' . $url . ' )');
            }
            sleep(10);
        }

        $this->c->logger->info('Auto Import Finished: ' . json_encode($result));
    }
}
