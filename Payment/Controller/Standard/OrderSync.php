<?php

namespace BharatX\Payment\Controller\Standard;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use BharatX\Payment\Model\Config;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\App\Request\Http;


class OrderSync extends Action
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
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonFactory;

    protected $request;

    /**
     * OrderSync constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository,
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Config $config
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Config $config,
        SortOrderBuilder $sortOrderBuilder,
        Http $request
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->config = $config;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;
        parent::__construct($context);
    }


    public function getItems($item)
    {
        return $item->getData();
    }

    public function errorResponse($code, $message)
    {
        $result = $this->jsonFactory->create();
        $result->setHttpResponseCode($code);
        $result->setData(['message' => $message]);
        return $result;
    }

    /**
     * Default controller action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        try {

            $authHeader = $this->request->getHeader('Authorization');
            if (!$this->isAuthorized($authHeader)) {
                // Invalid credentials, return unauthorized response
                return $this->errorResponse(401, "invalid credentials");
            }

            $params = $this->getRequest()->getParams();

            $this->logger->info("OrderSync Request Called", $params);

            $syncTime = 0;
            if (empty($params['syncTime']) === false) {
                // $syncTime = strtotime($params['syncTime']);
                $syncTime = $params['syncTime'];
            }

            $limit = 50;
            if (empty($params['limit']) === false) {
                $limit = $params['limit'];
            }

            $this->logger->info("ordersync request syncTime : " . $syncTime);
            $this->logger->info("ordersync request limit : " . $limit);

            $sortOrder = $this->sortOrderBuilder
                ->setField('created_at')
                ->setAscendingDirection()
                ->create();

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('status', 'pending', 'neq')
                ->addFilter('status', '', 'neq')
                ->addFilter('created_at', date('Y-m-d H:i:s', $syncTime), 'gt')
                ->setPageSize($limit)
                ->addSortOrder($sortOrder)
                ->create();

            // Get all orders
            $orders = $this->orderRepository->getList($searchCriteria);

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

                $this->logger->info('Order ID: ' . $order->getIncrementId());
            }

            $result = $this->jsonFactory->create();
            $result->setHttpResponseCode(201);
            $result->setData($params);
            return $result;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error('OrderSync Error logging Local exception: ' . $e->getMessage());
            return $this->errorResponse(400, $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('OrderSync Error logging order data: ' . $e->getMessage());
            return $this->errorResponse(400, $e->getMessage());
        }
    }

    /**
     * Validate Basic Authentication credentials
     *
     * @param string|null $authHeader
     * @return bool
     */
    protected function isAuthorized($authHeader)
    {
        if ($authHeader) {
            $authData = explode(' ', $authHeader);
            if (count($authData) === 2 && $authData[0] === 'Basic') {
                $decodedAuth = base64_decode($authData[1]);
                $credentials = explode(':', $decodedAuth);
                $username = isset($credentials[0]) ? $credentials[0] : '';
                $password = isset($credentials[1]) ? $credentials[1] : '';

                // Compare with your username and password
                if ($username === $this->config->getPartnerId() && $password === $this->config->getApiKey()) {
                    return true;
                }
            }
        }

        return false;
    }
}
