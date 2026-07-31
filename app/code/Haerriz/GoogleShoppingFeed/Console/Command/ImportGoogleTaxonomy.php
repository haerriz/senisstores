<?php
namespace Haerriz\GoogleShoppingFeed\Console\Command;

use Haerriz\GoogleShoppingFeed\Api\TaxonomyRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedProfile;
use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportGoogleTaxonomy extends Command
{
    const TAXONOMY_URL = 'https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt';

    private $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('haerriz:feed:import-taxonomy')
             ->setDescription('Import official Google Shopping taxonomy categories')
             ->addOption('url', null, InputOption::VALUE_OPTIONAL, 'Taxonomy URL', self::TAXONOMY_URL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url = $input->getOption('url');
        $output->writeln("<info>Fetching taxonomy from: {$url}</info>");

        $content = @file_get_contents($url);
        if ($content === false) {
            $output->writeln('<error>Failed to fetch taxonomy file. Check network access.</error>');
            return Command::FAILURE;
        }

        $connection = $this->resourceConnection->getConnection();
        $table      = $connection->getTableName('haerriz_google_shopping_feed_taxonomy');
        $imported   = 0;

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            // Format: "ID - Category > Path"
            if (preg_match('/^(\d+)\s+-\s+(.+)$/', $line, $matches)) {
                $id   = (int)$matches[1];
                $path = trim($matches[2]);

                $connection->insertOnDuplicate($table, [
                    'taxonomy_id'   => $id,
                    'taxonomy_path' => $path,
                    'source'        => 'google',
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
                $imported++;
            }
        }

        $output->writeln("<info>Imported {$imported} Google Shopping taxonomy categories.</info>");
        return Command::SUCCESS;
    }
}
