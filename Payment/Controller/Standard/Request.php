<?php

namespace BharatX\Payment\Controller\Standard;

use Magento\Framework\Controller\ResultFactory;

/**
 * Class Request
 * Generate request data to create order and proceed payment
 * @package BharatX\Payment\Controller\Standard\Notify
 */
class Request extends \BharatX\Payment\Controller\CfAbstract
{
    /**
     * @var \Psr\Log\LoggerInterface 
     */
    protected $logger;

    /**
     * @var \BharatX\Payment\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Framework\App\Action\Context
     */

    protected $context;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \BharatX\Payment\Model\Config $config
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \BharatX\Payment\Model\Config $config,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
    ) {
        parent::__construct(
            $logger,
            $config,
            $context,
            $transaction,
            $customerSession,
            $checkoutSession,
            $invoiceService,
            $quoteRepository,
            $orderRepository,
            $orderSender,
            $invoiceSender
        );

        // $this->helper   = $helper;
    }

    /**
     * Get order token for process the payment
     * @return array
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $orderId = $order->getIncrementId();
        $new_order_status = $this->config->getNewOrderStatus();

        $mage_version = $this->_objectManager->get('Magento\Framework\App\ProductMetadataInterface')->getVersion();
        $magento_version = substr_replace($mage_version, "x", 4);
        $module_version =  $this->_objectManager->get('Magento\Framework\Module\ModuleList')->getOne('BharatX_Payment')['setup_version'];


        $this->logger->info("before orderModel BharatX request execute", [
            "orderId" => $orderId,
            "incrementId" => $order->getIncrementId(),
            "getId" => $order->getId(),
            "entityId" => $order->getEntityId(),
            "new_order_status" => $new_order_status
        ]);

        $orderModel = $this->_objectManager->get('Magento\Sales\Model\Order')->load($order->getEntityId());

        $orderModel->setState('new')
            ->setStatus($new_order_status)
            ->save();

        $this->logger->info("after orderModel BharatX request execute", [
            "orderId" => $orderId,
            "incrementId" => $order->getIncrementId(),
            "getId" => $order->getId(),
            "entityId" => $order->getEntityId(),
            "new_order_status" => $new_order_status
        ]);

        $code = 400;

        $address = $order->getBillingAddress();
        if (empty($address)) {
            $address = $order->getShippingAddress();
        }

        $countryId = $address->getCountryId();
        $getCustomentNumber = $address->getTelephone();

        $countryCode = 91;

        $customerNumber = preg_replace("/^0+|[^0-9]/", '', $getCustomentNumber); // remove the leading zero and any non digit 

        $this->logger->info('customerNumber: ' . $customerNumber);

        if ($countryCode != "") {
            $customerNumber = "+" . $countryCode . $customerNumber;
        }

        $email = $order->getCustomerEmail();

        $amount = (int) (number_format($order->getGrandTotal() * 100, 0, ".", ""));
        $this->logger->info('amount: ' . $amount);

        $customerName = ($address->getFirstname() ?? '') . ' ' . ($address->getMiddlename() ?? '') . ' ' . ($address->getLastname() ?? '');
        $customerName = preg_replace('/\s+/', ' ', $customerName);

        $user = array(
            'name' => $customerName,
            'phoneNumber' => $customerNumber,
            'email' => $email
        );

        $username = $this->config->getConfigData('partner_id');
        $password = $this->config->getConfigData('api_key');

        $transactionId = $orderId . "_bx";

        $return_url = $this->config->getReturnUrl($transactionId);
        $notify_url = $this->config->getNotifyUrl();

        $transaction_mode = $this->config->getTransactionMode();

        $currentTime = new \DateTime();
        $interval = new \DateInterval('PT30M'); // 30 minutes interval
        $currentTime->add($interval);

        $expiryTimestamp = $currentTime->getTimestamp();

        $params = array(
            'transaction' => array(
                'id' => $transactionId,
                'amount' => $amount,
                'mode' => $transaction_mode,
                'notes' => (object)array(
                    'magento_order_id' => $orderId
                )
            ),
            'createConfiguration' => array(
                'successRedirectUrl' => $return_url,
                'failureRedirectUrl' => $return_url,
                'cancelRedirectUrl' => $return_url,
                'webhookUrl' => $notify_url,
                'expiryTimestamp' => $expiryTimestamp
                // "testModeConfigurations" => array(
                //     "shouldFail" => true
                // )
            ),
            'user' => $user
        );

        $this->logger->info("request params", $params);

        $url = $this->config->getBharatxApi() . '/merchant/transaction';

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

        if ($err) {
            $responseContent = [
                'success' => false,
                'message' => 'Unable to create your order. Please contact support.',
            ];

            $this->logger->error("Bharatx merchant transaction creation API failed " . $err);
        } else {
            $bxOrder = json_decode($curlResponse);

            $this->logger->info("request order creation api response", [
                "bxOrder" => $bxOrder
            ]);

            if (isset($bxOrder->transaction) && isset($bxOrder->transaction->url)) {
                $code = 200;
                $redirectUrl = $bxOrder->transaction->url;
                $this->logger->info('$bxOrder', [
                    'bxOrder' => $bxOrder
                ]);
                $responseContent = [
                    'success' => true,
                    'redirectUrl' => $redirectUrl
                ];
            } else {
                $this->logger->info("Transaction or URL property not found in the response.");
                $code = 500;
                $responseContent = [
                    'success' => false,
                    'message' => 'Unable to create your order. Please contact support.'
                ];
            }
        }

        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setHttpResponseCode($code);
        $response->setData($responseContent);

        return $response;
    }
}
