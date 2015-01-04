<?php

namespace Rasmus\PackageMateBundle\Command;

// Not a great solution to allow a long running process but works for now
set_time_limit(0);

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Github\Client AS Github_Client;
use Github\Exception\RuntimeException;
use Github\Exception\ApiLimitExceedException;
use MongoClient;
use MongoDuplicateKeyException;

/**
*	A simple commandline tool to loop over a collection of Packagist package -> Github repo pairs and
*	identify the contributors for the given repo. These are then saved in a collection of Github repo ->
*	contributor pairs. Duplicate pairs are ignored.
*/
class GithubCrawlerCommand extends Command {

  /**
  * @inheritDoc
  */
  protected function configure() {
    $this->setName('rasmus:github-crawler');
  }

  /**
  * @inheritDoc
  */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $time_start = microtime(true);

    // Using the Github API client from https://github.com/KnpLabs/php-github-api
    $githubClient = new Github_Client();
    // Authenticated user to raise the hourly request limit to 5000
    $githubClient->authenticate('001a373ab1d3d8f350c0f75692ea36e3397295ee', null, Github_Client::AUTH_HTTP_TOKEN);

    // Initialize MongoDB client
    $m = new MongoClient();
    $db = $m->rasmus;
    $packagist_packages = $db->packagist_packages;
    $github_users = $db->github_users;
    // If not already created set these fields as indexes
    $github_users->createIndex(array(
      'userName' => 1,
      'repo' => 1
    ));

    $i = 0;
    $n = 0;

    // Find only records with status not equal "1"
    // These are records which have not been processed yet
    // (usefull to protect against fallovers or it this process is to be
    // run multiple times on the same data set)
    $packagist_packages_cursor = $packagist_packages->find(array(
      'status' => array(
      '$ne' => 1
    )));

    // For each re
    foreach ($packagist_packages_cursor as $_id => $package_value) {
      $parts = explode('/', $package_value['sourceRepo']);
      $userName = $parts[0];
      $repoName = $parts[1];

      try {
        // Fetch the contributors for this repo
        $contributor = $githubClient->api('repo')->contributors($userName, $repoName);
      }
      catch (RuntimeException $e) {
        // Probably a not found exception
        $newdata = array(
          '$set' => array(
            'status' => 0
          )
        );

        $packagist_packages->update(array(
          'packageName' => $package_value['packageName']
        ), $newdata);

        continue;
      }
      catch (ApiLimitExceedException $e) {
        // API Limit exceeded so stop for now
        echo 'Warn: API Limit exceeded!' . PHP_EOL;
        break;
      }

      // For each contributor create a record linking the contributor to the repo
      foreach ($contributor as $user) {
        $document = array(
          'userName' => $user['login'],
          'repo' => $package_value['sourceRepo']
        );

        try {
          $github_users->insert($document);
        }
        catch (MongoDuplicateKeyException $e) {
          // Ignore duplicates (output a message and carry on)
          echo 'Warn: ' . $user['login'] . ' => ' . $package_value['sourceRepo'] . ' already exists and so is skipped' . PHP_EOL;
        }

        $n++;
      }

      $newdata = array(
        '$set' => array(
          'status' => 1
        )
      );

      // Once all done update the status of the package record so that it will
      // not be processed again
      $packagist_packages->update(array(
        'packageName' => $package_value['packageName']
      ), $newdata);

      $i++;
    }

    $time_end = microtime(true);
    $time = $time_end - $time_start;

    echo '=== Github contributors loaded into local MongoDB ===' . PHP_EOL;
    echo 'Info: ' . $n . ' new contributors added to ' . $i . ' packages' . PHP_EOL;
    echo 'Info: ' . $github_users->count() . ' contributors currently stored' . PHP_EOL;
    echo 'Info: Script took ' . round($time, 2) . ' seconds' . PHP_EOL;
  }
}
