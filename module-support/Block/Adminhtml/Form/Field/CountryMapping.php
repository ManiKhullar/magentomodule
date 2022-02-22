<?php
namespace Altayer\Support\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

/**
 * CountryMapping.php
 * @package   Altayer\Support\Model\Config\Source
 * @author   Mani <kmanidev6@gmail.com>
 * @date      07/Jan/2020
 */


class CountryMapping extends AbstractFieldArray
{
    /**
     * Prepare to render
     *
     * @return void
     */
    protected function _prepareToRender()
    {
        $this->addColumn('country', ['label' => __('Country')]);
        $this->addColumn('currency', ['label' => __('Currency')]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Country to Currency Mapping');
    }

}
