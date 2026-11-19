<?php
declare(strict_types=1);

namespace Haerriz\Seo\Setup\Patch\Data;

use Magento\Cms\Model\PageFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class FixTitleDecoration implements DataPatchInterface
{
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly PageFactory $pageFactory
    ) {}

    public function apply(): self
    {
        $this->configWriter->delete('design/head/title_prefix', 'stores', 1);
        $this->configWriter->delete('design/head/title_suffix', 'stores', 1);

        $page = $this->pageFactory->create()->load('home', 'identifier');
        if ($page->getId()) {
            $content = str_replace(
                ['<h1>Hardware Store in Theni with Delivery Across India</h1>'],
                ['<h2>Hardware Store in Theni with Delivery Across India</h2>'],
                (string)$page->getContent()
            );
            $page->setContent($content)->save();
        }
        return $this;
    }

    public static function getDependencies(): array { return [ImproveSeo::class]; }
    public function getAliases(): array { return []; }
}
