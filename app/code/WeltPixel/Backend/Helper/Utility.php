<?php

namespace WeltPixel\Backend\Helper;

use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use \Magento\Store\Api\StoreRepositoryInterface;
use \Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Utility extends \Magento\Framework\App\Helper\AbstractHelper
{

    /** @var  ThemeProviderInterface */
    protected $themeProvider;

    /** @var  StoreRepositoryInterface */
    protected $storeRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /** @var array  */
    protected $storeThemesLocales = [];

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param ThemeProviderInterface $themeProvider
     * @param StoreRepositoryInterface $storeRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        ThemeProviderInterface $themeProvider,
        StoreRepositoryInterface $storeRepository,
        StoreManagerInterface $storeManager
    )
    {
        parent::__construct($context);
        $this->themeProvider = $themeProvider;
        $this->storeRepository = $storeRepository;
        $this->storeManager = $storeManager;
    }

    public function isPearlThemeUsed($storeCode = null)
    {
        $themeId = $this->scopeConfig->getValue(
            \Magento\Framework\View\DesignInterface::XML_PATH_THEME_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeCode
        );

        $theme = $this->themeProvider->getThemeById($themeId);
        $isPearlTheme = $this->_validatePearlTheme($theme);
        return $isPearlTheme;
    }

    /**
     * @param \Magento\Theme\Model\Theme $theme
     * @return bool
     */
    protected function _validatePearlTheme($theme)
    {
        $pearlThemePath = 'Pearl/weltpixel';
        do {
            if ($theme->getThemePath() == $pearlThemePath) {
                return true;
            }
            $theme = $theme->getParentTheme();
        } while ($theme);

        return false;
    }

    /**
     * @return array
     */
    public function getStoreThemesLocales() {
        if (count($this->storeThemesLocales)) {
            return $this->storeThemesLocales;
        }

        $stores = $this->storeRepository->getList();
        $result = [];
        $isSingleStoreMode = $this->storeManager->isSingleStoreMode();
        foreach ($stores as $store) {
            $storeId = $store["store_id"];
            $websiteId = $store["website_id"];
            if (!$storeId) continue;
            $storeCode = $store["code"];
            $themeId = $this->scopeConfig->getValue(
                \Magento\Framework\View\DesignInterface::XML_PATH_THEME_ID,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeCode
            );

            $locale = $this->scopeConfig->getValue(
                \Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeCode
            );

            if ($isSingleStoreMode) {
                $themeId = $this->scopeConfig->getValue(
                    \Magento\Framework\View\DesignInterface::XML_PATH_THEME_ID,
                    \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
                    $websiteId
                );
                $locale = $this->scopeConfig->getValue(
                    \Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE,
                    \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
                    $websiteId
                );
            }

            $theme = $this->themeProvider->getThemeById($themeId);
            $result[$theme->getThemePath().'/'.$locale] = $storeCode;
        }

        $this->storeThemesLocales = $result;
        return $this->storeThemesLocales;
    }

    /**
     * @return false|\Magento\Csp\Helper\CspNonceProvider
     */
    public function getCspNonceProvider()
    {
        if (class_exists(\Magento\Csp\Helper\CspNonceProvider::class)) {
            return  \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Csp\Helper\CspNonceProvider::class);
        }

        return false;
    }
}
