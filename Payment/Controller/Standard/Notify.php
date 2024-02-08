<?php

namespace BharatX\Payment\Controller\Standard;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;

/**
 * Class Notify
 * To notify customer when if there is any netword falure during payment
 * @package BharatX\Payment\Controller\Standard\Notify
 */
class Notify extends \BharatX\Payment\Controller\CfAbstract implements CsrfAwareActionInterface
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
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     *
     * @return void
     */
    public function execute()
    {

        $this->logger->info("webhook called");

        $request = $this->getRequest();

        $validateOrder = $this->validateWebhook($request);

        $this->logger->info("validateOrder", $validateOrder);

        if (isset($validateOrder['transaction_id'])) {
            $transactionId = $validateOrder['transaction_id'];
            $magentoId = $transactionId;
            $order = $this->objectManagement->create('Magento\Sales\Model\Order')->loadByIncrementId($magentoId);

            $mageOrderStatus = $order->getStatus();

            $this->logger->info("mageOrderStatus" . $mageOrderStatus);

            if ($mageOrderStatus === SELF::STATE_PENDING) {
                if ($validateOrder['status'] == "SUCCESS") {

                    // maybe need to test if $validateOrder['transaction_id'] has the magento_id
                    $this->processPayment($magentoId, $order);

                    $this->logger->info("BharatX Notify order success for BharatX transaction_id(:$transactionId)");
                } else if ($validateOrder['status'] == "FAILURE" || $validateOrder['status'] == "CANCELLED") {
                    $orderStatus = self::STATE_CANCELED;
                    $this->processWebhookStatus($orderStatus, $order);
                    $this->logger->info("BharatX Notify change magento order status to (:$orderStatus) BharatX transaction_id(:$transactionId)");
                } else {
                    $status = $validateOrder['status'];
                    $this->logger->info("BharatX Notify processing payment for BharatX transaction_id(:$transactionId) is status (:$status)");
                }
            } else {
                $this->logger->info("Order has been already in processing state for BharatX transaction_id(:$transactionId)");
            }
        } else {
            $this->logger->error("Bharatx Notify transaction id not found");
        }
    }
}

