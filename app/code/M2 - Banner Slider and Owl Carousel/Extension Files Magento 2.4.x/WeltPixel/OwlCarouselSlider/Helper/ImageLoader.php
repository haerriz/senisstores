<?php

namespace WeltPixel\OwlCarouselSlider\Helper;

/**
 * Class ImageLoader
 * @package WeltPixel\OwlCarouselSlider\Helper
 */
class ImageLoader extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @return boolean
     */
    public function useDefaultLoader()
    {
        return true;
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getLoadingImageUrl()
    {
        return '';
    }

}
