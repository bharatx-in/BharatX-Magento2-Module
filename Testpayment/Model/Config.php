<?php

namespace Test\Testpayment\Model;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\Config\Storage\WriterInterface;

class Config
{
    // const KEY_ALLOW_SPECIFIC    = 'allowspecific';
    // const KEY_SPECIFIC_COUNTRY  = 'specificcountry';
    const KEY_ACTIVE            = 'active';
    // const KEY_PUBLIC_KEY        = 'app_id';
    const KEY_TITLE             = 'title';
    // const PAYMENT_ENVIRONMENT   = 'environment';
    const KEY_NEW_ORDER_STATUS  = 'order_status';
    // const KEY_ENABLE_INVOICE    = 'enable_invoice';
    // const KEY_IN_CONTEXT        = 'in_context';

    const KEY_PARTNER_ID = 'partner_id';
    const KEY_API_KEY = 'api_key';

    /**
     * @var string
     */
    protected $methodCode = 'testpayment';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var int
     */
    protected $storeId = null;

    /**
     * @var \Test\Testpayment\Helper\Testpayment
     */
    protected $helper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        \Test\Testpayment\Helper\Testpayment $helper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->helper = $helper;
    }

    // public function getAppId()
    // {
    //     return $this->getConfigData(self::KEY_PUBLIC_KEY);
    // }

    public function getTitle()
    {
        return $this->getConfigData(self::KEY_TITLE);
    }

    public function getPartnerId()
    {
        return $this->getConfigData(self::KEY_PARTNER_ID);
    }

    public function getApiKey()
    {
        return $this->getConfigData(self::KEY_API_KEY);
    }

    public function getNewOrderStatus()
    {
        return $this->getConfigData(self::KEY_NEW_ORDER_STATUS);
    }

    public function getReturnUrl(string $order_id)
    {
        $baseUrl = $this->helper->getUrl('testpayment/standard/response', array('_secure' => true));
        $returnUrl = $baseUrl . "?cf_id={$order_id}";
        return $returnUrl;
    }

    public function getNotifyUrl()
    {
        return $this->helper->getUrl('testpayment/standard/notify', array('_secure' => true));
    }

    public function getBharatxApi() {
        return 'https://web-v2.bharatx.tech/api';
    }

    public function getConfigData($field, $storeId = null)
    {
        if ($storeId == null) {
            $storeId = $this->storeId;
        }

        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function setConfigData($field, $value)
    {
        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;

        return $this->configWriter->save($path, $value);
    }

    /**
     * @return bool
     */
    public function canSendInvoice()
    {
        // return (bool) (int) $this->getConfigData(self::KEY_ENABLE_INVOICE, $this->storeId);
        return false;
    }

    public function isActive()
    {
        return (bool) (int) $this->getConfigData(self::KEY_ACTIVE, $this->storeId);
    }

    // public function canUseForCountry($country)
    // {
    //     /*
    //     for specific country, the flag will set up as 1
    //     */
    //     if ($this->getConfigData(self::KEY_ALLOW_SPECIFIC) == 1) {
    //         $availableCountries = explode(',', $this->getConfigData(self::KEY_SPECIFIC_COUNTRY));
    //         if (!in_array($country, $availableCountries)) {
    //             return false;
    //         }
    //     }

    //     return true;
    // }
}
