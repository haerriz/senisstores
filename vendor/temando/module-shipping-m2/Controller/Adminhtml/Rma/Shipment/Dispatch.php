<?php
/**
 * Refer to LICENSE.txt distributed with the Temando Shipping module for notice of license
 */

namespace Temando\Shipping\Controller\Adminhtml\Rma\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Temando\Shipping\Model\ResourceModel\Repository\ShipmentRepositoryInterface;
use Temando\Shipping\Model\ResourceModel\Rma\RmaAccess;

/**
 * Temando Dispatch Returns Shipment Action
 *
 * @package  Temando\Shipping\Controller
 * @author   Rhodri Davies <rhodri.davies@temando.com>
 * @license  http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link     http://www.temando.com/
 */
class Dispatch extends Action
{
    const ADMIN_RESOURCE = 'Magento_Rma::magento_rma';

    /**
     * @var RmaAccess
     */
    private $rmaAccess;

    /**
     * @var ShipmentRepositoryInterface
     */
    private $shipmentRepository;

    /**
     * Dispatch constructor.
     * @param Context $context
     * @param RmaAccess $rmaAccess
     * @param ShipmentRepositoryInterface $shipmentRepository
     */
    public function __construct(
        Context $context,
        RmaAccess $rmaAccess,
        ShipmentRepositoryInterface $shipmentRepository
    ) {
        $this->rmaAccess = $rmaAccess;
        $this->shipmentRepository = $shipmentRepository;

        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute()
    {
        $rmaId = $this->getRequest()->getParam('rma_id');

        // load and register current RMA
        $rma = $this->rmaAccess->getById($rmaId);
        $this->rmaAccess->setCurrentRma($rma);

        // load and register current RMA shipment
        $extShipmentId = $this->getRequest()->getParam('ext_shipment_id');

        try {
            $extShipment = $this->shipmentRepository->getById($extShipmentId);
            $this->rmaAccess->setCurrentRmaShipment($extShipment);
        } catch (LocalizedException $exception) {
            $message = __("Shipment '%1' not found.", $extShipmentId);
            $this->messageManager->addExceptionMessage($exception, $message);

            /** @var \Magento\Framework\Controller\Result\Forward $resultForward */
            $resultForward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
            $resultForward->forward('noroute');

            return $resultForward;
        }

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->prepend(__('Dispatch Return Shipment'));

        return $resultPage;
    }
}
