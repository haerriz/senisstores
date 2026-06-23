<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\GoogleFeed\Plugin\View\Page\Config;

use Magento\Framework\View\Page\Config\Renderer;

class RendererPlugin
{
    /**
     * Use correct MIME type when store favicon is a PNG.
     *
     * @param Renderer $subject
     * @param string $result
     * @return string
     */
    public function afterRenderAssets(Renderer $subject, $result)
    {
        if (!is_string($result) || strpos($result, 'rel="icon"') === false) {
            return $result;
        }

        if (preg_match('/href="[^"]+\.png"/i', $result)) {
            $result = str_replace('type="image/x-icon"', 'type="image/png"', $result);
        }

        return $result;
    }
}
