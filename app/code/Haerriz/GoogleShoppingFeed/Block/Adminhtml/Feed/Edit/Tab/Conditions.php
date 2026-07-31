<?php
namespace Haerriz\GoogleShoppingFeed\Block\Adminhtml\Feed\Edit\Tab;

use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;

class Conditions extends Generic implements TabInterface
{
    /**
     * @var \Magento\Backend\Block\Widget\Form\Renderer\Fieldset
     */
    protected $rendererFieldset;

    /**
     * @var \Magento\Rule\Block\Conditions
     */
    protected $conditions;

    /**
     * @var \Haerriz\GoogleShoppingFeed\Model\RuleFactory
     */
    protected $ruleFactory;

    /**
     * @var \Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface
     */
    protected $repository;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Backend\Block\Widget\Form\Renderer\Fieldset $rendererFieldset,
        \Magento\Rule\Block\Conditions $conditions,
        \Haerriz\GoogleShoppingFeed\Model\RuleFactory $ruleFactory,
        \Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface $repository,
        array $data = []
    ) {
        $this->rendererFieldset = $rendererFieldset;
        $this->conditions = $conditions;
        $this->ruleFactory = $ruleFactory;
        $this->repository = $repository;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    public function getTabLabel()
    {
        return __('Conditions');
    }

    public function getTabTitle()
    {
        return __('Conditions');
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return false;
    }

    protected function _prepareForm()
    {
        $id = $this->getRequest()->getParam('id');
        $rule = $this->ruleFactory->create();

        if ($id) {
            try {
                $profile = $this->repository->getById($id);
                $serialized = $profile->getConditionsSerialized();
                if ($serialized) {
                    $rule->setConditionsSerialized($serialized);
                }
            } catch (\Exception $e) {
                // profile not found
            }
        }

        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('rule_');

        $renderer = $this->rendererFieldset->setTemplate(
            'Magento_CatalogRule::promo/fieldset.phtml'
        )->setNewChildUrl(
            $this->getUrl('haerriz_googleshoppingfeed/feed/conditions', ['form' => 'rule_conditions_fieldset'])
        );

        $fieldset = $form->addFieldset(
            'conditions_fieldset',
            ['legend' => __('Apply the feed only to products matching the following conditions (leave blank for all products)')]
        )->setRenderer($renderer);

        $fieldset->addField(
            'conditions',
            'text',
            [
                'name' => 'conditions',
                'label' => __('Conditions'),
                'title' => __('Conditions'),
                'required' => true,
                'data-form-part' => 'haerriz_googleshoppingfeed_feed_form'
            ]
        )->setRule($rule)->setRenderer($this->conditions);

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
