<?php

namespace Rasmus\PackageMateBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Packagist\Api\Client AS Packagist_Client;


/**
 * Hello World command for demo purposes.
 *
 * You could also extend from Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand
 * to get access to the container via $this->getContainer().
 *
 * @author Tobias Schultze <http://tobion.de>
 */
class PackagistCrawlerCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('rasmus:packagist-crawler');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
      $packagistClient = new Packagist_Client();
      foreach ($packagistClient->all() as $packageName) {
        $package = $packagistClient->get($packageName);
        if(strpos($package->getRepository(), 'github.com') !== FALSE){
          preg_match ( '/(\/|\:)([\w\-]+[^\/#?\s]+\/[\w\-]+[^\.\/#?\s]+)(\.git)?$/i' , $package->getRepository(), $matches );

          if(count($matches) > 2){
            var_dump($package->getRepository());
            $parts = explode('/',$matches[2]);
          }
        }
      }
    }
}
