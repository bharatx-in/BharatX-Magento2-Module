<?php

namespace Test\Testpayment\Model;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\Config\Storage\WriterInterface;
use \Magento\Sales\Model\Order;

class Config
{
    const KEY_ACTIVE            = 'active';
    const KEY_TITLE             = 'title';
    const KEY_FRONTEND_TITLE    = 'frontendTitle';
    const KEY_TITLE_IMAGE       = 'title_image';
    const KEY_NEW_ORDER_STATUS  = 'order_status';
    const KEY_LIVE              = 'live';
    const KEY_ENABLE_INVOICE    = 'enable_invoice';

    const KEY_PARTNER_ID = 'partner_id';
    const KEY_API_KEY = 'api_key';

    /**
     * @var string
     */
    public $methodCode = 'bharatx';

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

    public function getTitle()
    {
        return $this->getConfigData(self::KEY_TITLE);
    }

    public function getFrontendTitle()
    {
        return $this->getConfigData(self::KEY_FRONTEND_TITLE);
    }

    public function getTitleImage()
    {
        return $this->getConfigData(self::KEY_TITLE_IMAGE);
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
        $baseUrl = $this->helper->getUrl('testpayment/standard/response');
        $returnUrl = $baseUrl . "?cf_id={$order_id}";
        return $returnUrl;
    }

    public function getNotifyUrl()
    {
        // return $this->helper->getUrl('testpayment/standard/notify', array('_secure' => true));
        $baseUrl = 'https://d79a940bf544.ngrok.app';
        return $baseUrl . '/testpayment/standard/notify';
    }

    public function getBharatxApi()
    {
        return 'https://web-v2.bharatx.tech/api';
        // return 'https://90dc5967a878.ngrok.app/api';
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
        return (bool) (int) $this->getConfigData(self::KEY_ENABLE_INVOICE, $this->storeId);
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return (bool) (int) $this->getConfigData(self::KEY_ACTIVE, $this->storeId);
    }

    /**
     * @return string
     */
    public function getTransactionMode()
    {
        $liveMode = (bool) (int) $this->getConfigData(SELF::KEY_LIVE, $this->storeId);
        if ($liveMode) {
            return "LIVE";
        } else {
            return "TEST";
        }
    }
}
