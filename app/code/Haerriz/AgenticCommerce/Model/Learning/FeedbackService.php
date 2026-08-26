<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Learning;

use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\Conversation\ConversationRepository;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class FeedbackService
{
    public function __construct(
        private Config $config,
        private ConversationRepository $conversations,
        private ResourceConnection $resource,
        private AdaptiveLearningService $learning
    ) {}

    public function submit(string $conversationId,string $message,string $toolName,int $rating,array $identity,?string $comment=null): array
    {
        $storeId=(int)($identity['store_id']??0);
        if (!$this->config->isFeedbackEnabled($storeId)) throw new LocalizedException(__('Agent feedback is disabled.'));
        $rating=$rating>0?1:-1;
        $conversation=$this->conversations->get($conversationId,$identity,true);
        $cid=(int)($conversation['conversation_id']??0); if ($cid<=0) throw new LocalizedException(__('Conversation was not found.'));
        $c=$this->resource->getConnection(); $audit=$this->resource->getTableName('haerriz_agentic_tool_audit');
        $messageHash=hash('sha256',trim($message));
        $exists=(bool)$c->fetchOne($c->select()->from($audit,['audit_id'])->where('conversation_id=?',$cid)->where('tool_name=?',$toolName)->where('message_hash=?',$messageHash)->order('audit_id DESC')->limit(1));
        if (!$exists) throw new LocalizedException(__('That tool was not used in this conversation.'));
        $table=$this->resource->getTableName('haerriz_agentic_feedback');
        $duplicate=(bool)$c->fetchOne($c->select()->from($table,['feedback_id'])->where('conversation_id=?',$cid)->where('tool_name=?',$toolName)->where('message_hash=?',$messageHash)->limit(1));
        if ($duplicate) throw new LocalizedException(__('Feedback was already submitted for this tool turn.'));
        $c->insert($table,['conversation_id'=>$cid,'store_id'=>$storeId,'tool_name'=>$toolName,'rating'=>$rating,'comment'=>mb_substr(trim((string)$comment),0,1000),'message_hash'=>$messageHash]);
        $this->learning->feedback($message,$toolName,$rating,$identity);
        return ['accepted'=>true,'rating'=>$rating,'assistant_message'=>(string)__('Thanks — your feedback will help improve routing for this store.')];
    }
}
