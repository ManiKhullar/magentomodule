<?php

namespace Echidna\NetsuiteOrderSyc\Block\Adminhtml\Order\View;

use Magento\Shipping\Helper\Data as ShippingHelper;
use Magento\Tax\Helper\Data as TaxHelper;

class NetsuiteInternalId extends \Magento\Sales\Block\Adminhtml\Order\AbstractOrder
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry             $registry,
        \Magento\Sales\Helper\Admin             $adminHelper,
        array                                   $data = [],
        ?ShippingHelper                         $shippingHelper = null,
        ?TaxHelper                              $taxHelper = null)
    {
        parent::__construct($context, $registry, $adminHelper, $data, $shippingHelper, $taxHelper);
    }

    /**
     * @param $order
     * @return false
     */
    public function getNetsuiteInternalId($order)
    {
        return $order->getNetsuitOrderId() ?? false;
    }
}
