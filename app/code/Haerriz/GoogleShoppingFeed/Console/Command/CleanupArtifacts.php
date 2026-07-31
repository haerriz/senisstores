<?php
namespace Haerriz\GoogleShoppingFeed\Console\Command;

use Haerriz\GoogleShoppingFeed\Model\Artifact\RetentionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupArtifacts extends Command
{
    private $retentionManager;

    public function __construct(RetentionManager $retentionManager)
    {
        $this->retentionManager = $retentionManager;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('haerriz:feed:cleanup-artifacts')
             ->setDescription('Remove expired historical feed artifacts')
             ->addOption('keep', 'k', InputOption::VALUE_OPTIONAL, 'Artifacts to keep per profile', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $keep = (int)$input->getOption('keep');
        $output->writeln("<info>Cleaning up artifacts (keeping last {$keep} per profile)...</info>");

        $deleted = $this->retentionManager->cleanup($keep);

        $output->writeln("<info>Deleted {$deleted} expired artifact(s).</info>");
        return Command::SUCCESS;
    }
}
