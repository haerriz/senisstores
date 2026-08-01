<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Haerriz\GoogleShoppingFeed\Api\CredentialProviderInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\ProfileValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feed_profiles';

    private FeedProfileRepositoryInterface $repository;
    private ProfileValidator $validator;
    private CredentialProviderInterface $credentialProvider;

    public function __construct(
        Context $context,
        FeedProfileRepositoryInterface $repository,
        ProfileValidator $validator,
        CredentialProviderInterface $credentialProvider
    ) {
        parent::__construct($context);
        $this->repository = $repository;
        $this->validator = $validator;
        $this->credentialProvider = $credentialProvider;
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $redirect->setPath('*/*/');
        }

        try {
            $id = isset($data['profile_id']) ? (int)$data['profile_id'] : 0;
            $profile = $id > 0
                ? $this->repository->getById($id)
                : $this->repository->create();

            $directFields = [
                'name',
                'feed_type',
                'store_id',
                'currency',
                'filename',
                'status',
                'cron_expression',
                'cron_expr',
                'delivery_type',
                'delivery_host',
                'delivery_port',
                'delivery_username',
                'delivery_path',
                'remote_path',
                'attributes_mapping_serialized',
                'conditions_serialized',
                'utm_enabled',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
            ];

            foreach ($directFields as $field) {
                if (array_key_exists($field, $data)) {
                    $profile->setData($field, $data[$field]);
                }
            }

            // Legacy form aliases -> canonical delivery_* columns
            if (array_key_exists('ftp_host', $data)) {
                $profile->setDeliveryHost($data['ftp_host']);
            }
            if (array_key_exists('ftp_port', $data)) {
                $profile->setDeliveryPort($data['ftp_port']);
            }
            if (array_key_exists('ftp_user', $data)) {
                $profile->setDeliveryUsername($data['ftp_user']);
            }
            if (array_key_exists('remote_path', $data) && !array_key_exists('delivery_path', $data)) {
                $profile->setDeliveryPath($data['remote_path']);
            }
            if (array_key_exists('cron_expr', $data) && !array_key_exists('cron_expression', $data)) {
                $profile->setCronExpression($data['cron_expr']);
            }

            $password = $data['delivery_password'] ?? $data['ftp_password'] ?? null;
            if (!empty($password) && $password !== '******') {
                $profile->setDeliveryPassword($this->credentialProvider->encrypt($password));
            }

            $errors = $this->validator->validate($profile);
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->messageManager->addErrorMessage($error);
                }
                $backUrl = $id > 0
                    ? $this->getUrl('*/*/edit', ['id' => $id])
                    : $this->getUrl('*/*/new');
                return $redirect->setUrl($backUrl);
            }

            $this->repository->save($profile);
            $this->messageManager->addSuccessMessage(
                __('Feed profile "%1" saved successfully.', $this->escapeHtml((string)$profile->getName()))
            );

            return $this->getRequest()->getParam('back') === 'edit'
                ? $redirect->setPath('*/*/edit', ['id' => $profile->getId()])
                : $redirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Save failed: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/');
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
