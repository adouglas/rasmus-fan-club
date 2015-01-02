<?php

namespace Rasmus\PackageMateBundle\Command;

// Not a great solution to allow a long running process but works for now
set_time_limit(0);

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Packagist\Api\Client AS Packagist_Client;
use MongoClient;
use MongoDuplicateKeyException;

/**
*	A simple commandline tool to fetch a list of all PHP projects listed on Packagist and
*	form a collecton of pairs in the form: package -> Github repo.
*/
class PackagistCrawlerCommand extends Command {
  /**
  * @inheritDoc
  */
  protected function configure() {
    $this->setName('rasmus:packagist-crawler');
  }

  /**
  * @inheritDoc
  */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $time_start = microtime(true);

    // Uses the Packagist API client from https://github.com/KnpLabs/packagist-api
    $packagistClient = new Packagist_Client();

    $m = new MongoClient();
    $db = $m->rasmus;
    $collection = $db->packagist_packages;

    // Ensure that packages are only listed once
    $collection->ensureIndex(array(
      'packageName' => 1
    ), array(
      'unique' => true
    ));

    $i = 0;

    // Fetch a list of all the packages listed on Packagist, then loop over this list
    foreach ($packagistClient->all() as $packageName) {

      // Fetch the details of a specific package
      $package = $packagistClient->get($packageName);
      // Only record projects wth source stored on Github
      if (strpos($package->getRepository(), 'github.com') !== FALSE) {
        // Extract the user name (owner) and repo name from the git address
        preg_match('/(\/|\:)([\w\-]+[^\/#?\s]+\/[\w\-]+[^\.\/#?\s]+)(\.git)?$/i', $package->getRepository(), $matches);

        if (count($matches) > 2) {
          // Create an add a docuemtn to the collection in the form package name -> repo name
          $document = array(
          'packageName' => $packageName,
          'sourceRepo' => $matches[2]
          );
          try {
            $collection->insert($document);
          }
          catch (MongoDuplicateKeyException $e) {
            // For any duplicates do not add but carry on with the rest of the list
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
    echo 'Info: Script took ' . round($time, 2) . ' seconds' . PHP_EOL;
  }
}
