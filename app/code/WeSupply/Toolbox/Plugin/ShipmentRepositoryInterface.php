<?php
namespace WeSupply\Toolbox\Plugin;

use Magento\Sales\Api\Data\ShipmentTrackInterface;

class ShipmentRepositoryInterface extends AbstractOrder
{
    /**
     * @param \Magento\Sales\Model\Order\ShipmentRepository $subject
     * @param $result
     * @return mixed
     */
    public function afterSave(
        \Magento\Sales\Model\Order\ShipmentRepository $subject, $result, \Magento\Sales\Api\Data\ShipmentInterface $entity
    )
    {
        if($this->helper->getWeSupplyEnabled()) {
            $orderId = $entity->getOrderId();
            $this->eventManager->dispatch(
                'wesupply_order_update',
                ['orderId' => $orderId]
            );
        }
        return $result;
    }
}
