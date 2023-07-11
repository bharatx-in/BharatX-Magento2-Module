<?php

namespace Test\Testpayment\Cron;

use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Test\Testpayment\Model\Config;
use Magento\Framework\Api\SortOrderBuilder;
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
     * @var Config 
     */
    protected $config;

    protected $sortOrderBuilder;

    /**
     * OrderSync constructor.
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository,
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Config $config
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Config $config,
        SortOrderBuilder $sortOrderBuilder
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->config = $config;
        $this->sortOrderBuilder = $sortOrderBuilder;
    }


    public function getItems($item)
    {
        return $item->getData();
    }

    public function getSyncTime()
    {
        $url = 'https://90dc5967a878.ngrok.app/api' . '/merchant/magento/syncTime';
        // $url = $this->config->getBharatxApi() . '/merchant/magento/syncTime';

        $username = $this->config->getPartnerId();
        $password = $this->config->getApiKey();

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $username . ':' . $password,
        ));

        $curlResponse = curl_exec($curl);

        $err = curl_error($curl);

        if ($err) {
            $this->logger->info("Error: " . $err);
            throw new \Magento\Framework\Exception\LocalizedException(__('Ordersync symctime failed'));
        }

        curl_close($curl);

        $this->logger->info("syncTimeInseconds : " . $curlResponse);

        $responseData = json_decode($curlResponse, true); // true to convert it into an associative array

        if (isset($responseData['lastTimeStamp'])) {
            $lastTimestamp = $responseData['lastTimeStamp'];
            return $lastTimestamp;
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('Ordersync synctime lasttimestamp not recieved'));
        }
    }

    public function sendData($params)
    {   
        $url = 'https://90dc5967a878.ngrok.app/api' . '/merchant/magento/syncOrders';
        // $url = $this->config->getBharatxApi() . '/merchant/magento/syncOrders';

        $payload = json_encode($params);

        $username = $this->config->getPartnerId();
        $password = $this->config->getApiKey();

        $this->logger->info("username password", [
            "username" => $username,
            "password" => $password
        ]);

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

        if ($err) {
            $this->logger->info("Error: " . $err);
            throw new \Magento\Framework\Exception\LocalizedException(__('Ordersync syncOrders failed'));
        }

        curl_close($curl);
    }

    /**
     * Cron job to log order data
     */
    public function execute()
    {
        try {

            $this->logger->info("OrderSync Cron Called");

            $syncTime = $this->getSyncTime();

            $this->logger->info("syncTime : " . $syncTime);

            $sortOrder = $this->sortOrderBuilder
                ->setField('created_at')
                ->setAscendingDirection()
                ->create();
                
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('status', 'pending', 'neq')
                ->addFilter('status', '', 'neq')
                ->addFilter('created_at', date('Y-m-d H:i:s', $syncTime), 'gt')
                ->setPageSize(500)
                ->addSortOrder($sortOrder)
                ->create();

            // Get all orders
            $orders = $this->orderRepository->getList($searchCriteria);

            $this->logger->info("OrderSync Orders", $orders->getItems());

            $params = [];

            // Log order data
            foreach ($orders as $order) {
                $payment = $order->getPayment();
                $shipping = $order->getShippingAddress();
                $billing = $order->getBillingAddress();
                $allItems = $order->getItems();

                $items = [];

                foreach ($allItems as $item) {
                    $items[] = $item->getData();
                }

                $params[] = [
                    "order" => $order->getData(),
                    "payment" => $payment->getData(),
                    "shipping" => $shipping->getData(),
                    "billing" => $billing->getData(),
                    "items" => $items,
                ];

                $paramsSize = count($params);

                // sending in batches of 50
                if ($paramsSize == 50) {
                    $this->sendData($params);

                    $params = [];
                }

                $this->logger->info('Order ID: ' . $order->getIncrementId());
            }

            $paramsSize = count($params);

            if ($paramsSize > 0) {
                $this->sendData($params);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error('OrderSync Error logging Local exception: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('OrderSync Error logging order data: ' . $e->getMessage());
        }
    }
}
