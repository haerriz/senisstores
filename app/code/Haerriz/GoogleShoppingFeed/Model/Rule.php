<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Magento\Rule\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\CatalogRule\Model\Rule\Condition\CombineFactory as ConditionCombineFactory;
use Magento\Rule\Model\Action\CollectionFactory as ActionCollectionFactory;

class Rule extends AbstractModel
{
    /**
     * @var ConditionCombineFactory
     */
    protected $condCombineFactory;

    /**
     * @var ActionCollectionFactory
     */
    protected $condActionFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param TimezoneInterface $localeDate
     * @param ConditionCombineFactory $condCombineFactory
     * @param ActionCollectionFactory $condActionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        TimezoneInterface $localeDate,
        ConditionCombineFactory $condCombineFactory,
        ActionCollectionFactory $condActionFactory,
        array $data = []
    ) {
        $this->condCombineFactory = $condCombineFactory;
        $this->condActionFactory = $condActionFactory;
        parent::__construct($context, $registry, $formFactory, $localeDate, null, null, $data);
    }

    /**
     * Get conditions instance
     *
     * @return \Magento\CatalogRule\Model\Rule\Condition\Combine
     */
    public function getConditionsInstance()
    {
        return $this->condCombineFactory->create();
    }

    /**
     * Get actions instance (unused but required by interface)
     *
     * @return \Magento\Rule\Model\Action\Collection
     */
    public function getActionsInstance()
    {
        return $this->condActionFactory->create();
    }
}
