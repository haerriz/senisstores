<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\AbandonedCart\Controller\Cart;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;

class Restore extends Action
{
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @param Context $context
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $cartRepository
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Context $context,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $maskedId = (string) $this->getRequest()->getParam('id');
        $redirect = $this->resultRedirectFactory->create();

        if ($maskedId === '') {
            return $redirect->setPath('checkout/cart');
        }

        $mask = $this->quoteIdMaskFactory->create()->load($maskedId, 'masked_id');
        $quoteId = (int) $mask->getQuoteId();
        if ($quoteId <= 0) {
            $this->messageManager->addErrorMessage(__('This cart link is no longer valid.'));
            return $redirect->setPath('checkout/cart');
        }

        try {
            $quote = $this->cartRepository->get($quoteId);
            if (!$quote->getIsActive() || (int) $quote->getItemsCount() === 0) {
                $this->messageManager->addNoticeMessage(__('Your cart is empty or already ordered.'));
                return $redirect->setPath('checkout/cart');
            }

            $this->checkoutSession->replaceQuote($quote);
            $this->messageManager->addSuccessMessage(__('Welcome back! Your cart has been restored.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('We could not restore your cart. Please shop again.'));
        }

        return $redirect->setPath('checkout/cart');
    }
}
