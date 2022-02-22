<?php

namespace Resume\Career\Block;

class Career extends \Magento\Framework\View\Element\Template
{
    /**
     * Construct
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Resume\Career\Model\ResourceModel\Interest\CollectionFactory  $interestCollectionFactory,
        //\Amasty\Storelocator\Model\ResourceModel\Location\CollectionFactory  $locationCollectionFactory,
        array $data = []
    )
    {
		$this->_interestCollectionFactory = $interestCollectionFactory;
		//$this->_locationCollectionFactory = $locationCollectionFactory;
        parent::__construct($context, $data);
       }

    /**
     * Get form action URL for POST booking request
     *
     * @return string
     */
    public function getFormAction()
    {
            // companymodule is given in routes.xml
            // controller_name is folder name inside controller folder
            // action is php file name inside above controller_name folder

        return $this->getUrl().'career/index/save';
        // here controller_name is index, action is booking
    }
    
    
    public function getInterestArea(){
		return $this->_interestCollectionFactory->create()->addFieldToFilter('is_active',array('eq' => 1));
	}
	
	public function getLocationList(){
		//return $this->_locationCollectionFactory->create()->addFieldToFilter('status',array('eq' => 1));
	}
	
	
}
