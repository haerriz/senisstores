<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Checkout;

use Haerriz\AgenticCommerce\Model\Cart\CartService;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\BillingAddressManagementInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\PaymentInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class CheckoutService
{
    public function __construct(
        private CartService $cartService,
        private CartRepositoryInterface $cartRepository,
        private ShippingMethodManagementInterface $shippingMethods,
        private PaymentMethodManagementInterface $paymentMethods,
        private BillingAddressManagementInterface $billingAddresses,
        private AddressInterfaceFactory $addressFactory,
        private PaymentInterfaceFactory $paymentFactory,
        private CartManagementInterface $cartManagement,
        private OrderRepositoryInterface $orderRepository,
        private PriceCurrencyInterface $priceCurrency,
        private PaymentMethodAdapterRegistry $paymentAdapters,
        private CheckoutValidationRegistry $checkoutValidators
    ) {}

    public function getState(array $identity, ?string $cartId=null): array
    {
        $quote=$this->cartService->resolveQuote($identity,$cartId,false);
        if (!$quote || !(int)$quote->getId()) {
            return [
                'ready' => false,
                'is_virtual' => false,
                'missing' => ['cart'],
                'guest_email' => '',
                'shipping_address' => null,
                'billing_address' => null,
                'shipping_method' => '',
                'payment_method' => '',
                'available_shipping_methods' => [],
                'available_payment_methods' => [],
                'requirements' => [],
                'cart' => $this->cartService->getSummary($identity, $cartId),
            ];
        }
        $shipping=$quote->getShippingAddress(); $billing=$quote->getBillingAddress();
        $shippingAddress=$quote->isVirtual()?null:$this->presentAddress($shipping);
        $billingAddress=$this->presentAddress($billing);
        $availableShipping=[];
        if (!$quote->isVirtual() && $shipping->getCountryId()) {
            try { foreach ($this->shippingMethods->getList((int)$quote->getId()) as $m) $availableShipping[]=$this->presentShippingMethod($m); } catch (\Throwable) {}
        }
        $availablePayment=[];
        try { foreach ($this->paymentMethods->getList((int)$quote->getId()) as $m) $availablePayment[]=['code'=>(string)$m->getCode(),'title'=>(string)$m->getTitle()]; } catch (\Throwable) {}
        $missing=[];
        if (!$quote->getItemsCount()) $missing[]='items';
        if (!$quote->isVirtual() && !$shipping->getCountryId()) $missing[]='shipping_address';
        if (!$quote->isVirtual() && !(string)$shipping->getShippingMethod()) $missing[]='shipping_method';
        if (!$billing->getCountryId()) $missing[]='billing_address';
        if (!(string)$quote->getPayment()->getMethod()) $missing[]='payment_method';
        if (!(int)($identity['customer_id']??0) && !(string)$quote->getCustomerEmail()) $missing[]='guest_email';
        $requirements=$this->checkoutValidators->validate($quote,$identity);
        foreach($requirements as $requirement) if(empty($requirement['satisfied'])) $missing[]='requirement:'.(string)$requirement['code'];
        return [
            'ready'=>$missing===[], 'is_virtual'=>(bool)$quote->isVirtual(), 'missing'=>$missing,
            'guest_email'=>(string)$quote->getCustomerEmail(),
            'shipping_address'=>$shippingAddress,'billing_address'=>$billingAddress,
            'shipping_method'=>(string)$shipping->getShippingMethod(), 'payment_method'=>(string)$quote->getPayment()->getMethod(),
            'available_shipping_methods'=>$availableShipping,'available_payment_methods'=>$availablePayment,'requirements'=>$requirements,
            'cart'=>$this->cartService->getSummary($identity,$cartId),
        ];
    }

    public function setGuestEmail(array $identity, string $email, ?string $cartId=null): array
    {
        if ((int)($identity['customer_id']??0)>0) throw new LocalizedException(__('A signed-in customer does not need a guest checkout email.'));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new LocalizedException(__('Enter a valid email address.'));
        $quote=$this->requiredQuote($identity,$cartId); $quote->setCustomerEmail(mb_substr($email,0,254)); $this->cartRepository->save($quote);
        return $this->getState($identity,$cartId);
    }

    public function setAddress(array $identity, string $kind, array $data, ?string $cartId=null): array
    {
        $quote=$this->requiredQuote($identity,$cartId); $address=$this->addressFactory->create();
        $safe=$this->sanitizeAddress($data); $address->setData($safe);
        if ($kind==='shipping') {
            if ($quote->isVirtual()) throw new LocalizedException(__('Shipping address is not required for a virtual cart.'));
            $quote->getShippingAddress()->addData($safe)->setCollectShippingRates(true);
            $this->cartRepository->save($quote);
        } elseif ($kind==='billing') {
            $this->billingAddresses->assign((int)$quote->getId(),$address,false);
        } else throw new LocalizedException(__('Unsupported address type.'));
        return $this->getState($identity,$cartId);
    }

    public function useCustomerAddress(array $identity, string $kind, array $addressData, ?string $cartId=null): array
    {
        if ((int)($identity['customer_id']??0)<=0) throw new AuthorizationException(__('Please sign in to use a saved address.'));
        return $this->setAddress($identity,$kind,$addressData,$cartId);
    }

    public function getShippingMethods(array $identity, ?string $cartId=null): array
    {
        return (array)($this->getState($identity,$cartId)['available_shipping_methods']??[]);
    }

    public function setShippingMethod(array $identity, string $carrier, string $method, ?string $cartId=null): array
    {
        $quote=$this->requiredQuote($identity,$cartId);
        if ($quote->isVirtual()) throw new LocalizedException(__('Shipping method is not required for a virtual cart.'));
        if (!$this->shippingMethods->set((int)$quote->getId(),mb_substr($carrier,0,64),mb_substr($method,0,64))) throw new LocalizedException(__('That shipping method could not be selected.'));
        return $this->getState($identity,$cartId);
    }

    public function getPaymentMethods(array $identity, ?string $cartId=null): array
    {
        return (array)($this->getState($identity,$cartId)['available_payment_methods']??[]);
    }

    public function setPaymentMethod(array $identity, string $code, ?string $cartId=null, array $safePayload=[]): array
    {
        $quote=$this->requiredQuote($identity,$cartId); $code=trim($code);
        if ($code==='') throw new LocalizedException(__('Choose a payment method.'));
        $allowed=[]; foreach ($this->paymentMethods->getList((int)$quote->getId()) as $m) $allowed[(string)$m->getCode()]=(string)$m->getTitle();
        if (!isset($allowed[$code])) throw new LocalizedException(__('That payment method is not available for this cart.'));
        $payment=$this->paymentFactory->create(); $payment->setMethod($code);
        $this->paymentAdapters->apply($code,$payment,$safePayload,['identity'=>$identity,'quote_id'=>(int)$quote->getId()]);
        if (!$this->paymentMethods->set((int)$quote->getId(),$payment)) throw new LocalizedException(__('The payment method could not be selected.'));
        return $this->getState($identity,$cartId);
    }

    /**
     * Create a server-only fingerprint for an order confirmation. If cart contents, totals, shipping,
     * payment, or customer email change after the shopper reviews the confirmation, execution is
     * rejected and a new confirmation must be prepared.
     */
    public function confirmationSnapshot(array $identity, ?string $cartId = null): array
    {
        $quote = $this->requiredQuote($identity, $cartId);
        $state = $this->getState($identity, $cartId);
        if (!$state['ready']) {
            throw new LocalizedException(__('Checkout is not ready. Missing: %1', implode(', ', (array)$state['missing'])));
        }
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [(string)$item->getSku(), (float)$item->getQty(), (float)$item->getRowTotal()];
        }
        $material = [
            'quote_id' => (int)$quote->getId(),
            'store_id' => (int)$quote->getStoreId(),
            'customer_id' => (int)($identity['customer_id'] ?? 0),
            'customer_email' => (string)$quote->getCustomerEmail(),
            'grand_total' => round((float)$quote->getGrandTotal(), 4),
            'items' => $items,
            'shipping_method' => (string)$quote->getShippingAddress()->getShippingMethod(),
            'payment_method' => (string)$quote->getPayment()->getMethod(),
        ];
        return [
            'quote_id' => (int)$quote->getId(),
            'fingerprint' => hash('sha256', json_encode($material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'grand_total' => (float)$quote->getGrandTotal(),
            'items_count' => (int)$quote->getItemsQty(),
        ];
    }

    public function placeConfirmedOrder(array $identity, array $confirmationPayload, ?string $cartId = null): array
    {
        $current = $this->confirmationSnapshot($identity, $cartId);
        $expectedQuoteId = (int)($confirmationPayload['quote_id'] ?? 0);
        $expectedFingerprint = (string)($confirmationPayload['fingerprint'] ?? '');
        if ($expectedQuoteId <= 0 || $expectedFingerprint === ''
            || $expectedQuoteId !== (int)$current['quote_id']
            || !hash_equals($expectedFingerprint, (string)$current['fingerprint'])) {
            throw new LocalizedException(__('Your cart or checkout details changed after confirmation was prepared. Review checkout and confirm again.'));
        }
        return $this->placeOrder($identity, $cartId);
    }

    public function placeOrder(array $identity, ?string $cartId=null): array
    {
        $quote=$this->requiredQuote($identity,$cartId); $state=$this->getState($identity,$cartId);
        if (!$state['ready']) throw new LocalizedException(__('Checkout is not ready. Missing: %1', implode(', ',(array)$state['missing'])));
        $orderId=(int)$this->cartManagement->placeOrder((int)$quote->getId());
        $order=$this->orderRepository->get($orderId);
        return ['placed'=>true,'order'=>[
            'number'=>(string)$order->getIncrementId(),'status'=>(string)$order->getStatus(),'status_label'=>(string)$order->getStatusLabel(),
            'grand_total'=>(float)$order->getGrandTotal(),'formatted_grand_total'=>$this->priceCurrency->format((float)$order->getGrandTotal(),false),
        ],'assistant_message'=>(string)__('Order %1 was placed successfully.',(string)$order->getIncrementId())];
    }

    private function requiredQuote(array $identity, ?string $cartId)
    {
        $quote=$this->cartService->resolveQuote($identity,$cartId,false);
        if (!$quote || !(int)$quote->getId() || !$quote->getItemsCount()) throw new LocalizedException(__('Your cart is empty.'));
        return $quote;
    }

    private function sanitizeAddress(array $data): array
    {
        $street=$data['street']??[]; if (is_string($street)) $street=[$street];
        $out=[
            'firstname'=>mb_substr(trim((string)($data['firstname']??'')),0,64),'lastname'=>mb_substr(trim((string)($data['lastname']??'')),0,64),
            'company'=>mb_substr(trim((string)($data['company']??'')),0,128),'street'=>array_slice(array_values(array_filter(array_map(fn($v)=>mb_substr(trim((string)$v),0,128),(array)$street))),0,4),
            'city'=>mb_substr(trim((string)($data['city']??'')),0,128),'region'=>mb_substr(trim((string)($data['region']??'')),0,128),
            'region_id'=>(int)($data['region_id']??0),'postcode'=>mb_substr(trim((string)($data['postcode']??'')),0,32),
            'country_id'=>strtoupper(mb_substr(trim((string)($data['country_id']??'')),0,2)),'telephone'=>mb_substr(trim((string)($data['telephone']??'')),0,32),
        ];
        foreach (['firstname','lastname','city','postcode','country_id','telephone'] as $key) if ($out[$key]==='' || $out[$key]===0) throw new LocalizedException(__('Address field %1 is required.',$key));
        if (!$out['street']) throw new LocalizedException(__('Street is required.'));
        return $out;
    }

    private function presentAddress($address): ?array
    {
        if (!$address || !$address->getCountryId()) return null;
        return ['firstname'=>(string)$address->getFirstname(),'lastname'=>(string)$address->getLastname(),'company'=>(string)$address->getCompany(),'street'=>(array)$address->getStreet(),'city'=>(string)$address->getCity(),'region'=>(string)$address->getRegion(),'region_id'=>(int)$address->getRegionId(),'postcode'=>(string)$address->getPostcode(),'country_id'=>(string)$address->getCountryId(),'telephone'=>(string)$address->getTelephone()];
    }

    private function presentShippingMethod($m): array
    {
        return ['carrier_code'=>(string)$m->getCarrierCode(),'method_code'=>(string)$m->getMethodCode(),'carrier_title'=>(string)$m->getCarrierTitle(),'method_title'=>(string)$m->getMethodTitle(),'amount'=>(float)$m->getAmount(),'formatted_amount'=>$this->priceCurrency->format((float)$m->getAmount(),false),'available'=>(bool)$m->getAvailable(),'error_message'=>(string)$m->getErrorMessage()];
    }
}
