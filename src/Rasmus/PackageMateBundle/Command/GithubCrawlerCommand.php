<?php

namespace Rasmus\PackageMateBundle\Command;

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


class GithubCrawlerCommand extends Command
{

  protected function configure()
  {
    $this->setName('rasmus:github-crawler');
  }


  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $time_start = microtime(true);

    $githubClient = new Github_Client();
    $githubClient->authenticate('001a373ab1d3d8f350c0f75692ea36e3397295ee', null, Github_Client::AUTH_HTTP_TOKEN);

    $m = new MongoClient();
    $db = $m->rasmus;
    $packagist_packages = $db->packagist_packages;
    $github_users = $db->github_users;
    $github_users->createIndex(array('userName' => 1, 'repo' => 1));

    $i = 0;
    $n = 0;

    $packagist_packages_cursor = $packagist_packages->find( array( "status" => array( '$ne' => 1 ) ) );
    foreach ( $packagist_packages_cursor as $_id => $package_value )
    {
      $parts = explode('/',$package_value["sourceRepo"]);
      $userName = $parts[0];
      $repoName = $parts[1];

      try{
        $collaborators = $githubClient->api('repo')->contributors($userName, $repoName);
      }
      catch(RuntimeException $e){
        // Probably a not found exception
        $newdata = array( '$set' => array( "status" => 0 ) );
        $packagist_packages->update( array( "packageName" => $package_value["packageName"] ), $newdata );
        continue;
      }
      catch(ApiLimitExceedException $e){
        // API Limit exceeded so stop for now
        break;
      }
      foreach($contributor as $user){
        $document = array( "userName" => $user["login"], "repo" => $package_value["sourceRepo"] );

        try{
          $github_users->insert($document);
        }
        catch(MongoDuplicateKeyException $e){
          echo 'Warn: ' . $user["login"] . ' => ' . $package_value["sourceRepo"] . ' already exists and so is skipped' . PHP_EOL;
        }

        $n++;
      }

      $newdata = array( '$set' => array( "status" => 1 ) );
      $packagist_packages->update( array( "packageName" => $package_value["packageName"] ), $newdata );

      $i++;
    }
    $time_end = microtime(true);
    $time = $time_end - $time_start;

    echo '=== Github contributors loaded into local MongoDB ===' . PHP_EOL;
    echo 'Info: ' . $n . ' new contributors added to ' . $i . ' packages' . PHP_EOL;
    echo 'Info: ' . $github_users->count() . ' contributors currently stored' . PHP_EOL;
    echo 'Info: Script took ' . round($time,2) . ' seconds' . PHP_EOL;
  }
}
