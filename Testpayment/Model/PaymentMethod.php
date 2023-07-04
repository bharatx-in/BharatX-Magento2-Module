<?php

namespace Test\Testpayment\Model;

use Magento\Payment\Model\InfoInterface;

/**
 * Pay In Store payment method model
 */
class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod {

    const METHOD_CODE = 'testpayment';
    const ACTION_PROCESSING = 'processing';

    protected $_code = self::METHOD_CODE;
    // protected $_code = 'testpayment';

    /**
     * @var \Test\Testpayment\Model\Config
     */
    protected $config;

    /**
     * @var bool
     */
    protected $_canAuthorize  = true;

    /**
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * 
     * @param array $data
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\Context $context
     * @param \Test\Cfcheckout\Model\Config $config
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */

    public function __construct(
        \Magento\Framework\Registry $registry,
        \Test\Testpayment\Model\Config $config,
        \Magento\Framework\Model\Context $context,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->config   = $config;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    public function capture(InfoInterface $payment, $amount)
    {
        return $this;
    }
}
