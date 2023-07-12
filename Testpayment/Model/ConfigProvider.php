<?php

namespace Test\Testpayment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCode = 'bharatx';

    /**
     * @var \Test\Testpayment\Model\Config
     */
    protected $config;

    /**
     * @param \Test\Testpayment\Model\Config
     */
    public function __construct(
        \Test\Testpayment\Model\Config $config
    ) {
        $this->config = $config;
    }

    /**
     * @return array|void
     */
    public function getConfig()
    {
        if (!$this->config->isActive()) {
            return [];
        }

        $config = [
            'payment' => [
                'testpayment' => [
                    'title' => $this->config->getTitle(),
                    'titleImage' => $this->config->getTitleImage(),
                    'frontendTitle' => $this->config->getFrontendTitle()
                ],
            ],
        ];

        return $config;
    }
}