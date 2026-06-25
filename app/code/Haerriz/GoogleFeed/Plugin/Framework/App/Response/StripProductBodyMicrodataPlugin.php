<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\GoogleFeed\Plugin\Framework\App\Response;

use Magento\Framework\App\Response\Http;

class StripProductBodyMicrodataPlugin
{
    /**
     * Remove legacy Product microdata from <body> so Google uses JSON-LD only.
     *
     * @param Http $subject
     * @return void
     */
    public function beforeSendResponse(Http $subject)
    {
        $body = $subject->getBody();

        if (!is_string($body) || strpos($body, 'catalog-product-view') === false) {
            return;
        }

        $body = (string) preg_replace(
            '/\sitemtype="http:\/\/schema\.org\/Product"\sitemscope="itemscope"/i',
            '',
            $body,
            1
        );

        $subject->setBody($body);
    }
}
