<?php

namespace Test\Testpayment\Model;

use Magento\Payment\Model\InfoInterface;
use Magento\Framework\Exception\LocalizedException;


/**
 * Pay In Store payment method model
 */
class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{

    const METHOD_CODE = 'bharatx';
    const ACTION_PROCESSING = 'processing';

    protected $_code = self::METHOD_CODE;

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

    // /**
    //  * @var bool
    //  */
    // protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canUseInternal          = true;        //Disable module for Magento Admin Order

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

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
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
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

        $this->orderRepository = $orderRepository;
    }

    public function capture(InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * Process refund for the payment.
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws LocalizedException
     */
    public function refund(InfoInterface $payment, $amount)
    {   
        // $this->logger->logger->info("refund called");
        if (!$this->canRefund()) {
            throw new LocalizedException(__('Refunds are not supported by this payment method.'));
        }

        // Implement your refund logic here
        $order = $payment->getOrder();
        $orderId = $order->getIncrementId();

        $url = 'https://web-v2.bharatx.tech/api/merchant/transaction';

        $username = $this->config->getConfigData('partner_id');
        $password = $this->config->getConfigData('api_key');

        $transactionId = $orderId . '_' . $username;

        $params = array(
            'refund' => array(
                'merchantRefundId' => $transactionId . '_' . 'refund',
                'amount' => $amount,
            ),
            'createConfiguration' => array(
                'webhookUrl' => 'https://webhook.site/23cabeb7-bb1d-424a-86d5-7fa9f9550d75',
            ),
        );

        $payload = json_encode($params);


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $username . ':' . $password,
        ));

        $curlResponse = curl_exec($curl);

        $err = curl_error($curl);

        curl_close($curl);


        // [PENDING, SUCCESS, FAILURE, CANCELLED, TBD]

        if ($err) {
            throw new LocalizedException(__('API request failed. ' . $err));
        } else {
            $responseData = json_decode($curlResponse);

            if ($responseData != null && isset($responseData->status)) {
                $redirectUrl = $responseData->transaction->url;

                $responseStatus = 'refund_' . $responseData->status;

                // if ($responseData->status === 'PENDING') {

                $order->setState('refund_pending')
                    ->setStatus('refund_pending')
                    ->save();
                // $payment->setAmountPaid($amount)
                // ->setLastTransId($refund->id)
                // ->setTransactionId($refund->id)
                // ->setIsTransactionClosed(true)
                // ->setShouldCloseParentTransaction(true);

                // }
                // $this->logger->info('$responseData', [
                //     'responseData' => $responseData
                // ]);
            } else {
                // $this->logger->error("Transaction or URL property not found in the response.");
                throw new LocalizedException(__('Transaction or URL property not found in the response'));
            }
        }



        return $this;
    }
}
