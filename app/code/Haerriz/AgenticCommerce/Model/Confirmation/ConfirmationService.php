<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Confirmation;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;

class ConfirmationService
{
    private const TABLE='haerriz_agentic_confirmation';
    public function __construct(private ResourceConnection $resource, private Random $random) {}
    public function create(string $conversationPublicId,array $identity,string $action,array $payload,string $summary,int $ttl=600): array
    {
        $token=$this->random->getRandomString(48); $conn=$this->resource->getConnection();
        $conn->insert($this->resource->getTableName(self::TABLE),[
            'token_hash'=>hash('sha256',$token),'conversation_public_id'=>mb_substr($conversationPublicId,0,64),'store_id'=>(int)$identity['store_id'],'customer_id'=>(int)($identity['customer_id']??0)?:null,
            'client_hash'=>hash('sha256',(string)($identity['client_id']??'')),'action'=>mb_substr($action,0,64),'payload_json'=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            'summary'=>mb_substr($summary,0,1000),'expires_at'=>date('Y-m-d H:i:s',time()+max(60,min(1800,$ttl))),'created_at'=>date('Y-m-d H:i:s')
        ]);
        return ['token'=>$token,'action'=>$action,'title'=>(string)__('Confirmation required'),'summary'=>$summary,'expires_at'=>date(DATE_ATOM,time()+max(60,min(1800,$ttl)))];
    }
    public function latest(string $conversationPublicId,array $identity): ?array
    {
        $conn=$this->resource->getConnection(); $row=$conn->fetchRow($conn->select()->from($this->resource->getTableName(self::TABLE))->where('conversation_public_id = ?', $conversationPublicId)->where('used_at IS NULL')->where('expires_at > ?',date('Y-m-d H:i:s'))->order('confirmation_id DESC')->limit(1));
        if(!$row) return null; $this->assertIdentity($row,$identity); return $row;
    }
    public function consume(string $token, array $identity): array
    {
        $hash = hash('sha256', trim($token));
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $now = date('Y-m-d H:i:s');
        $row = $conn->fetchRow($conn->select()->from($table)->where('token_hash = ?', $hash)->limit(1));
        if (!$row) {
            throw new LocalizedException(__('That confirmation is invalid or expired.'));
        }
        $this->assertIdentity($row, $identity);
        if (!empty($row['used_at']) || strtotime((string)$row['expires_at']) <= time()) {
            throw new LocalizedException(__('That confirmation is invalid or expired.'));
        }

        // Atomic consume: only one concurrent request is allowed to transition the token from unused
        // to used. This is essential for order placement and other consequential operations.
        $updated = $conn->update($table, ['used_at' => $now], [
            'confirmation_id = ?' => (int)$row['confirmation_id'],
            'used_at IS NULL',
            'expires_at > ?' => $now,
        ]);
        if ((int)$updated !== 1) {
            throw new LocalizedException(__('That confirmation was already used or expired.'));
        }
        $payload = json_decode((string)$row['payload_json'], true);
        return ['action' => (string)$row['action'], 'payload' => is_array($payload) ? $payload : [], 'summary' => (string)$row['summary']];
    }

    public function consumeLatest(string $conversationPublicId, array $identity): array
    {
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $now = date('Y-m-d H:i:s');
        $row = $conn->fetchRow(
            $conn->select()->from($table)
                ->where('conversation_public_id = ?', $conversationPublicId)
                ->where('used_at IS NULL')
                ->where('expires_at > ?', $now)
                ->order('confirmation_id DESC')
                ->limit(1)
        );
        if (!$row) {
            throw new LocalizedException(__('There is no pending action to confirm.'));
        }
        $this->assertIdentity($row, $identity);
        $updated = $conn->update($table, ['used_at' => $now], [
            'confirmation_id = ?' => (int)$row['confirmation_id'],
            'used_at IS NULL',
            'expires_at > ?' => $now,
        ]);
        if ((int)$updated !== 1) {
            throw new LocalizedException(__('That confirmation was already used or expired.'));
        }
        $payload = json_decode((string)$row['payload_json'], true);
        return ['action' => (string)$row['action'], 'payload' => is_array($payload) ? $payload : [], 'summary' => (string)$row['summary']];
    }

    public function cancelLatest(string $conversationPublicId,array $identity): bool
    {
        $row=$this->latest($conversationPublicId,$identity); if(!$row) return false; $this->resource->getConnection()->update($this->resource->getTableName(self::TABLE),['used_at'=>date('Y-m-d H:i:s')],['confirmation_id = ?'=>(int)$row['confirmation_id']]); return true;
    }
    private function assertIdentity(array $row,array $identity): void
    {
        if((int)$row['store_id']!==(int)$identity['store_id']) throw new AuthorizationException(__('Confirmation belongs to a different store.'));
        $customer=(int)($identity['customer_id']??0); if((int)($row['customer_id']??0)>0 && (int)$row['customer_id']!==$customer) throw new AuthorizationException(__('Confirmation belongs to another customer.'));
        if((int)($row['customer_id']??0)===0 && !hash_equals((string)$row['client_hash'],hash('sha256',(string)($identity['client_id']??'')))) throw new AuthorizationException(__('Confirmation belongs to another session.'));
    }
}
