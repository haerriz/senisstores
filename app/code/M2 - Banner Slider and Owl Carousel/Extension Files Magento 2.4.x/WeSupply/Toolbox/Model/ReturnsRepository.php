<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Model;

use Magento\Framework\Exception\AlreadyExistsException;
use WeSupply\Toolbox\Model\ResourceModel\Returns as ReturnsResource;

/**
 * Class ReturnsRepository
 *
 * @package WeSupply\Toolbox\Model
 */
class ReturnsRepository
{
    /**
     * @var ReturnsResource
     */
    protected $resource;
    /**
     * @var ReturnsFactory
     */
    protected $returnsFactory;

    /**
     * @var Returns
     */
    private $return;

    /**
     * ReturnsRepository constructor.
     *
     * @param ReturnsResource $resource
     * @param ReturnsFactory  $returnsFactory
     */
    public function __construct(
        ReturnsResource $resource,
        ReturnsFactory $returnsFactory
    )
    {
        $this->resource = $resource;
        $this->returnsFactory = $returnsFactory;
    }

    /**
     * @param $splitId
     *
     * @return Returns
     */
    public function getByReturnSplitId($splitId)
    {
        $this->return = $this->returnsFactory->create();
        $this->resource->load($this->return, $splitId, 'return_split_id');

        return $this->return;
    }

    /**
     * @param $referenceId
     * @param $requestLogId
     * @param $splitId
     *
     * @throws AlreadyExistsException
     */
    public function registerNewReturn($referenceId, $requestLogId, $splitId)
    {
        $this->return = $this->returnsFactory->create();

        $this->return->setReturnReference($referenceId);
        $this->return->setRequestLogId($requestLogId);
        $this->return->setReturnSplitId($splitId);
        $this->return->setStatus('init');

        $this->resource->save($this->return);
    }

    /**
     * @param $splitId
     * @param $returnData
     *
     * @throws \Exception
     */
    public function updateReturn($splitId, $returnData)
    {
        $this->getByReturnSplitId($splitId);

        if ($this->return->getId()) {
            $this->return->setStatus($returnData['status']);
            $this->return->setRefunded($returnData['refunded']);

            $this->resource->save($this->return);
        }
    }

    /**
     * @param $splitId
     * @param $creditmemoId
     *
     * @throws AlreadyExistsException
     */
    public function updateCreditmemoId($splitId, $creditmemoId)
    {
        $this->getByReturnSplitId($splitId);

        if ($this->return->getId()) {
            $this->return->setCreditMemoId($creditmemoId);

            $this->resource->save($this->return);
        }
    }
}
