<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterfaceFactory;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;

class FeedProfileCloner
{
    private $profileFactory;
    private $profileRepository;

    public function __construct(
        FeedProfileInterfaceFactory $profileFactory,
        FeedProfileRepositoryInterface $profileRepository
    ) {
        $this->profileFactory = $profileFactory;
        $this->profileRepository = $profileRepository;
    }

    public function duplicate(FeedProfileInterface $source): FeedProfileInterface
    {
        $data = $source->getData();

        unset($data['profile_id'], $data['entity_id']);
        $data['name'] = __('Copy of %1', $source->getName());
        $data['status'] = 0;
        
        // Security Scrub: Clear all credentials, private keys, and passphrases
        unset($data['delivery_password']);
        unset($data['delivery_private_key']);
        unset($data['delivery_key_passphrase']);

        $copy = $this->profileFactory->create();
        $copy->setData($data);
        return $this->profileRepository->save($copy);
    }
}
