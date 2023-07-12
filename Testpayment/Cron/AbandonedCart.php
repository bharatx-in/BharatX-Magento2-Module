<?php

namespace Test\Testpayment\Cron;

use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Test\Testpayment\Model\Config;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;


class AbandonedCart
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

    protected $cartRepository;

    protected $dateTime;

    /**
     * OrderSync constructor.
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository,
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Config $config
     * @param SortOrderBuilder $sortOrderBuilder
     * @param CartRepositoryInterface $cartRepository,
     * @param DateTime $dateTime
     */
    public function __construct(
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Config $config,
        SortOrderBuilder $sortOrderBuilder,
        CartRepositoryInterface $cartRepository,
        DateTime $dateTime
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->config = $config;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->cartRepository = $cartRepository;
        $this->dateTime = $dateTime;
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

            $this->logger->info("AbandonedCart Cron Called");

            $currentTimestamp = $this->dateTime->gmtTimestamp();
            $fifteenMinutesAgo = $currentTimestamp - (15 * 60);
            $thirtyMinutesAgo = $currentTimestamp - (30 * 60);

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('is_active', 1, 'eq')
                ->create();
                // ->addFilter('created_at', $fifteenMinutesAgo, 'lt')
                // ->addFilter('created_at', $thirtyMinutesAgo, 'gte')
                // ->create();

            $result = $this->cartRepository->getList($searchCriteria);

            $this->logger->info("AbandonedCart Orders", $result->getItems());

            $params = [];

            // Process the retrieved abandoned carts
            foreach ($result->getItems() as $quote) {
                $quoteId = $quote->getId();

                $this->logger->info("Quote Id: " . $quoteId);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error('AbandonedCart Error logging Local exception: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('AbandonedCart Error logging order data: ' . $e->getMessage());
        }
    }
}
