<?php

namespace Test\Testpayment\Controller\Standard;

use Magento\Framework\Controller\ResultFactory;

class Response extends \Test\Testpayment\Controller\CfAbstract
{
    /**
     * @var \Psr\Log\LoggerInterface 
     */
    protected $logger;

    /**
     * @var \Test\Testpayment\Model\Config
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

    // /**
    //  * @var \Test\Testpayment\Helper\Testpayment
    //  */
    // protected $checkoutHelper;

    // /**
    //  * @var \Magento\Sales\Model\OrderFactory
    //  */
    // protected $orderFactory;

     /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Test\Testpayment\Model\Config $config
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
        \Test\Testpayment\Model\Config $config,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Test\Testpayment\Helper\Testpayment $checkoutHelper,
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
     * Get order response from cashfree to complete order
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

        if(empty($request['cf_id']) === false) {

            $transactionId = $request['cf_id'];
            $magentoId = (explode("_", $transactionId))[0];
            $resultRedirect = $this->resultRedirectFactory->create();
            $order = $this->orderFactory->create()->loadByIncrementId($magentoId);
            $validateOrder = $this->checkRedirectOrderStatus($transactionId, $order);


            $this->logger->info("responseexecute", [
                "request" => $request,
                "magentoId" => $magentoId,
                "order" => $order,
                "orderstatus" => $order->getStatus(),
                "validateOrderStatus" => $validateOrder['status']
            ]);

            if ($validateOrder['status'] == "SUCCESS") {

                $this->logger->info("Success called");


                $mageOrderStatus = $order->getStatus();
                $this->logger->info("orderStatus". $mageOrderStatus);
                if($mageOrderStatus === 'processing') {
                    // maybe need to test if $validateOrder['transaction_id'] has the magento_id
                    // $this->processPayment($magentoId, $order);

                }
                $this->processPayment($magentoId, $order);
                $this->messageManager->addSuccess(__('Your payment was successful'));
                $resultRedirect->setPath('checkout/onepage/success');
                return $resultRedirect;
            } else if ($validateOrder['status'] == "CANCELLED") {

                $this->logger->info("Cancelled called");

                $this->messageManager->addWarning(__('Your payment was cancel'));
                $this->checkoutSession->restoreQuote();
                $resultRedirect->setPath('checkout/cart');
                return $resultRedirect;
            } else if ($validateOrder['status'] == "FAILED") {

                $this->logger->info("Failed called");


                $this->messageManager->addErrorMessage(__('Your payment was failed'));
                $order->cancel()->save();
                $resultRedirect->setPath('checkout/onepage/failure');
                return $resultRedirect;
            } else if($validateOrder['status'] == "PENDING"){

                $this->logger->info("Pending called");


                $this->checkoutSession->restoreQuote();
                $this->messageManager->addWarning(__('Your payment is pending'));
                $resultRedirect->setPath('checkout/cart');
                return $resultRedirect;
            } else{

                $this->logger->info("Nothing called"); 


                $this->checkoutSession->restoreQuote();
                $this->messageManager->addErrorMessage(__('There is an error. Payment status is pending'));
                $resultRedirect->setPath('checkout/cart');
                return $resultRedirect;
            }
        } else {

            $this->logger->info("no cf id");


            $order = $this->checkoutSession->getLastRealOrder();
            $code = 400;
            
            $transactionId = $request['additional_data']['cf_transaction_id'];
            
            if(empty($transactionId) === false && $request['additional_data']['cf_order_status'] === 'PAID')
            {
                $orderId = $order->getIncrementId();
                $validateOrder = $this->validateSignature($request, $order);
                if(!empty($validateOrder['status']) && $validateOrder['status'] === true) {
                    $mageOrderStatus = $order->getStatus();
                    if($mageOrderStatus === 'pending') {
                        $this->processPayment($transactionId, $order);
                    }

                    $responseContent = [
                        'success'       => true,
                        'redirect_url'  => 'checkout/onepage/success/',
                        'order_id'      => $orderId,
                    ];

                    $code = 200;
                } else {
                    $responseContent['message'] = $validateOrder['errorMsg'];
                }

                $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                $response->setData($responseContent);
                $response->setHttpResponseCode($code);
                return $response;
            } else {
                $responseContent['message'] = "Cashfree Payment details missing.";
            }
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