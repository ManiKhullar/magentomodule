<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Echidna\ReorderPad\Observer;

use Echidna\ReorderPad\Helper\Data;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\RequisitionList\Model\RequisitionListFactory;

class CustomerLogin implements ObserverInterface
{
    /**
     * @var RequisitionListFactory
     */
    protected $requisitionListFactory;
    /**
     * @var DateTime
     */
    protected $dateTime;
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @param RequisitionListFactory $requisitionListFactory
     * @param DateTime $dateTime
     * @param Data $helper
     */
    public function __construct(
        RequisitionListFactory $requisitionListFactory,
        DateTime               $dateTime,
        Data                   $helper
    )
    {
        $this->requisitionListFactory = $requisitionListFactory;
        $this->dateTime = $dateTime;
        $this->helper = $helper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $isEnable = $this->helper->isEnabled();
        if ($isEnable) {
            $customer = $observer->getEvent()->getCustomer();
            $customerRequisitionListCollection = $this->requisitionListFactory->create()
                ->getCollection()
                ->addFieldToFilter('customer_id', $customer->getId());
            if (!empty($customer->getData()) && !count($customerRequisitionListCollection)) {
                $customerId = $customer->getId();
                $requisitionList = $this->requisitionListFactory->create();
                $requisitionList->setCustomerId($customerId);
                $requisitionList->setName($customer->getFirstname() . ' ' . $customer->getLastname());
                $requisitionList->setUpdatedAt($this->dateTime->timestamp());
                $requisitionList->save();
            }
        }
    }
}
