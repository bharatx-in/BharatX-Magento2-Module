<?php

namespace BharatX\Payment\Controller\Standard;

use Magento\Framework\Controller\ResultFactory;

class Response extends \BharatX\Payment\Controller\CfAbstract
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
     * @var \BharatX\Payment\Helper\Bharatxpayment
     */
    protected $checkoutHelper;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \BharatX\Payment\Model\Config $config
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\DB\Transaction $transaction
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
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \BharatX\Payment\Helper\Bharatxpayment $checkoutHelper,
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
        $this->orderFactory     = $orderFactory;
        $this->checkoutHelper   = $checkoutHelper;
    }

    /**
     * Get order response from backend to complete order
     * @return array
     */
    public function execute()
    {

        $this->logger->info("Response called");
        $request = $this->getRequest()->getParams();
        $responseContent = [
            'success'       => false,
            'redirect_url'  => 'checkout/#payment',
            'parameters'    => []
        ];

        if (empty($request['cf_id']) === false) {

            $transactionId = $request['cf_id'];
            $magentoId = $transactionId;
            $resultRedirect = $this->resultRedirectFactory->create();
            $order = $this->orderFactory->create()->loadByIncrementId($magentoId);

            $mageOrderStatus = $order->getStatus();

            $validateOrder = [
                'status' => 'ERROR'
            ];

            $this->logger->info("response mageOrderStatus " . $mageOrderStatus);

            if ($mageOrderStatus == SELF::STATE_PENDING) {
                $validateOrder = $this->checkRedirectOrderStatus($transactionId, $order);
            } else if ($mageOrderStatus == SELF::STATE_PROCESSING) {
                $validateOrder['status'] = "SUCCESS";
            } else if ($mageOrderStatus == SELF::STATE_CANCELED) {
                $validateOrder['status'] = "FAILURE";
            }

            if ($validateOrder['status'] == "SUCCESS") {

                // maybe need to test if $validateOrder['transaction_id'] has the magento_id
                if ($mageOrderStatus == SELF::STATE_PENDING) {
                    $this->processPayment($magentoId, $order);
                }

                $this->logger->info("Bharatx Response payment successfull for transactionId " . $transactionId);

                $this->messageManager->addSuccessMessage('Your payment was successful');
                $resultRedirect->setPath('checkout/onepage/success');
                return $resultRedirect;
            } else if ($validateOrder['status'] == "CANCELLED") {

                $this->logger->info("Bharatx Response payment cancelled for transactionId " . $transactionId);

                // $this->messageManager->addErrorMessage('Your payment was cancel');
                $order->cancel()->save();
                $this->checkoutSession->restoreQuote();
                $resultRedirect->setUrl('/checkout/#payment');
                return $resultRedirect;
            } else if ($validateOrder['status'] == "FAILURE") {

                $this->logger->info("Bharatx Response payment failed for transactionId " . $transactionId);

                // $this->messageManager->addErrorMessage('Your payment was failed');
                $order->cancel()->save();
                $this->checkoutSession->restoreQuote();
                $resultRedirect->setUrl('/checkout/#payment');
                return $resultRedirect;
            } else if ($validateOrder['status'] == "PENDING") {

                $this->logger->info("Bharatx Response payment pending for transactionId " . $transactionId);

                $this->checkoutSession->restoreQuote();
                // $this->messageManager->addWarningMessage('Your payment is pending');
                $resultRedirect->setUrl('/checkout/#payment');
                return $resultRedirect;
            } else {

                $this->logger->info("Bharatx Response payment error for transactionId" . $transactionId, $validateOrder);

                $this->checkoutSession->restoreQuote();
                // $this->messageManager->addErrorMessage('There is an error. Payment status is pending');
                $resultRedirect->setUrl('/checkout/#payment');
                return $resultRedirect;
            }
        } else {

            $this->logger->info("Bharatx Response payment no CF id");

            $order = $this->checkoutSession->getLastRealOrder();
            $code = 400;
            $responseContent['message'] = "BharatX Payment details missing.";
        }

        //update/disable the quote
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $quote = $objectManager->get('Magento\Quote\Model\Quote')->load($order->getQuoteId());
        $quote->setIsActive(true)->save();
        $this->checkoutSession->setFirstTimeChk('0');

        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);
        $response->setHttpResponseCode($code);
        return $response;
    }
}
