<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Customer;

use Magento\Framework\Exception\AuthorizationException;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Newsletter\Model\SubscriptionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class NewsletterService
{
    public function __construct(private SubscriberFactory $subscriberFactory, private SubscriptionManagerInterface $subscriptions, private StoreManagerInterface $stores) {}
    public function status(array $identity): array
    {
        $customerId=$this->customerId($identity); $websiteId=(int)$this->stores->getStore((int)$identity['store_id'])->getWebsiteId();
        $subscriber=$this->subscriberFactory->create()->loadByCustomer($customerId,$websiteId);
        return ['subscribed'=>(bool)$subscriber->isSubscribed(),'status'=>(int)$subscriber->getSubscriberStatus()];
    }
    public function subscribe(array $identity): array { $this->subscriptions->subscribeCustomer($this->customerId($identity),(int)$identity['store_id']); return $this->status($identity)+['assistant_message'=>(string)__('You are subscribed to the newsletter.')]; }
    public function unsubscribe(array $identity): array { $this->subscriptions->unsubscribeCustomer($this->customerId($identity),(int)$identity['store_id']); return $this->status($identity)+['assistant_message'=>(string)__('You are unsubscribed from the newsletter.')]; }
    private function customerId(array $identity): int { $id=(int)($identity['customer_id']??0); if($id<=0) throw new AuthorizationException(__('Please sign in to manage newsletter preferences.')); return $id; }
}
