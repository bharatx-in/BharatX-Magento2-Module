<?php

namespace Test\Testpayment\Controller\Standard;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class CreateTransaction extends Action
{
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory
    ) {
        $this->jsonFactory = $jsonFactory;
        parent::__construct($context);
    }

    /**
     * Default controller action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();
        $result->setHttpResponseCode(201);
        $result->setData(['message' => 'This is create transaction']);
        return $result;
    }
}
