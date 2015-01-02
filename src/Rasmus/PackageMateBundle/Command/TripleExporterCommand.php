<?php

namespace Rasmus\PackageMateBundle\Command;

// Not a great solution to allow a long running process but works for now
set_time_limit(0);

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MongoClient;

/**
*
*/
class TripleExporterCommand extends Command {

  /**
  * [configure description]
  * @return [type] [description]
  */
  protected function configure() {
    $this->setName('rasmus:triple-exporter');
  }

  /**
  * [execute description]
  * @param  InputInterface  $input  [description]
  * @param  OutputInterface $output [description]
  * @return [type]                  [description]
  */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $time_start = microtime(true);

    $m = new MongoClient();
    $db = $m->rasmus;
    $packagist_packages = $db->packagist_packages;
    $github_users = $db->github_users;

    $i = 0;
    $n = 0;

    $packagist_packages_cursor = $packagist_packages->find(array(
      "status" => 1
    ));
    foreach ($packagist_packages_cursor as $_id => $package_value) {

      $repositoryHash = md5($package_value["sourceRepo"]);
      // Print the following values
      // _:$_id rdf:type ont:package.
      echo '_:' . $_id . ' rdf:type ont:package.' . PHP_EOL;
      // _:$_id ont:packageName "$package_value["packageName"] )".
      echo '_:' . $_id . ' ont:packageName "' . $package_value["packageName"] . '".' . PHP_EOL;
      // _:$_id rdfs:seeAlso <https://packagist.org/packages/$package_value["packageName"] )>.
      echo '_:' . $_id . ' rdfs:seeAlso <https://packagist.org/packages/' . $package_value["packageName"] . ' )>.' . PHP_EOL;
      // _:$repositoryHash rdf:type ont:repository.
      echo '_:' . $repositoryHash . ' rdf:type ont:repository.' . PHP_EOL;
      // _:$repositoryHash ont:repostoryName "$package_value["sourceRepo"]".
      echo '_:' . $repositoryHash . ' ont:repostoryName "' . $package_value["sourceRepo"] . '".' . PHP_EOL;
      // _:$repositoryHash rdfs:seeAlso <https://github.com/$package_value["sourceRepo"]>.
      echo '_:' . $repositoryHash . ' rdfs:seeAlso <https://github.com/' . $package_value["sourceRepo"] . '>.' . PHP_EOL;
      // _:$_id ont:hasRepository _:$repositoryHash.
      echo '_:' . $_id . ' ont:hasRepository _:' . $repositoryHash . '.' . PHP_EOL;
      // _:$repositoryHash ont:hasPackage _:$_id.
      echo '_:' . $repositoryHash . ' ont:hasPackage _:' . $_id . '.' . PHP_EOL;

      $github_users_cursor = $github_users->find(array(
      "repo" => $package_value["sourceRepo"]
      ));
      foreach ($github_users_cursor as $_uid => $user_value) {
        $userHash = md5($user_value["userName"]);
        // _:$_uid rdf:type ont:developer.
        echo '_:' . $userHash . ' rdf:type ont:developer.' . PHP_EOL;
        // _:$userHash ont:name "$user_value["userName"]".
        echo '_:' . $userHash . ' ont:name "' . $user_value["userName"] . '".' . PHP_EOL;
        // _:$userHash rdfs:seeAlso <https://github.com/$user_value["userName"]>.
        echo '_:' . $userHash . ' rdfs:seeAlso <https://github.com/' . $user_value["userName"] . '>.' . PHP_EOL;
        // _:$userHash ont:contributorOn _:$repositoryHash.
        echo '_:' . $userHash . ' ont:contributorOn _:' . $repositoryHash . '.' . PHP_EOL;
        // _:$repositoryHash ont:hasContributor _:$userHash.
        echo '_:' . $repositoryHash . ' ont:hasContributor _:' . $userHash . '.' . PHP_EOL;
        $n++;
      }

      $i++;
    }

    $time_end = microtime(true);
    $time = $time_end - $time_start;

    echo '=== Package and contributor Turtle created from MongoDB ===' . PHP_EOL;
    echo 'Info: ' . $n . ' contributors added to ' . $i . ' packages' . PHP_EOL;
    echo 'Info: Script took ' . round($time, 2) . ' seconds' . PHP_EOL;

  }
}
