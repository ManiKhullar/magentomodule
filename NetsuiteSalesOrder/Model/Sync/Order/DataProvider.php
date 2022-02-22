<?php

namespace Echidna\NetsuiteSalesOrder\Model\Sync\Order;

use Echidna\Core\Model\Logger;
use Echidna\Netsuite\Model\Api\RequestBuilder as NetSuiteSearchBuilder;
use Echidna\Netsuite\Model\Api\Service as NetSuiteService;
use Echidna\NetsuiteSalesOrder\Model\Config;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use NetSuite\Classes\SearchResult;

/**
 * Provide the required data for the sales order update
 */
class DataProvider
{
    private NetSuiteSearchBuilder $netSuiteSearchBuilder;
    private NetSuiteService $netSuiteService;
    private OrderRepositoryInterface $orderRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private SortOrderBuilder $sortOrderBuilder;
    private Config $config;
    private Logger $logger;

    /**
     * @param NetSuiteSearchBuilder $netSuiteSearchBuilder
     * @param NetSuiteService $netSuiteService
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        NetSuiteSearchBuilder    $netSuiteSearchBuilder,
        NetSuiteService          $netSuiteService,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder    $searchCriteriaBuilder,
        SortOrderBuilder         $sortOrderBuilder,
        Config                   $config,
        Logger                   $logger
    )
    {
        $this->netSuiteSearchBuilder = $netSuiteSearchBuilder;
        $this->netSuiteService = $netSuiteService;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Search Netsuite for the itemFulfillment based on the search data provided
     * @param OrderInterface|Order $order
     * @return SearchResult|null
     */
    public function search(OrderInterface $order): ?SearchResult
    {
        /* Check Order Exists in NetSuite or not*/
        $searchParams = $this->getSearchParams($order);
        $searchCriteria = $this->netSuiteSearchBuilder->prepareSearchCriteria('salesOrder', $searchParams);
        try {
            $response = $this->netSuiteService->search($searchCriteria, $order->getStoreId(), $this->config->getOrderBatchSize());
            if ($response->totalRecords > 0) {
                return $response;
            }
        } catch (LocalizedException | Exception $e) {
            $this->logger->critical(__("Netsuite order search was not processed for the order increment_id: %1", $order->getIncrementId()));
            $this->logger->debug($e->getMessage());
            $this->logger->debug($e->getTraceAsString());
        }
        return null;
    }

    /**
     * @return OrderInterface[]
     */
    public function getOrderCollection(): array
    {

        $websites = $this->config->getStoresForOrderSync();
        // Create the sort order by updated_at field in ascending order to make sure the older orders are processed first
        $updatedAtSort = $this->sortOrderBuilder->setField('updated_at')
            ->setAscendingDirection()
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(
                'status',
                [
                    'processing',
                    'pending'
                ],
                'in'
            )->addFilter(
                'netsuit_order_id',
                '0',
                'leq'
            )->addFilter(
                'store_id',
                $websites,
                'in'
            )->addSortOrder(
                $updatedAtSort
            )->setPageSize(
                $this->config->getOrderBatchSize()
            )->create();

        $results = $this->orderRepository->getList($searchCriteria);
        return $results->getItems();
    }

    /**
     * @param OrderInterface $order
     * @return \string[][]
     */
    private function getSearchParams(OrderInterface $order): array
    {
        return [
            'otherRefNum' => [
                'operator' => 'equalTo',
                'searchValue' => (string)$order->getIncrementId()
            ]
        ];
    }
}
