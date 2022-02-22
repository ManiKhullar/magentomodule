<?php
/**
 * Segment.php
 * @category
 * @package
 * @copyright Copyright (c) 2016  
 * @author     Mani <kmanidev6@gmail.com>
 */

namespace Altayer\CustomerSegment\Model\ResourceModel;
use Magento\CustomerSegment\Model\ResourceModel\Segment as MagentoSegment;

class Segment extends MagentoSegment {

    /**
     * @param \Magento\CustomerSegment\Model\Segment $segment
     * @return $this
     * @throws \Exception
     */
    protected function processConditions($segment)
    {
        $websiteIds = $segment->getWebsiteIds();
        $relatedCustomers = [];
        if (!empty($websiteIds)) {
            foreach ($websiteIds as $websiteId) {
                //get customers ids that satisfy conditions
                $customerIds = $segment->getConditions()->getSatisfiedIds($websiteId);
                foreach ($customerIds as $customerId) {
                    $relatedCustomers[] = [
                        'entity_id' => $customerId,
                        'website_id' => $websiteId,
                    ];
                }
            }
        }
        $this->saveMatchedCustomer($relatedCustomers, $segment);
        return $this;
    }

    /**
     * @param $model
     */
    public function insertSegmentData($model)
    {
        $this->getConnection()->insertOnDuplicate(
            'magento_customersegment_customer',
            $model
        );
    }

    /**
     * @param \Magento\CustomerSegment\Model\Segment $model
     * @return Segment|void
     */
    public function deleteSegmentCustomers($model)
    {
        $this->getConnection()->delete(
            $this->getTable('magento_customersegment_customer'),
            ['segment_id=?' => $model['segment_id'],'customer_id=?' => $model['customer_id'],'website_id=?' => $model['website_id']]
        );
    }

    /**
     * @param $customerEmails
     * @return false|string
     */
    public function getCustomerIdsFromEmail($customerEmails)
    {
        $custemails = '';$ids ='';
        $customerEmails = explode(',', $customerEmails);
        foreach ($customerEmails as $email){
            $custemails .= "'".$email."',";
        }
        $custemails = substr($custemails, 0, -1);
        $query = $this->getConnection()->fetchAll("SELECT entity_id FROM customer_entity WHERE email in ($custemails)");
        foreach ($query as $row){
            $ids .=   $row['entity_id'].",";
        }
        $ids = substr($ids, 0, -1);
        return $ids;
    }

    /**
     * @return false|string
     */
    public function getSegmentWebsiteIds($segment_id)
    {
        $ids ='';
        $query = $this->getConnection()->fetchAll("SELECT website_id FROM magento_customersegment_website WHERE segment_id = $segment_id");
        foreach ($query as $row){
            $ids .=   $row['website_id'].",";
        }
        $ids = substr($ids, 0, -1);
        return $ids;
    }
}
