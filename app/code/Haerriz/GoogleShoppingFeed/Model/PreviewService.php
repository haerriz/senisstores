<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\ProductProviderInterface;
use Haerriz\GoogleShoppingFeed\Api\ProductTypeResolverInterface;
use Haerriz\GoogleShoppingFeed\Api\RowValidatorInterface;
use Haerriz\GoogleShoppingFeed\Model\Mapping\RowBuilder;
use Haerriz\GoogleShoppingFeed\Model\Quality\CompletenessScorer;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;

class PreviewService
{
    private Filesystem $filesystem;
    private FeedExporter $exporter;
    private ProfileValidator $validator;
    private ProductProviderInterface $productProvider;
    private ProductTypeResolverInterface $productTypeResolver;
    private RowBuilder $rowBuilder;
    private RuleFactory $ruleFactory;
    private RowValidatorInterface $rowValidator;
    private CompletenessScorer $completenessScorer;
    private ResourceConnection $resourceConnection;

    public function __construct(
        Filesystem $filesystem,
        FeedExporter $exporter,
        ProfileValidator $validator,
        ProductProviderInterface $productProvider,
        ProductTypeResolverInterface $productTypeResolver,
        RowBuilder $rowBuilder,
        RuleFactory $ruleFactory,
        RowValidatorInterface $rowValidator,
        CompletenessScorer $completenessScorer,
        ResourceConnection $resourceConnection
    ) {
        $this->filesystem = $filesystem;
        $this->exporter = $exporter;
        $this->validator = $validator;
        $this->productProvider = $productProvider;
        $this->productTypeResolver = $productTypeResolver;
        $this->rowBuilder = $rowBuilder;
        $this->ruleFactory = $ruleFactory;
        $this->rowValidator = $rowValidator;
        $this->completenessScorer = $completenessScorer;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Build in-memory sample rows for Quick View (no file write required).
     *
     * @param array{dry_run_changed?: bool} $options
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   row_count: int,
     *   format: string,
     *   channel: string,
     *   field_errors: array<int, array{field: string, message: string, sku: string}>,
     *   completeness: array<string, mixed>
     * }
     */
    public function buildSample(FeedProfileInterface $profile, int $limit = 10, array $options = []): array
    {
        $this->assertProfileValid($profile);
        $limit = max(1, min(100, $limit));
        $dryRunChanged = !empty($options['dry_run_changed']);

        $rule = $this->createRule($profile);
        $collection = $this->productProvider->getCollection($profile, $rule, 0, max($limit * 5, 25));
        if ($dryRunChanged) {
            $this->applyChangedSinceLastJobFilter($collection, $profile);
        }
        $this->productTypeResolver->prepare($collection, $profile);

        $rows = [];
        $fieldErrors = [];
        foreach ($collection as $product) {
            foreach ($this->productTypeResolver->resolve($product, $profile) as $feedProduct) {
                if ($rule && !$rule->getConditions()->validate($feedProduct)) {
                    continue;
                }
                try {
                    $row = $this->rowBuilder->build($feedProduct, $profile);
                    $rows[] = $row;
                    $validation = $this->rowValidator->validate($this->normalizeRowForValidation($row));
                    if (!$validation->isValid()) {
                        $sku = (string)$feedProduct->getSku();
                        foreach ($validation->getErrors() as $error) {
                            $fieldErrors[] = [
                                'field' => $this->extractFieldFromError((string)$error),
                                'message' => (string)$error,
                                'sku' => $sku,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    $fieldErrors[] = [
                        'field' => '_row',
                        'message' => $e->getMessage(),
                        'sku' => (string)$feedProduct->getSku(),
                    ];
                    continue;
                }
                if (count($rows) >= $limit) {
                    break 2;
                }
            }
        }

        $completeness = $this->completenessScorer->score($profile, min(50, max(10, $limit * 2)));

        return [
            'rows' => $rows,
            'row_count' => count($rows),
            'format' => $this->resolveFormat($profile),
            'channel' => (string)$profile->getFeedType(),
            'field_errors' => $fieldErrors,
            'completeness' => $completeness,
        ];
    }

    /**
     * @param array{dry_run_changed?: bool} $options
     * @return array<string, mixed>
     */
    public function preview(FeedProfileInterface $profile, $limit = 10, array $options = [])
    {
        $this->assertProfileValid($profile);
        $limit = max(1, min(100, (int)$limit));
        $sample = $this->buildSample($profile, $limit, $options);

        $extension = $this->resolveExtension($profile);
        $path = 'google_feed/preview/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $directory->create('google_feed/preview');

        try {
            $counts = $this->exporter->export($profile, $path, null, $limit);
            $content = $directory->isExist($path) ? $directory->readFile($path) : '';

            return [
                'sampled' => true,
                'limit' => $limit,
                'counts' => $counts,
                'content' => $content,
                'row_count' => (int)($sample['row_count'] ?? ($counts['exported'] ?? 0)),
                'format' => $sample['format'],
                'channel' => $sample['channel'],
                'field_errors' => $sample['field_errors'],
                'completeness' => $sample['completeness'],
                'dry_run_changed' => !empty($options['dry_run_changed']),
                'extension' => $extension,
            ];
        } finally {
            if ($directory->isExist($path)) {
                $directory->delete($path);
            }
        }
    }

    public function generatePreview(FeedProfileInterface $profile, $limit = 10)
    {
        return $this->preview($profile, $limit);
    }

    private function assertProfileValid(FeedProfileInterface $profile): void
    {
        if (method_exists($this->validator, 'assertValid')) {
            $this->validator->assertValid($profile);
            return;
        }

        $errors = $this->validator->validate($profile);
        $blocking = array_filter($errors, static function ($error) {
            $message = (string)$error;
            return !str_contains(strtolower($message), 'mapping');
        });
        if ($blocking) {
            throw new \InvalidArgumentException(implode(' ', $blocking));
        }
    }

    private function createRule(FeedProfileInterface $profile)
    {
        $serialized = $profile->getConditionsSerialized();
        if (!$serialized) {
            return null;
        }
        $conditions = json_decode($serialized, true);
        if (!$conditions) {
            return null;
        }
        $rule = $this->ruleFactory->create();
        $rule->getConditions()->loadArray($conditions);
        return $rule;
    }

    private function applyChangedSinceLastJobFilter($collection, FeedProfileInterface $profile): void
    {
        $profileId = (int)$profile->getId();
        if ($profileId <= 0) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $jobTable = $connection->getTableName('haerriz_google_shopping_feed_job');
        $since = $connection->fetchOne(
            $connection->select()
                ->from($jobTable, ['finished_at'])
                ->where('profile_id = ?', $profileId)
                ->where('status IN (?)', ['done', 'success', 'completed'])
                ->where('finished_at IS NOT NULL')
                ->order('finished_at DESC')
                ->limit(1)
        );

        if (!$since) {
            return;
        }

        $collection->addAttributeToFilter('updated_at', ['gteq' => $since]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRowForValidation(array $row): array
    {
        $normalized = $row;
        foreach ($row as $key => $value) {
            $plain = strtolower(str_replace(['g:', ' '], ['', '_'], (string)$key));
            if (!isset($normalized[$plain])) {
                $normalized[$plain] = $value;
            }
            if ($plain === 'id' && !isset($normalized['id'])) {
                $normalized['id'] = $value;
            }
            if ($plain === 'title' && !isset($normalized['title'])) {
                $normalized['title'] = $value;
            }
        }
        return $normalized;
    }

    private function extractFieldFromError(string $error): string
    {
        if (preg_match('/field:\s*([a-z0-9:_-]+)/i', $error, $matches)) {
            return $matches[1];
        }
        return '_validation';
    }

    private function resolveFormat(FeedProfileInterface $profile): string
    {
        $extension = $this->resolveExtension($profile);
        if (in_array($extension, ['csv', 'tsv', 'txt'], true)) {
            return 'csv';
        }
        if (in_array($extension, ['jsonl', 'json'], true)) {
            return 'jsonl';
        }
        return 'xml';
    }

    private function resolveExtension(FeedProfileInterface $profile): string
    {
        $filename = (string)$profile->getFilename();
        $ext = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['xml', 'csv', 'tsv', 'txt', 'jsonl', 'json'], true)) {
            return $ext === 'json' ? 'jsonl' : $ext;
        }

        $feedType = strtolower((string)$profile->getFeedType());
        if (str_contains($feedType, 'json')) {
            return 'jsonl';
        }
        if (str_contains($feedType, 'csv') || str_contains($feedType, 'meta') || str_contains($feedType, 'tiktok')
            || str_contains($feedType, 'amazon') || str_contains($feedType, 'ebay') || str_contains($feedType, 'pinterest')
            || str_contains($feedType, 'snapchat') || str_contains($feedType, 'instagram') || str_contains($feedType, 'rakuten')
        ) {
            return 'csv';
        }

        return 'xml';
    }
}
