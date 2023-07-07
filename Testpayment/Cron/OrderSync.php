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

    public function sendData($params) {

        $this->logger->info("request params", $params);

        $url = 'https://webhook.site/23cabeb7-bb1d-424a-86d5-7fa9f9550d75'; 

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
            // CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            // CURLOPT_USERPWD => $username . ':' . $password,
        ));

        $curlResponse = curl_exec($curl);

        $err = curl_error($curl);

        curl_close($curl);
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
                // $order = $orders[0];
                $payment = $order->getPayment();
                $params = [
                    "order" => $order->getData(),
                    "payment" => $payment->getData()
                ];

                $this->sendData($params);
                $this->logger->info('Order ID: ' . $order->getIncrementId());
            }
        } catch (\Exception $e) {
            $this->logger->error('OrderSync Error logging order data: ' . $e->getMessage());
        }
    }
}
