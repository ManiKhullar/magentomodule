<?php

namespace Echidna\NetsuiteSalesOrder\Model\Sync\Fulfillment;

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
use NetSuite\Classes\SearchResult;

/**
 * Provide the required data for the order item fulfillment
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
     * Prepare the itemFulfillment search parameter values from unfulfilled Magento orders
     * @param bool $groupByStore
     * @return array
     */
    public function prepareSearchValues(bool $groupByStore = false): array
    {
        $orderCollection = $this->getOrderCollection();
        $searchValues = [];
        $reOrganizedOrderData = [];
        foreach ($orderCollection as $order) {
            $orderInternalId = (string)$order->getData('netsuit_order_id');
            $reOrganizedOrderData[$orderInternalId] = $order;
            $searchData = [
                'internalId' => $orderInternalId, // NetSuite Order Internal ID,
                'type' => 'salesOrder'
            ];
            if ($groupByStore) {
                $searchValues[$order->getStoreId()][] = $searchData;
            } else {
                $searchValues[] = $searchData;
            }
        }
        return [$searchValues, $reOrganizedOrderData];
    }

    /**
     * Search Netsuite for the itemFulfillment based on the search data provided
     * @param array $searchData
     * @param string|int $storeId
     * @return SearchResult|null
     */
    public function search(array $searchData, $storeId): ?SearchResult
    {
        $searchParams = $this->getSearchParams($searchData);
        $searchCriteria = $this->netSuiteSearchBuilder->prepareSearchCriteria('itemFulfillment', $searchParams);
        $pageSizeForFulfillmentSearch = $this->config->getFulfillmentBatchSize() * 5; // assuming a maximum of 5 itemfulfillments for an order
        try {
            $response = $this->netSuiteService->search($searchCriteria, $storeId, $pageSizeForFulfillmentSearch);
            if ($response->totalRecords > 0) {
                return $response;
            }
        } catch (LocalizedException | Exception $e) {
            $this->logger->critical(__("Netsuite fulfillment was not processed for the order increment_id: %1", $storeId));
            $this->logger->debug($e->getMessage());
            $this->logger->debug($e->getTraceAsString());
        }
        return null;
    }

    /**
     * Return the order collection which has it's Netsuite internal ID
     * @return OrderInterface[]
     */
    public function getOrderCollection(): array
    {
        $websites = $this->config->getStoresForFulfillmentSync();
        // Create the sort order by updated_at field in ascending order to make sure the older orders are processed first
        /*$updatedAtSort = $this->sortOrderBuilder
            ->setField('updated_at')
            ->setAscendingDirection()
            ->create();*/
        $updatedAtSort = $this->sortOrderBuilder
            ->setField('entity_id')
            ->setDescendingDirection()
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
                'gt'
            )->addFilter(
                'store_id',
                $websites,
                'in'
            )->addSortOrder(
                $updatedAtSort
            )->setPageSize(
                $this->config->getFulfillmentBatchSize()
            )->create();

        $results = $this->orderRepository->getList($searchCriteria);
        return $results->getItems();
    }

    /**
     * @param array $searchValues
     * @return \string[][]
     */
    private function getSearchParams(array $searchValues): array
    {
        return [
            'createdFrom' => [
                'operator' => 'anyOf',
                'searchValue' => $searchValues
            ]
        ];
    }
}
