<?php
namespace Altayer\CustomerSegment\Model\Api;
use Psr\Log\LoggerInterface;


class CustomerSegmentInterface
{
    protected $logger;
    protected $_segmentFactory;
    protected $_segment;

    /**
     * CustomerSegmentInterface constructor.
     * @param LoggerInterface $logger
     * @param \Altayer\CustomerSegment\Model\ResourceModel\SegmentFactory $segmentFactory
     * @param \Altayer\CustomerSegment\Model\ResourceModel\Segment $segment
     */
    public function __construct(
        LoggerInterface $logger,
        \Altayer\CustomerSegment\Model\ResourceModel\SegmentFactory $segmentFactory,
        \Altayer\CustomerSegment\Model\ResourceModel\Segment $segment
    )
    {
        $this->logger = $logger;
        $this->_segmentFactory = $segmentFactory;
        $this->_segment = $segment;
    }


    /**
     * @param $action
     * @param $customer_id
     * @return bool|mixed|string
     */
    public function saveCustomerSegment($action, $customer_id,$segment_id)
    {
        $response = ['success' => false];
        try {
            $segmentArr['segment_id'] = $segment_id;
            $store_id = $this->_segment->getSegmentWebsiteIds($segment_id);//echo "<pre>";print_r($store_id);die;
            $storeIds = explode(',',$store_id);
            $customer_ids = $this->_segment->getCustomerIdsFromEmail($customer_id);
            $customerIds = explode(',',$customer_ids);

            if($action=='add'){
                foreach($storeIds as $storeId){
                    $segmentArr['website_id']  = $storeId;
                    foreach($customerIds as $customerId){
                        $segmentArr['customer_id']  = $customerId;
                        $message = $this->_segment->insertSegmentData($segmentArr);
                    }
                }
                $response = ['message' => 'User added to segment'];
                //echo "User added to segment";
            }else if($action=='remove'){
                foreach($storeIds as $storeId){
                    $segmentArr['website_id']  = $storeId;
                    foreach($customerIds as $customerId){
                        $segmentArr['customer_id']  = $customerId;
                        $message = $this->_segment->deleteSegmentCustomers($segmentArr);
                    }
                }

                $response = ['message' => 'User removed to segment'];
                //echo "User removed to segment";
            }
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
            $this->logger->info($e->getMessage());
        }
        //$returnArray = json_encode($response);
        return $response['message'];
    }
}
