<?php
namespace Altayer\Support\Ui\Component\Listing\DataProviders\Altayer\Support;

use Altayer\Support\Model\ResourceModel\Report\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Class Report
 * @package Altayer\Support\Ui\Component\Listing\DataProviders\Altayer\Support
 */
class Report extends AbstractDataProvider
{
    /**
     * Designer constructor.
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    )
    {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }
}