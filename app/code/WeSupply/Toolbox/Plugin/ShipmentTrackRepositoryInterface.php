<?php
namespace WeSupply\Toolbox\Plugin;

use Magento\Sales\Api\Data\ShipmentTrackInterface;

class ShipmentTrackRepositoryInterface extends AbstractOrder
{
    /**
     * @param \Magento\Sales\Api\ShipmentTrackRepositoryInterface $subject
     * @param $result
     * @return mixed
     */
    public function afterSave(
        \Magento\Sales\Api\ShipmentTrackRepositoryInterface $subject, $result
    )
    {
        if($this->helper->getWeSupplyEnabled()) {
            $orderId = $result['order_id'];
            $this->eventManager->dispatch(
                'wesupply_order_update',
                ['orderId' => $orderId]
            );
        }
        return $result;
    }

    /**
     * @param \Magento\Sales\Api\ShipmentTrackRepositoryInterface $subject
     * @param $result
     * @param ShipmentTrackInterface $entity
     */
    public function afterDelete(
        \Magento\Sales\Api\ShipmentTrackRepositoryInterface $subject,
        $result,
        ShipmentTrackInterface $entity
    )
    {
        if($this->helper->getWeSupplyEnabled()) {
            $orderId = $entity['order_id'];
            $this->eventManager->dispatch(
                'wesupply_order_update',
                ['orderId' => $orderId]
            );
        }
        return $result;
    }


}
