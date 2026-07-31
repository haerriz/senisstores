<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class PreviewService
{
    private $filesystem;
    private $exporter;
    private $validator;

    public function __construct(Filesystem $filesystem, FeedExporter $exporter, ProfileValidator $validator)
    {
        $this->filesystem = $filesystem;
        $this->exporter = $exporter;
        $this->validator = $validator;
    }

    public function preview(FeedProfileInterface $profile, $limit = 10)
    {
        $this->validator->assertValid($profile);
        $limit = max(1, min(100, (int)$limit));
        $path = 'google_feed/preview/' . bin2hex(random_bytes(16)) . '.' . ($profile->getFeedType() ?? 'xml');
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $directory->create('google_feed/preview');
        try {
            $counts = $this->exporter->export($profile, $path, null, $limit);
            $content = '';
            if ($directory->isExist($path)) {
                $content = $directory->readFile($path);
            }
            return [
                'sampled' => true,
                'limit' => $limit,
                'counts' => $counts,
                'format' => $profile->getFeedType(),
                'content' => $content,
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
}
