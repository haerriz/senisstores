<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\AbandonedCart\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class EmailSender
{
    private const TEMPLATE_ID = 'haerriz_abandonedcart_reminder';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var LogoResolver
     */
    private $logoResolver;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PriceHelper
     */
    private $priceHelper;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ImageHelper
     */
    private $imageHelper;

    /**
     * @param Config $config
     * @param LogoResolver $logoResolver
     * @param TransportBuilder $transportBuilder
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param StoreManagerInterface $storeManager
     * @param PriceHelper $priceHelper
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     * @param ProductRepositoryInterface $productRepository
     * @param ImageHelper $imageHelper
     */
    public function __construct(
        Config $config,
        LogoResolver $logoResolver,
        TransportBuilder $transportBuilder,
        CartRepositoryInterface $cartRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        StoreManagerInterface $storeManager,
        PriceHelper $priceHelper,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        ImageHelper $imageHelper
    ) {
        $this->config = $config;
        $this->logoResolver = $logoResolver;
        $this->transportBuilder = $transportBuilder;
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->storeManager = $storeManager;
        $this->priceHelper = $priceHelper;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->imageHelper = $imageHelper;
    }

    /**
     * @param array<string, mixed> $candidate
     * @param bool $logSent
     * @return bool
     */
    public function send(array $candidate, $logSent = true)
    {
        $storeId = (int) $candidate['store_id'];
        $quoteId = (int) $candidate['quote_id'];

        try {
            /** @var Quote $quote */
            $quote = $this->cartRepository->get($quoteId);
        } catch (\Exception $e) {
            $this->logger->warning('Abandoned cart quote not found: ' . $quoteId);
            return false;
        }

        if (!$quote->getIsActive() || (int) $quote->getItemsCount() === 0) {
            return false;
        }

        $store = $this->storeManager->getStore($storeId);
        $logo = $this->logoResolver->resolve($storeId);
        $restoreUrl = $this->buildRestoreUrl($quote, $storeId);
        $productsHtml = $this->buildProductsHtml($quote, $storeId);

        $vars = [
            'customer_name' => trim($candidate['firstname'] . ' ' . $candidate['lastname']),
            'cart_items_count' => (int) $candidate['items_count'],
            'cart_total' => $this->priceHelper->currency($quote->getGrandTotal(), true, false),
            'restore_url' => $restoreUrl,
            'store_name' => $store->getFrontendName(),
            'store_phone' => '+91 9442650753',
            'logo_url' => $logo['url'],
            'logo_alt' => $logo['alt'],
            'logo_width' => $logo['width'],
            'products_html' => $productsHtml,
        ];

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::TEMPLATE_ID)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($vars)
                ->setFromByScope('general', $storeId)
                ->addTo($candidate['email'], $vars['customer_name'])
                ->getTransport();
            $transport->sendMessage();

            if ($logSent) {
                $this->logSent($quoteId, $storeId, (string) $candidate['email']);
            }
            return true;
        } catch (MailException $e) {
            $this->logger->error('Abandoned cart mail failed for quote ' . $quoteId . ': ' . $e->getMessage());
            return false;
        } catch (LocalizedException $e) {
            $this->logger->error('Abandoned cart template error for quote ' . $quoteId . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param Quote $quote
     * @param int $storeId
     * @return string
     */
    private function buildRestoreUrl(Quote $quote, $storeId)
    {
        $store = $this->storeManager->getStore($storeId);
        $mask = $this->quoteIdMaskFactory->create();
        $mask->load($quote->getId(), 'quote_id');
        if (!$mask->getMaskedId()) {
            $mask->setQuoteId((int) $quote->getId())->save();
        }

        return $store->getUrl(
            'abandonedcart/cart/restore',
            ['_query' => ['id' => $mask->getMaskedId()], '_nosid' => true]
        );
    }

    /**
     * @param Quote $quote
     * @param int $storeId
     * @return string
     */
    private function buildProductsHtml(Quote $quote, $storeId)
    {
        $max = $this->config->getMaxProductsInEmail($storeId);
        $html = '';
        $count = 0;

        foreach ($quote->getAllVisibleItems() as $item) {
            if ($count >= $max) {
                break;
            }

            $product = $item->getProduct();
            if (!$product || !$product->getId()) {
                continue;
            }

            $name = htmlspecialchars((string) $product->getName(), ENT_QUOTES, 'UTF-8');
            $qty = (int) $item->getQty();
            $price = htmlspecialchars((string) $this->priceHelper->currency($item->getRowTotal(), true, false), ENT_QUOTES, 'UTF-8');
            $imageUrl = $this->resolveProductImageUrl((int) $product->getId(), $storeId);

            $imageHtml = $imageUrl !== ''
                ? '<img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $name . '" width="80" style="display:block;border:0;max-width:80px;height:auto;" />'
                : '';

            $html .= '<tr>'
                . '<td style="padding:10px;border-bottom:1px solid #eee;">'
                . $imageHtml
                . '</td>'
                . '<td style="padding:10px;border-bottom:1px solid #eee;vertical-align:top;">'
                . '<strong>' . $name . '</strong><br/>'
                . 'Qty: ' . $qty . '<br/>'
                . '<span style="color:#c9a227;font-weight:bold;">' . $price . '</span>'
                . '</td>'
                . '</tr>';
            $count++;
        }

        return $html;
    }

    /**
     * @param int $productId
     * @param int $storeId
     * @return string
     */
    private function resolveProductImageUrl($productId, $storeId)
    {
        try {
            $catalogProduct = $this->productRepository->getById($productId, false, $storeId);
        } catch (\Exception $e) {
            return '';
        }

        return (string) $this->imageHelper
            ->init($catalogProduct, 'product_thumbnail_image')
            ->getUrl();
    }

    /**
     * @param int $quoteId
     * @param int $storeId
     * @param string $email
     * @return void
     */
    private function logSent($quoteId, $storeId, $email)
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('haerriz_abandoned_cart_email');

        $connection->insertOnDuplicate(
            $table,
            [
                'quote_id' => $quoteId,
                'store_id' => $storeId,
                'recipient_email' => $email,
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s'),
            ],
            ['recipient_email', 'status', 'sent_at']
        );
    }
}
