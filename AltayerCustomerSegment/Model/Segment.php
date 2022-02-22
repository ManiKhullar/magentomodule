<?php
/**
 * Segment.php
 * @category
 * @package
 * @copyright Copyright (c) 2016  
 * @author   Mani <kmanidev6@gmail.com>
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
