<?php

namespace Test\Testpayment\Cron;

use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SortOrder;


class OrderSync
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
     * OrderSync constructor.
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository,
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Cron job to log order data
     */
    public function execute()
    {
        try {

            $this->logger->info("OrderSync Cron Called");

            $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('created_at', date('Y-m-d H:i:s', strtotime('-24 hours')), 'gteq')
            ->create();

            // Get all orders
            $orders = $this->orderRepository->getList($searchCriteria);

            // Log order data
            foreach ($orders as $order) {
                // $this->logger->info('Order ID: ' . $order->getIncrementId());
                $this->logger->info('Order ID: ',  $order->getData());
                // Log other order attributes as needed
            }
        } catch (\Exception $e) {
            $this->logger->error('OrderSync Error logging order data: ' . $e->getMessage());
        }
    }
}
