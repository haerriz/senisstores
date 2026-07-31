<?php
namespace Haerriz\GoogleShoppingFeed\Console\Command;

use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\Generation\Orchestrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateFeed extends Command
{
    private $profileRepository;
    private $orchestrator;

    public function __construct(
        FeedProfileRepositoryInterface $profileRepository,
        Orchestrator $orchestrator
    ) {
        $this->profileRepository = $profileRepository;
        $this->orchestrator      = $orchestrator;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('haerriz:feed:generate')
             ->setDescription('Generate product feed for profile')
             ->addOption('profile', 'p', InputOption::VALUE_REQUIRED, 'Profile ID')
             ->addOption('all', 'a', InputOption::VALUE_NONE, 'Generate all active profiles');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $all       = (bool)$input->getOption('all');
        $profileId = (int)$input->getOption('profile');

        if (!$all && $profileId <= 0) {
            $output->writeln('<error>Please specify --profile ID or --all.</error>');
            return Command::FAILURE;
        }

        try {
            if ($all) {
                $criteria = (new \Magento\Framework\Api\SearchCriteriaBuilder())->addFilter('status', 1)->create();
                $profiles = $this->profileRepository->getList($criteria)->getItems();
            } else {
                $profiles = [$this->profileRepository->getById($profileId)];
            }

            foreach ($profiles as $profile) {
                $output->writeln("Generating: <info>{$profile->getName()}</info> ({$profile->getFilename()})");
                $result = $this->orchestrator->run($profile, 'cli');
                $output->writeln("  <info>exported={$result['exported']}, size={$result['fileSize']}B</info>");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Failed: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}
