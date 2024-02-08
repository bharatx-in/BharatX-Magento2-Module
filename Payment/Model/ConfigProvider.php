<?php

namespace BharatX\Payment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCode = 'bharatx';

    /**
     * @var \BharatX\Payment\Model\Config
     */
    protected $config;

    /**
     * @param \BharatX\Payment\Model\Config
     */
    public function __construct(
        \BharatX\Payment\Model\Config $config
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
                'Bharatxpayment' => [
                    'title' => $this->config->getTitle(),
                    'titleImage' => $this->config->getTitleImage(),
                    'frontendTitle' => $this->config->getFrontendTitle()
                ],
            ],
        ];

        return $config;
    }
}