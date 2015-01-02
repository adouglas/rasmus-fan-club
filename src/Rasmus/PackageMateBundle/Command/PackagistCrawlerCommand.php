<?php

namespace Rasmus\PackageMateBundle\Command;

set_time_limit(0);

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Packagist\Api\Client AS Packagist_Client;
use MongoClient;
use MongoDuplicateKeyException;


class PackagistCrawlerCommand extends Command
{

    protected function configure()
    {
        $this->setName('rasmus:packagist-crawler');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
      $time_start = microtime(true);

      $packagistClient = new Packagist_Client();

      $m = new MongoClient();
      $db = $m->rasmus;
      $collection = $db->packagist_packages;
      $collection->ensureIndex( array( "packageName" => 1 ), array( "unique" => true) );

      $i = 0;

      foreach ($packagistClient->all() as $packageName) {
        $package = $packagistClient->get($packageName);
        if(strpos($package->getRepository(), 'github.com') !== FALSE){
          preg_match ( '/(\/|\:)([\w\-]+[^\/#?\s]+\/[\w\-]+[^\.\/#?\s]+)(\.git)?$/i' , $package->getRepository(), $matches );

          if(count($matches) > 2){
            $document = array( "packageName" => $packageName, "sourceRepo" => $matches[2] );
            try{
              $collection->insert($document);
            }
            catch(MongoDuplicateKeyException $e){
              echo 'Warn: ' . $packageName . ' already exists and so is skipped' . PHP_EOL;
              continue;
            }
          }
          $i++;
        }
      }

      $time_end = microtime(true);
      $time = $time_end - $time_start;

      echo '=== Sparse Packagist archive loaded into local MongoDB ===' . PHP_EOL;
      echo 'Info: ' . $i . ' new packages added' . PHP_EOL;
      echo 'Info: ' . $collection->count() . ' packages currently stored' . PHP_EOL;
      echo 'Info: Script took ' . round($time,2) . ' seconds' . PHP_EOL;
    }
}
