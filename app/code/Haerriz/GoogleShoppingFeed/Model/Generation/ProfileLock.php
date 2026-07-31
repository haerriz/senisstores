<?php
namespace Haerriz\GoogleShoppingFeed\Model\Generation;

class ProfileLock
{
    public function lock(int $profileId): bool { return true; }
    public function unlock(int $profileId): void {}
}
