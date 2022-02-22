<?php
namespace Resume\Career\Controller\Index;

class Position extends \Magento\Framework\App\Action\Action
{

    
    protected $_positionCollectionFactory;
    protected $resultJsonFactory;
    
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
       \Resume\Career\Model\ResourceModel\Position\CollectionFactory  $positionCollectionFactory
       //\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->_positionCollectionFactory = $positionCollectionFactory;
        //$this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }
    
    public function execute()
    {
        $positionCollection = $this->_positionCollectionFactory->create();
        //$resultJson = $this->resultJsonFactory->create();
        
        $positionHtml ='';
        $interest_id = $this->_request->getParam('interest_id');
		$positionCollection->addFieldToFilter('career_interest', array('eq' => $interest_id))
                                ->addFieldToFilter('is_active',array('eq' => 1));
         if($positionCollection->getSize()){
			 $positionHtml = '<select name="position_id" id="position_id" required>
			                   <option>Select Position</option>';
			     foreach($positionCollection as $position){
					 $positionHtml .= '<option value="'.$position->getId().'">'.$position->getTitle().'</option>';
					 
				 }              
			   $positionHtml .= '</select>';
			   
			   //echo $positionHtml;
		 }
		 
		                        
		$response = ['success' => 'true','positionHtml'=>$positionHtml];
		echo json_encode($response);
		exit;
    }
}
