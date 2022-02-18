<?php
/**
 * @package Altayer_Support
 * @author Amrendra Singh <amrendragr8@gmail.com>
 */
namespace Altayer\Support\Block\Adminhtml\Fugitive;


/**
 * Class Items
 *
 * @package Altayer\Support\Block\Adminhtml\Fugitive
 */
class Form extends \Magento\Backend\Block\Template
{
    /**
     * @var string
     */
    protected $_template = "Altayer_Support::fugitive/fugitivetransaction.phtml";

    /**
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry;

    /**
     * Items constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry             $coreRegistry
     * @param array                                   $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Store\Api\StoreRepositoryInterface $storeManager,
        array $data = []
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->_storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    public function getStoreCode()
    {
        /** @var \Magento\Store\Api\StoreRepositoryInterface $repository */

        $stores = $this->_storeManager->getStores();
        $storeName = [];
        foreach ($stores as $store) {
            $storeName[] = $store->getCode();
        }

        return $storeName;
    }

    public function getCardTypes(){
        $cardTypes =[
            '0'=>'MasterCard',
            '1' =>'Visa'
        ];
        return $cardTypes;
    }
    

}
