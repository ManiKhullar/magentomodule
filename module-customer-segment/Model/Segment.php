<?php
/**
 * Segment.php
 * @category
 * @package
 * @copyright Copyright (c) 2016 Redbox Digital (http://www.redboxdigital.com)
 * @author    Mathew Marchant <mathew.marchant@redboxdigital.com>
 */

namespace Altayer\CustomerSegment\Model;
use Magento\CustomerSegment\Model\Segment as MagentoSegment;

class Segment extends MagentoSegment {

    /**
     * Set resource model
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('Altayer\CustomerSegment\Model\ResourceModel\Segment');
    }
}