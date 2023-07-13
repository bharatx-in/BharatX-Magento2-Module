<?php

namespace Test\Testpayment\Controller\Standard;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Test\Testpayment\Model\Config;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\App\Request\Http;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;


class AbandonedCart extends Action
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

    protected $cartRepository;

    protected $dateTime;

    protected $quoteIdMaskFactory;

    protected $addressRepository;

    /**
     * AbandonedCart constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository,
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Config $config
     * @param SortOrderBuilder $sortOrderBuilder
     * @param CartRepositoryInterface $cartRepository,
     * @param DateTime $dateTime
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param AddressRepositoryInterface $addressRepository
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Config $config,
        SortOrderBuilder $sortOrderBuilder,
        Http $request,
        CartRepositoryInterface $cartRepository,
        DateTime $dateTime,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        AddressRepositoryInterface $addressRepository
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->config = $config;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;
        $this->cartRepository = $cartRepository;
        $this->dateTime = $dateTime;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->addressRepository = $addressRepository;
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

            $this->logger->info("AbandonedCart Request Called", $params);

            $currentTimestamp = $this->dateTime->gmtTimestamp();
            $fifteenMinutesAgo = $currentTimestamp - (15 * 60);
            $thirtyMinutesAgo = $currentTimestamp - (30 * 60);

            $fromTime = $thirtyMinutesAgo;
            if (empty($params['fromTime']) === false) {
                $fromTime = (int) $params['fromTime'];
            }

            $toTime = $fifteenMinutesAgo;
            if (empty($params['toTime']) === false) {
                $toTime = (int) $params['toTime'];
            }

            $limit = 50;
            if (empty($params['limit']) === false) {
                $limit = (int) $params['limit'];
            }

            $this->logger->info("AbandonedCart request fromTime : ", [$fromTime]);
            $this->logger->info("AbandonedCart request toTime : ", [$toTime]);
            $this->logger->info("AbandonedCart request limit : ", [$limit]);

            $sortOrder = $this->sortOrderBuilder
                ->setField('created_at')
                ->setAscendingDirection()
                ->create();

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('is_active', 1, 'eq')
                ->addFilter('created_at', date('Y-m-d H:i:s', $currentTimestamp), 'lt')
                ->addFilter('created_at', date('Y-m-d H:i:s', $toTime), 'lt')
                ->addFilter('created_at', date('Y-m-d H:i:s', $fromTime), 'gteq')
                ->setPageSize($limit)
                ->addSortOrder($sortOrder)
                ->create();

            $result = $this->cartRepository->getList($searchCriteria);

            $this->logger->info("AbandonedCart Orders", $result->getItems());

            $params = [];

            // Process the retrieved abandoned carts
            foreach ($result->getItems() as $quote) {
                $quoteId = $quote->getId();
                $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quoteId, 'quote_id');
                $maskedId = $quoteIdMask->getMaskedId();

                // $customerId = $quote->getCustomerId();

                // Retrieve customer address
                // $billingAddressId = $quote->getBillingAddress()->getCustomerAddressId();
                // $billingAddress = $this->addressRepository->getById($billingAddressId);

                $billingAddress = $quote->getBillingAddress();
                $shippingAddress = $quote->getShippingAddress();

                $allItems = $quote->getAllItems();

                $items = [];

                foreach ($allItems as $item) {
                    $items[] = $item->getData();
                }

                $params[] = [
                    "quote" => $quote->getData(),
                    "items" => $items,
                    "maskedId" => $maskedId,
                    "billingAddress" => $billingAddress->getData(),
                    "shippingAddress" => $shippingAddress->getData()
                ];

                $this->logger->info("Quote Id: " . $quoteId);
            }

            $result = $this->jsonFactory->create();
            $result->setHttpResponseCode(201);
            $result->setData($params);
            return $result;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error('AbandonedCart Error logging Local exception: ' . $e->getMessage());
            return $this->errorResponse(400, $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('AbandonedCart Error logging order data: ' . $e->getMessage());
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
