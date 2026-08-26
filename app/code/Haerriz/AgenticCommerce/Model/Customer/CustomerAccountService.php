<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Customer;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;

class CustomerAccountService
{
    public function __construct(
        private CustomerRepositoryInterface $customers,
        private AddressRepositoryInterface $addresses,
        private AddressInterfaceFactory $addressFactory,
        private RegionInterfaceFactory $regionFactory
    ) {}

    public function profile(array $identity): array
    {
        $id = $this->customerId($identity);
        $customer = $this->customers->getById($id);
        return [
            'id' => $id,
            'firstname' => (string)$customer->getFirstname(),
            'lastname' => (string)$customer->getLastname(),
            'email' => (string)$customer->getEmail(),
            'created_at' => (string)$customer->getCreatedAt(),
            'default_billing' => (string)$customer->getDefaultBilling(),
            'default_shipping' => (string)$customer->getDefaultShipping(),
        ];
    }

    /** Update only low-risk profile fields. Password and authentication secrets are intentionally excluded. */
    public function updateProfile(array $identity, array $data): array
    {
        $id = $this->customerId($identity);
        $customer = $this->customers->getById($id);
        if (array_key_exists('firstname', $data)) {
            $value = mb_substr(trim((string)$data['firstname']), 0, 255);
            if ($value === '') throw new LocalizedException(__('First name cannot be empty.'));
            $customer->setFirstname($value);
        }
        if (array_key_exists('lastname', $data)) {
            $value = mb_substr(trim((string)$data['lastname']), 0, 255);
            if ($value === '') throw new LocalizedException(__('Last name cannot be empty.'));
            $customer->setLastname($value);
        }
        $this->customers->save($customer);
        return $this->profile($identity) + ['assistant_message' => (string)__('Your customer profile was updated.')];
    }

    public function addressList(array $identity): array
    {
        $id = $this->customerId($identity);
        $customer = $this->customers->getById($id);
        $billing = (int)$customer->getDefaultBilling();
        $shipping = (int)$customer->getDefaultShipping();
        $items = [];
        foreach ($customer->getAddresses() ?: [] as $address) {
            $items[] = $this->presentAddress($address, $billing, $shipping);
        }
        return $items;
    }

    public function addressByPosition(array $identity, int $position): array
    {
        $items = $this->addressList($identity);
        if (!$items) throw new LocalizedException(__('You do not have any saved addresses.'));
        if ($position <= 0) $position = count($items);
        $position = max(1, $position);
        if (!isset($items[$position - 1])) throw new LocalizedException(__('That saved address does not exist.'));
        return $items[$position - 1];
    }

    /**
     * Create/update an owned customer address. This method is intended for structured/direct forms;
     * raw address PII never needs to be sent to an external planner.
     */
    public function saveAddress(array $identity, array $data, ?int $addressId = null): array
    {
        $customerId = $this->customerId($identity);
        if ($addressId && $addressId > 0) {
            $address = $this->addresses->getById($addressId);
            if ((int)$address->getCustomerId() !== $customerId) {
                throw new AuthorizationException(__('That address does not belong to your account.'));
            }
        } else {
            $address = $this->addressFactory->create();
            $address->setCustomerId($customerId);
        }

        $safe = $this->sanitizeAddress($data);
        $region = $this->regionFactory->create();
        $region->setRegion((string)$safe['region']);
        $region->setRegionCode((string)$safe['region_code']);
        $region->setRegionId((int)$safe['region_id']);

        $address->setFirstname((string)$safe['firstname'])
            ->setLastname((string)$safe['lastname'])
            ->setCompany((string)$safe['company'])
            ->setStreet((array)$safe['street'])
            ->setCity((string)$safe['city'])
            ->setRegion($region)
            ->setRegionId((int)$safe['region_id'] ?: null)
            ->setPostcode((string)$safe['postcode'])
            ->setCountryId((string)$safe['country_id'])
            ->setTelephone((string)$safe['telephone'])
            ->setIsDefaultShipping((bool)$safe['default_shipping'])
            ->setIsDefaultBilling((bool)$safe['default_billing']);

        $saved = $this->addresses->save($address);
        return [
            'saved' => true,
            'address' => $this->ownedAddress($identity, (int)$saved->getId()),
            'addresses' => $this->addressList($identity),
            'assistant_message' => (string)__('Your address was saved.'),
        ];
    }

    public function deleteAddress(array $identity, int $addressId): array
    {
        $customerId = $this->customerId($identity);
        $address = $this->addresses->getById($addressId);
        if ((int)$address->getCustomerId() !== $customerId) {
            throw new AuthorizationException(__('That address does not belong to your account.'));
        }
        $this->addresses->deleteById($addressId);
        return [
            'deleted' => true,
            'addresses' => $this->addressList($identity),
            'assistant_message' => (string)__('The saved address was removed.'),
        ];
    }

    public function ownedAddress(array $identity, int $addressId): array
    {
        $customerId = $this->customerId($identity);
        $customer = $this->customers->getById($customerId);
        $address = $this->addresses->getById($addressId);
        if ((int)$address->getCustomerId() !== $customerId) {
            throw new AuthorizationException(__('That address does not belong to your account.'));
        }
        return $this->presentAddress($address, (int)$customer->getDefaultBilling(), (int)$customer->getDefaultShipping());
    }

    private function customerId(array $identity): int
    {
        $id = (int)($identity['customer_id'] ?? 0);
        if ($id <= 0) throw new AuthorizationException(__('Please sign in to use customer account tools.'));
        return $id;
    }

    private function sanitizeAddress(array $data): array
    {
        $street = $data['street'] ?? [];
        if (is_string($street)) $street = [$street];
        $safe = [
            'firstname' => mb_substr(trim((string)($data['firstname'] ?? '')), 0, 255),
            'lastname' => mb_substr(trim((string)($data['lastname'] ?? '')), 0, 255),
            'company' => mb_substr(trim((string)($data['company'] ?? '')), 0, 255),
            'street' => array_slice(array_values(array_filter(array_map(static fn($v): string => mb_substr(trim((string)$v), 0, 255), (array)$street))), 0, 4),
            'city' => mb_substr(trim((string)($data['city'] ?? '')), 0, 255),
            'region' => mb_substr(trim((string)($data['region'] ?? '')), 0, 255),
            'region_code' => mb_substr(trim((string)($data['region_code'] ?? '')), 0, 64),
            'region_id' => max(0, (int)($data['region_id'] ?? 0)),
            'postcode' => mb_substr(trim((string)($data['postcode'] ?? '')), 0, 64),
            'country_id' => strtoupper(mb_substr(trim((string)($data['country_id'] ?? '')), 0, 2)),
            'telephone' => mb_substr(trim((string)($data['telephone'] ?? '')), 0, 64),
            'default_shipping' => !empty($data['default_shipping']),
            'default_billing' => !empty($data['default_billing']),
        ];
        foreach (['firstname','lastname','city','postcode','country_id','telephone'] as $key) {
            if ($safe[$key] === '') throw new LocalizedException(__('Address field %1 is required.', $key));
        }
        if (!$safe['street']) throw new LocalizedException(__('Street is required.'));
        if (!preg_match('/^[A-Z]{2}$/', $safe['country_id'])) throw new LocalizedException(__('Country must be a two-letter country code.'));
        return $safe;
    }

    private function presentAddress($address, int $billingId, int $shippingId): array
    {
        $region = $address->getRegion();
        return [
            'id' => (int)$address->getId(),
            'firstname' => (string)$address->getFirstname(),
            'lastname' => (string)$address->getLastname(),
            'company' => (string)$address->getCompany(),
            'street' => (array)$address->getStreet(),
            'city' => (string)$address->getCity(),
            'region' => is_object($region) && method_exists($region, 'getRegion') ? (string)$region->getRegion() : (string)$region,
            'region_code' => is_object($region) && method_exists($region, 'getRegionCode') ? (string)$region->getRegionCode() : '',
            'region_id' => (int)($address->getRegionId() ?? 0),
            'postcode' => (string)$address->getPostcode(),
            'country_id' => (string)$address->getCountryId(),
            'telephone' => (string)$address->getTelephone(),
            'default_billing' => (int)$address->getId() === $billingId,
            'default_shipping' => (int)$address->getId() === $shippingId,
        ];
    }
}
