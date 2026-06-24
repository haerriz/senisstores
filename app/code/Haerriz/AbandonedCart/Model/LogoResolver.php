<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\AbandonedCart\Model;

use Magento\Email\Model\Design\Backend\Logo;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class LogoResolver
{
    private const XML_PATH_EMAIL_LOGO = 'design/email/logo';
    private const XML_PATH_EMAIL_LOGO_ALT = 'design/email/logo_alt';
    private const XML_PATH_EMAIL_LOGO_WIDTH = 'design/email/logo_width';
    private const XML_PATH_HEADER_LOGO = 'design/header/logo_src';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Filesystem $filesystem
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
    }

    /**
     * @param int $storeId
     * @return array<string, mixed>
     */
    public function resolve($storeId)
    {
        $store = $this->storeManager->getStore($storeId);
        $mediaBase = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        $logoFile = (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_LOGO,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $url = '';
        if ($logoFile !== '') {
            $relativePath = Logo::UPLOAD_DIR . '/' . ltrim($logoFile, '/');
            if ($this->mediaFileExists($relativePath)) {
                $url = $mediaBase . $relativePath;
            }
        }

        if ($url === '') {
            $headerLogo = (string) $this->scopeConfig->getValue(
                self::XML_PATH_HEADER_LOGO,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            if ($headerLogo !== '') {
                $relativePath = 'logo/' . ltrim($headerLogo, '/');
                if ($this->mediaFileExists($relativePath)) {
                    $url = $mediaBase . $relativePath;
                }
            }
        }

        if ($url === '') {
            $fallback = 'favicon/stores/' . $storeId . '/senisstores-logo.png';
            if ($this->mediaFileExists($fallback)) {
                $url = $mediaBase . $fallback;
            }
        }

        $alt = (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_LOGO_ALT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($alt === '') {
            $alt = (string) $store->getFrontendName();
        }

        $width = (int) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_LOGO_WIDTH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($width <= 0) {
            $width = 200;
        }

        return [
            'url' => $url,
            'alt' => $alt,
            'width' => $width,
        ];
    }

    /**
     * @param string $relativePath
     * @return bool
     */
    private function mediaFileExists($relativePath)
    {
        try {
            $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            return $media->isFile($relativePath);
        } catch (\Exception $e) {
            return false;
        }
    }
}
