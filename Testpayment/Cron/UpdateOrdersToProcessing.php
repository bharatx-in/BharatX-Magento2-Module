<?php

namespace Test\Testpayment\Cron;

use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Test\Testpayment\Model\Config;
use Test\Testpayment\Controller\CfAbstract;
use Magento\Sales\Model\OrderFactory;

class UpdateOrdersToProcessing 
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var ResourceConnection 
     */
    protected $resourceConnection;

    /**
     * @var Config 
     */
    protected $config;

    /**
     * @var CfAbstract 
     */
    protected $baseController;

    /**
     * @var OrderFactory 
     */
    protected $orderFactory;

    /**
     * OrderSync constructor.
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository,
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param CfAbstract $baseController
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        ResourceConnection $resourceConnection,
        Config $config,
        CfAbstract $baseController,
        OrderFactory $orderFactory
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->resourceConnection = $resourceConnection;
        $this->config = $config;
        $this->baseController = $baseController;
        $this->orderFactory = $orderFactory;
    }

    public function getPendingOrders()
    {
        $connection = $this->resourceConnection->getConnection();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');
        $salesOrderPaymentTable = $this->resourceConnection->getTableName('sales_order_payment');

        $currentTime = new \DateTime();
        $interval = new \DateInterval('P1D'); // 1 day interval
        $currentTime->sub($interval); // Subtract 24 hours

        $select = $connection->select()
            ->from(['sop' => $salesOrderPaymentTable], ['method'])
            ->join(['so' => $salesOrderTable], 'sop.parent_id = so.entity_id', ['status', 'increment_id'])
            ->where('so.status = ?', 'pending')
            ->where('sop.method = ?', 'testpayment')
            ->where('so.created_at >= ?', $currentTime->format('Y-m-d H:i:s'));;

        $result = $connection->fetchAll($select);

        return $result;
    }

    public function execute()
    {

        $this->logger->info("UpdateOrdersToProcessing Cron Called");

        $pendingOrders = $this->getPendingOrders();

        $this->logger->info("UpdateOrdersToProcessing Pending Orders ", $pendingOrders);

        foreach ($pendingOrders as $pendingOrder) {

            $magentoId = $pendingOrder["increment_id"];

            $transactionId = $magentoId . "_" . $this->config->getPartnerId();
            $order = $this->orderFactory->create()->loadByIncrementId($magentoId);

            $validateOrder = $this->baseController->checkRedirectOrderStatus($transactionId, $order);

            if ($validateOrder !== 'PENDING') {
                // $order = $this->orderRepository->get($magentoId);
                // $order = $this->orderFactory->create()->loadByIncrementId($magentoId);
            }

            if ($validateOrder['status'] == "SUCCESS") {
                $this->baseController->processPayment($magentoId, $order);
                $this->logger->info("Bharatx UpdateOrdersToProcessing payment successfull for transactionId " . $transactionId);
            } else if ($validateOrder['status'] == "CANCELLED") {
                $this->logger->info("Bharatx UpdateOrdersToProcessing payment cancelled for transactionId " . $transactionId);
                $order->cancel()->save();
            } else if ($validateOrder['status'] == "FAILURE") {
                $this->logger->info("Bharatx UpdateOrdersToProcessing payment failed for transactionId " . $transactionId);
                $order->cancel()->save();
            } else {
                $this->logger->info("Bharatx UpdateOrdersToProcessing payment " . $validateOrder['status'] . " for transactionId " . $transactionId);
            }
        }

        // Build search criteria to filter orders by payment method and status
        // $searchCriteria = $this->searchCriteriaBuilder
        //     ->addFilter('status', 'pending')
        //     ->create();

        // $paymentMethodFilter = $this->filterBuilder
        //     ->setField('method')
        //     ->setConditionType('eq')
        //     ->setValue('testpayment')
        //     ->create();

        // $statusFilter = $this->filterBuilder
        //     ->setField('status')
        //     ->setConditionType('eq')
        //     ->setValue('pending')
        //     ->create();

        // $searchCriteria = $this->searchCriteriaBuilder
        //     ->addFilters([$paymentMethodFilter, $statusFilter])
        //     ->create();

        // $baseController = 'testpayment';

        // $searchCriteria = $this->searchCriteriaBuilder
        //     ->addFilter('payment.method', $baseController)
        //     ->create();


        // Retrieve orders using the search criteria
        // $orderSearchResult = $this->orderRepository->getList($searchCriteria);

        // $orders = $orderSearchResult->getItems();



        // foreach ($orders as $order) {
        // $this->logger->info('Data', $order->getData);
        // $this->logger->info('Order ID: ' . $order->getIncrementId());
        // $this->logger->info('Order ID: ',  $order->getData());
        // Log other order attributes as needed
        // }

        // foreach ($orders as $order) {
        //     // Perform actions to update the order to processing status
        //     // For example, you can update the order status and state
        //     $order->setStatus('processing');
        //     $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);

        //     // Save the updated order
        //     $this->orderRepository->save($order);
        // }
    }
}