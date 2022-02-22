<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Echidna\ReorderPad\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @return mixed
     */
    public function isEnabled()
    {
        return $this->scopeConfig->getValue('echidna_reorderpad/general/enable', \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE);
    }
}
