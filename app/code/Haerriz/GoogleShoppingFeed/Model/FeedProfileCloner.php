<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterfaceFactory;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;

class FeedProfileCloner
{
    private FeedProfileInterfaceFactory $profileFactory;
    private FeedProfileRepositoryInterface $profileRepository;

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

        unset(
            $data['profile_id'],
            $data['entity_id'],
            $data['created_at'],
            $data['updated_at'],
            $data['delivery_password'],
            $data['delivery_private_key'],
            $data['delivery_key_passphrase'],
            $data['ftp_password'],
            $data['sftp_password']
        );

        $data['name'] = (string)__('Copy of %1', $source->getName());
        $data['status'] = 0;
        $data['is_locked'] = 0;
        $data['locked_at'] = null;
        $data['retry_count'] = 0;
        $data['consecutive_failures'] = 0;
        $data['next_run_at'] = null;

        $copy = $this->profileFactory->create();
        $copy->setData($data);
        return $this->profileRepository->save($copy);
    }
}
