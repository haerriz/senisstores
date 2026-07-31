<?php
namespace Haerriz\GoogleShoppingFeed\Console\Command;

use Haerriz\GoogleShoppingFeed\Model\Api\StatusReconciliation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MerchantReconcile extends Command
{
    private $reconciliation;

    public function __construct(StatusReconciliation $reconciliation)
    {
        $this->reconciliation = $reconciliation;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('haerriz:feed:merchant-reconcile')
             ->setDescription('Reconcile Merchant API product approval statuses')
             ->addOption('store', 's', InputOption::VALUE_OPTIONAL, 'Store ID', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $storeId = (int)$input->getOption('store');
        $output->writeln("<info>Reconciling Merchant Center statuses (store={$storeId})...</info>");

        $result = $this->reconciliation->reconcile($storeId);

        if ($result['reconciled']) {
            $output->writeln("<info>Reconciled {$result['count']} products.</info>");
        } else {
            $output->writeln('<error>Reconciliation failed: ' . ($result['error'] ?? $result['reason'] ?? 'unknown') . '</error>');
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
