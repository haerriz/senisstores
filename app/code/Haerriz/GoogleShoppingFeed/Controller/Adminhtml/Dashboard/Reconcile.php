<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Dashboard;

use Haerriz\GoogleShoppingFeed\Model\Api\StatusReconciliation;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Reconcile extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feed_management';

    private StatusReconciliation $reconciliation;

    public function __construct(
        Context $context,
        StatusReconciliation $reconciliation
    ) {
        parent::__construct($context);
        $this->reconciliation = $reconciliation;
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('haerriz_googleshoppingfeed/dashboard/index');

        try {
            $storeId = (int)$this->getRequest()->getParam('store_id', 0);
            $result = $this->reconciliation->reconcile($storeId);

            if (!empty($result['reconciled'])) {
                $this->messageManager->addSuccessMessage(__(
                    'Merchant status reconciliation completed. Updated %1 offers.',
                    (int)($result['count'] ?? 0)
                ));
            } else {
                $reason = $result['message'] ?? $result['reason'] ?? $result['error'] ?? 'unknown';
                $this->messageManager->addWarningMessage(__(
                    'Merchant status reconciliation did not complete: %1',
                    $reason
                ));
            }
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Reconcile failed: %1', $e->getMessage()));
        }

        return $redirect;
    }
}
