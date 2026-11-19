<?php
declare(strict_types=1);

namespace Haerriz\Seo\Setup\Patch\Data;

use Magento\Cms\Model\PageFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class CorrectPhoneNumber implements DataPatchInterface
{
    private const PHONE_E164 = '+919443050753';

    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly PageFactory $pageFactory
    ) {}

    public function apply(): self
    {
        $this->configWriter->save('general/store_information/phone', self::PHONE_E164);

        $page = $this->pageFactory->create()->load('home', 'identifier');
        if ($page->getId()) {
            $content = str_replace(
                ['+919442650753', '+91 94426 50753', '9442650753'],
                [self::PHONE_E164, '+91 94430 50753', '9443050753'],
                (string)$page->getContent()
            );
            $page->setContent($content)->save();
        }
        return $this;
    }

    public static function getDependencies(): array { return [FixTitleDecoration::class]; }
    public function getAliases(): array { return []; }
}
