<?php

/**
 * MonitorOrderCommand.php
 * @package   Altayer\Support\Console
 * @author   Mani <kmanidev6@gmail.com>
 * @date      07/Jan/2020
 */

namespace Altayer\Support\Console;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Altayer\Support\Model\Config\Source\Mappings;
use Magento\Framework\App\ResourceConnection;
use Altayer\Sales\Helper\SlackApiHelper;
use Psr\Log\LoggerInterface as PsrLogger;

/**
 * Class MonitorOrderaCommand
 * @package Altayer\Support\Console\Command
 */
class MonitorOrderCommand extends Command
{
    protected $scopeConfig;
    protected $connection;
    protected $_mappings;
    protected $_logger;

    const XML_CONFIG_ENABLE = 'altayer_order_monitor/order_monitor/enable_order_monitoring';
    const XML_CONFIG_EXCLUDE_EMAIL = 'altayer_order_monitor/order_monitor/order_monitor_exclude_emailids';
    const XML_PATH_STORE_NAME = "general/store_information/name";
    const XML_PATH_SLACK_CHANNEL_NAME = 'altayer_order_monitor/order_monitor/order_monitor_slack_channel';
    const XML_PATH_ORDER_INTERVAL = 'altayer_order_monitor/order_monitor/order_interval';

    /**
     * @var SlackApiHelper
     */
    protected $slackHelper;

    protected function configure()
    {
        $this->setName('monitor:order')
            ->setDescription('Hourly order monitoring');
        parent::configure();
    }

    /**
     * MonitorOrderaCommand constructor.
     * @param ResourceConnection $resourceConnection
     * @param Mappings $mappings
     * @param ScopeConfigInterface $scopeConfig
     * @param SlackApiHelper $slackApiHelper
     * @param PsrLogger $logger
     * @param null $name
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Mappings $mappings,
        ScopeConfigInterface $scopeConfig,
        SlackApiHelper $slackApiHelper,
        PsrLogger $logger,
        $name = null
    )
    {
        parent::__construct($name);
        $this->scopeConfig = $scopeConfig;
        $this->connection = $resourceConnection->getConnection();
        $this->_mappings = $mappings;
        $this->slackHelper = $slackApiHelper;
        $this->_logger = $logger;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {

            $this->_logger->debug("Order Monitoring: start ");
            $enable = $this->scopeConfig->getValue(self::XML_CONFIG_ENABLE);

            // if not enabled then exist.

            if (!$enable) {
                $this->_logger->debug("Order Monitoring: not enabled ");
                return;
            }

            // if configuration not present then exit.
            if (!$this->isConfigurationPresent()) {
                $this->_logger->debug("Order Monitoring: configuration for brand has not done");
                return;
            }

            $slackChannelName = $this->scopeConfig->getValue(self::XML_PATH_SLACK_CHANNEL_NAME);

            if (!$slackChannelName) {
                $this->_logger->debug("Order Monitoring: slack channel has not configured yet ");
            }

            //check if order placed last hour for the specific country

            $configuredCurrency = $this->getAllConfiguredCurrency();

            if (empty($configuredCurrency)) {
                $this->_logger->debug("Order Monitoring: missing currency configuration ");
            }

            $ignoreEmailIds = $this->scopeConfig->getValue(self::XML_CONFIG_EXCLUDE_EMAIL);

            $missingOrder = $this->isOrderPlaced($configuredCurrency, $ignoreEmailIds);

            if (empty($missingOrder)) {
                $this->_logger->debug("Order Monitoring: No Missing order");
                return;
            }

            $orderDetails = $this->getLastPlacedOrder($missingOrder, $ignoreEmailIds);

            $slackChannelName = '#' . $this->scopeConfig->getValue(self::XML_PATH_SLACK_CHANNEL_NAME);


            $this->slackHelper->sendSlackMessage($this->getSlackMessage($missingOrder, $orderDetails), '#' . $slackChannelName);

            $this->_logger->debug("Order Monitoring: end ");

        } catch (\Exception $e) {
            $this->_logger->debug("Order Monitoring: Error" . $e->getMessage() . ' :: ' . $e->getFile() . ' :: ' . $e->getLine());
        }

    }

    /**
     * @return bool
     */
    private function isConfigurationPresent()
    {
        $sourceArray = $this->_mappings->toOptionArray();
        if (empty($sourceArray)) {
            return false;
        }
        return true;
    }

    /**
     * @return bool|string
     */
    protected function getDate()
    {
        $timezone = $this->scopeConfig->getValue('general/locale/timezone');
        @date_default_timezone_set($timezone);
        return date("Y-m-d H:i:s");
    }

    /**
     * @return array
     */
    private function getAllConfiguredCurrency()
    {
        $currencyArray = [];
        $data = $this->_mappings->getRawMapping();
        foreach ($data as $country => $currency) {
            $currencyArray [] = $currency;
        }
        return $currencyArray;
    }

    /**
     * @return array
     */
    private function getCurrencyToCountryMap()
    {
        $data = $this->_mappings->getRawMapping();
        $currencyToCountryMap = array_flip($data);
        return $currencyToCountryMap;
    }

    /**
     * @param $currency
     * @param $ignoreEmailIds
     * @return array
     */
    private function getLastPlacedOrder($currency, $ignoreEmailIds)
    {
        $value = "'" . implode('\',\'', $currency) . "'";

        if (!$ignoreEmailIds) {
            $ignoreEmailIds = '\' \'';
        }

        $sql = "SELECT a.increment_id        AS `order_number`, 
                       a.created_at          AS `order_date`, 
                       a.store_currency_code AS `order_currency`, 
                       a.customer_email      AS `customer_email`, 
                       p.method              AS `payment_method` 
                FROM   sales_order a 
                       INNER JOIN (SELECT increment_id, 
                                          Max(entity_id) AS maxid 
                                   FROM   sales_order 
                                   WHERE  store_currency_code IN ($value) 
                                   AND customer_email NOT IN ($ignoreEmailIds)
                                   GROUP  BY store_currency_code) AS b 
                               ON a.entity_id = b.maxid 
                       INNER JOIN sales_order_payment p 
                               ON a.entity_id = p.parent_id; ";

        $data = $this->connection->fetchAll($sql);

        return $data;
    }


    /**
     * @param $currency
     * @param $ignoreEmailIds
     * @return array
     */
    private function isOrderPlaced($currency, $ignoreEmailIds)
    {

        if (!$ignoreEmailIds) {
            $ignoreEmailIds = '\' \'';
        }

        $interval = $this->scopeConfig->getValue(self::XML_PATH_ORDER_INTERVAL);
        $interval = (!empty($interval)) ? $interval : 1;

        $value = "'" . implode('\',\'', $currency) . "'";
        $ord = [];
        $sql = "SELECT DISTINCT store_currency_code AS `curerncy`
                FROM   sales_order
                WHERE  store_currency_code IN ($value)
                       AND created_at >= Date_sub(Now(), INTERVAL $interval hour)
                       AND customer_email NOT IN ($ignoreEmailIds)
                ORDER  BY created_at DESC;";

        $data = $this->connection->fetchAll($sql);

        foreach ($data as $orderCurrency) {
            $ord[] = $orderCurrency['curerncy'];
        }

        $missedOrderCountry = array_diff($this->getAllConfiguredCurrency(), $ord);

        return $missedOrderCountry;
    }

    /**
     * @param $missedCountry
     * @param $missedOrderDetails
     * @return string
     */
    private function getSlackMessage($missedCountry, $missedOrderDetails)
    {
        $countryMap = $this->getCurrencyToCountryMap();
        $brand = $this->scopeConfig->getValue(self::XML_PATH_STORE_NAME);

        $interval = $this->scopeConfig->getValue(self::XML_PATH_ORDER_INTERVAL);
        $interval = (!empty($interval)) ? $interval : 1;

        $countryName = "| ";

        foreach ($missedCountry as $country) {
            $countryName = $countryName . $countryMap[$country] . " | ";
        }

        $msg = ':loudspeaker:  ' . $this->getDate() . ' :  *`' . $brand . '`*' . "\n" .
            '>During the last ' . $interval . ' hour(s) there is no order for countries *`' . $countryName . '`* ' . "\n" . '> ```Last placed order detail :- ' . "\n";

        foreach ($missedOrderDetails as $order) {
            $orderId = $order['order_number'];
            $orderDate = $order['order_date'];
            $orderCurrency = $countryMap[$order['order_currency']];
            $orderMethod = $order['payment_method'];
            $customer_email = $order['customer_email'];

            $msg = $msg . ' ' . $orderCurrency . ' = Order Id - ' . $orderId . ' | payment method - ' . $orderMethod . ' | customer email -  ' . $customer_email . ' | placed on - ' . $orderDate . "\n";
        }
        return $msg . '```';
    }
}
