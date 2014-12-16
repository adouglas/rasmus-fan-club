<?php

namespace Rasmus\PackageMateBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Github\Client AS Github_Client;


/**
* Hello World command for demo purposes.
*
* You could also extend from Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand
* to get access to the container via $this->getContainer().
*
* @author Tobias Schultze <http://tobion.de>
*/
class GithubCrawlerCommand extends Command
{
  /**
  * {@inheritdoc}
  */
  protected function configure()
  {
    $this->setName('rasmus:github-crawler');
  }

  /**
  * {@inheritdoc}
  */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $githubClient = new Github_Client();

    $githubClient->authenticate('001a373ab1d3d8f350c0f75692ea36e3397295ee', null, Github_Client::AUTH_HTTP_TOKEN);


    $collaborators = $githubClient->api('repo')->contributors($parts[0], $parts[1]);
    foreach($collaborators as $user){
      var_dump($user["login"]);
    }

  }
}
