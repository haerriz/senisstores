<?php
namespace Haerriz\GoogleShoppingFeed\Console\Command;

use Haerriz\GoogleShoppingFeed\Api\FeedJobRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\Generation\Orchestrator;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConsumeFeedJobs extends Command
{
    private $jobRepository;
    private $profileRepository;
    private $orchestrator;
    private $searchCriteriaBuilder;

    public function __construct(
        FeedJobRepositoryInterface $jobRepository,
        FeedProfileRepositoryInterface $profileRepository,
        Orchestrator $orchestrator,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->jobRepository         = $jobRepository;
        $this->profileRepository     = $profileRepository;
        $this->orchestrator          = $orchestrator;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('haerriz:feed:consume-jobs')
             ->setDescription('Consume queued asynchronous feed generation jobs')
             ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Max jobs to process', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = (int)$input->getOption('limit');
        $output->writeln("<info>Processing up to {$limit} queued feed jobs...</info>");

        $criteria = $this->searchCriteriaBuilder
            ->addFilter('status', 'pending')
            ->setPageSize($limit)
            ->create();

        $jobs      = $this->jobRepository->getList($criteria)->getItems();
        $processed = 0;

        foreach ($jobs as $job) {
            try {
                $profile = $this->profileRepository->getById((int)$job->getProfileId());
                $output->writeln("Processing job #{$job->getId()} for profile [{$profile->getName()}]...");
                $this->orchestrator->run($profile, 'queue');
                $processed++;
                $output->writeln("  <info>Done.</info>");
            } catch (\Exception $e) {
                $output->writeln("  <error>Failed: {$e->getMessage()}</error>");
            }
        }

        $output->writeln("<info>{$processed} job(s) processed.</info>");
        return Command::SUCCESS;
    }
}
