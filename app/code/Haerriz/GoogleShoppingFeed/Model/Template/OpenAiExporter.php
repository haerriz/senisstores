<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class OpenAiExporter
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Export products into minor-unit encoded compressed jsonl.gz format
     *
     * @param array $products
     * @param string $filename
     * @param array $mapping
     * @return bool
     */
    public function export($products, $filename, $mapping)
    {
        try {
            $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $absolutePath = $directory->getAbsolutePath('google_feed/' . $filename);

            $gz = gzopen($absolutePath, 'w9');
            if (!$gz) {
                return false;
            }

            foreach ($products as $product) {
                $data = [];
                foreach ($mapping as $map) {
                    $googleAttr = $map['google_attribute'];
                    $val = $product->getData($map['magento_attribute']);

                    // Convert to minor unit representation
                    if ($googleAttr === 'price') {
                        $val = (int)($val * 100);
                    }

                    $data[$googleAttr] = $val;
                }
                gzwrite($gz, json_encode($data) . "\n");
            }

            gzclose($gz);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
