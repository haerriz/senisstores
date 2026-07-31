<?php
namespace Haerriz\GoogleShoppingFeed\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\Mapping\RowBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;

class ValidateFeed extends Command
{
    private $profileRepository;
    private $searchCriteriaBuilder;
    private $rowBuilder;

    public function __construct(
        FeedProfileRepositoryInterface $profileRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RowBuilder $rowBuilder
    ) {
        $this->profileRepository = $profileRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->rowBuilder = $rowBuilder;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('haerriz:feed:validate')
             ->setDescription('Validate feed profile configurations');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $profiles = $this->profileRepository->getList($searchCriteria)->getItems();

        if (empty($profiles)) {
            $output->writeln("<comment>No feed profiles found to validate.</comment>");
            return Command::SUCCESS;
        }

        $hasErrors = false;
        $output->writeln("Validating <info>" . count($profiles) . "</info> feed profile(s)...");

        foreach ($profiles as $profile) {
            $id = $profile->getEntityId() ?? $profile->getProfileId();
            $output->writeln("Checking Profile #{$id} [{$profile->getName()}]...");

            $errors = $this->rowBuilder->validate($profile);
            if (!empty($errors)) {
                $hasErrors = true;
                foreach ($errors as $error) {
                    $output->writeln("  <error>ERROR: {$error}</error>");
                }
            } else {
                $output->writeln("  <info>VALID</info> - Extension: " . pathinfo($profile->getFilename(), PATHINFO_EXTENSION));
            }
        }

        if ($hasErrors) {
            $output->writeln("<error>Validation failed for one or more profiles.</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>All feed profile configurations are valid!</info>");
        return Command::SUCCESS;
    }
}
