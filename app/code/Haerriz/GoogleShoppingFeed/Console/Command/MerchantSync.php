<?php
namespace Haerriz\GoogleShoppingFeed\Console\Command;

use Haerriz\GoogleShoppingFeed\Model\Api\ProductSynchronizer;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Api\ProductProviderInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MerchantSync extends Command
{
    private $synchronizer;
    private $profileRepository;
    private $productProvider;
    private $searchCriteriaBuilder;

    public function __construct(
        ProductSynchronizer $synchronizer,
        FeedProfileRepositoryInterface $profileRepository,
        ProductProviderInterface $productProvider,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->synchronizer          = $synchronizer;
        $this->profileRepository     = $profileRepository;
        $this->productProvider       = $productProvider;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('haerriz:feed:merchant-sync')
             ->setDescription('Synchronize catalog items with Google Merchant Center API')
             ->addOption('store', 's', InputOption::VALUE_OPTIONAL, 'Store ID', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $storeId = (int)$input->getOption('store');
        $output->writeln("<info>Starting Google Merchant Center synchronization (store={$storeId})...</info>");

        $criteria = $this->searchCriteriaBuilder
            ->addFilter('status', 1)
            ->addFilter('feed_type', 'google_shopping_v1')
            ->create();

        $profiles = $this->profileRepository->getList($criteria)->getItems();

        if (empty($profiles)) {
            $output->writeln('<comment>No active Google Shopping profiles found.</comment>');
            return Command::SUCCESS;
        }

        foreach ($profiles as $profile) {
            $output->writeln("Syncing profile: <info>{$profile->getName()}</info>");
            $collection = $this->productProvider->getCollection($profile);
            $products   = $collection->getItems();

            $result = $this->synchronizer->sync($products, $storeId);
            $output->writeln("  Synced: {$result['synced']}, Status: {$result['status']}");

            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $err) {
                    $output->writeln("  <error>{$err}</error>");
                }
            }
        }

        $output->writeln('<info>Merchant Center synchronization complete.</info>');
        return Command::SUCCESS;
    }
}
