<?php

namespace WeltPixel\OwlCarouselSlider\Block\Adminhtml\System\Config;

/**
 * Class ImportantNote
 * @package WeltPixel\OwlCarouselSlider\Block\Adminhtml\System\Config
 */
class ImportantNote extends \Magento\Config\Block\System\Config\Form\Field
{
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $html = '<div class="importany-note">'
            . "<p>Get the absolute most out of the product you're using by upgrading to the PRO version, which comes with a number of awesome benefits, including:</p>"
            . "<ul>
                    <li>
                    <strong>Access to the latest PRO version features</strong>, which are designed to help you keep your store looking, feeling and performing as any modern store should.
                    </li>
                    <li>
                    <strong>1 full year of free Support & Updates Services</strong>, allowing you to keep your extension up to date with the latest Magento versions, ensuring maximum performance and security.
                    </li>
                </ul>"
            . '<p>Head over to the Product Page and <a href="https://www.weltpixel.com/owl-carousel-and-slider.html?quickview=pro" target="_blank">get your PRO version now!</a></p>'
            .  "</div>";

        return $html;
    }
}
