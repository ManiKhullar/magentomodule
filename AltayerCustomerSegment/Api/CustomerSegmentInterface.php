<?php
namespace Altayer\CustomerSegment\Api;

interface CustomerSegmentInterface
{
    /**
     * GET for Post api
     * @param string $action
     * @param string $customer_id
     * * @param string $segment_id
     * @return string
     */
    public function saveCustomerSegment($action,$customer_id,$segment_id);
}
