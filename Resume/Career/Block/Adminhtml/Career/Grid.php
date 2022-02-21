<?php
namespace Resume\Career\Block\Adminhtml\Career;


class Grid extends \Magento\Backend\Block\Widget\Grid\Extended
{
    
    protected $_collectionFactory;

    
    protected $_career;
    protected $_interestCollectionFactory;
    protected $_positionCollectionFactory;
    //protected $_locationCollectionFactory;

    
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \Resume\Career\Model\Career $career,
        \Resume\Career\Model\ResourceModel\Interest\CollectionFactory $interestCollectionFactory,
        \Resume\Career\Model\ResourceModel\Position\CollectionFactory $positionCollectionFactory,
        \Resume\Career\Model\ResourceModel\Career\CollectionFactory $collectionFactory,
        //\Amasty\Storelocator\Model\ResourceModel\Location\CollectionFactory  $locationCollectionFactory,
        array $data = []
    ) {
        $this->_collectionFactory = $collectionFactory;
        $this->_interestCollectionFactory = $interestCollectionFactory;
        $this->_positionCollectionFactory = $positionCollectionFactory;
        //$this->_locationCollectionFactory = $locationCollectionFactory;
        $this->_career = $career;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('careerGrid');
        $this->setDefaultSort('career_id');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(true);
        $this->setSaveParametersInSession(true);
    }

    /**
     * Prepare collection
     *
     * @return \Magento\Backend\Block\Widget\Grid
     */
    protected function _prepareCollection()
    {
        $collection = $this->_collectionFactory->create();        
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * Prepare columns
     *
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     */
    protected function _prepareColumns()
    {
        $this->addColumn('career_id', [
            'header'    => __('ID'),
            'index'     => 'career_id',
        ]);
        
        $this->addColumn('work_area', ['header' => __('Work Area'), 'index' => 'work_area']);
        $this->addColumn('work_skill', ['header' => __('Work Skil'), 'index' => 'work_skill']);
        $this->addColumn('work_experience', ['header' => __('Experience'), 'index' => 'work_experience']);
        $this->addColumn('about_you', ['header' => __('About You'), 'index' => 'about_you']);        
        $this->addColumn(
            'resume',
            [
                'header' => __('Resume'),                
                'index'  => 'resume',
                'renderer'=>'Resume\Career\Block\Adminhtml\Career\Edit\Tab\Renderer\Url'
            ]
        );
        
      

        return parent::_prepareColumns();
    }

    /**
     * Row click url
     *
     * @param \Magento\Framework\Object $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['career_id' => $row->getId()]);
    }

    /**
     * Get grid url
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
    
    public function getInterestList(){
		
		$interestList = array();
		$interestCollection = $this->_interestCollectionFactory->create();
		if($interestCollection->getSize()){
			foreach($interestCollection as $interest){
				$interestList[$interest->getId()] = $interest->getTitle();
			}
		}
		
		return $interestList;
	}
	
	
	public function getPositionList(){
		
		$positionList = array();
		$positionCollection = $this->_positionCollectionFactory->create();
		if($positionCollection->getSize()){
			foreach($positionCollection as $position){
				$positionList[$position->getId()] = $position->getTitle();
			}
		}
		
		return $positionList;
	}
	
	public function getLocationList(){
		
		$locationList = array();
		 
		
		return $locationList;
	}
}
