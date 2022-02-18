<?php

namespace Altayer\Support\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class PrebookStatus
 */
class FactoryStatus implements OptionSourceInterface
{

    const IS_FACTORY_YES=1;
    const IS_FACTORY_NO=0;

    public static function getOptionArray()
    {
        return [
            self::IS_FACTORY_YES => __('Gap/Factory'),
            self::IS_FACTORY_NO => __('Gap')
        ];
    }

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $res = [];
        foreach (self::getOptionArray() as $index => $value) {
            $res[] = ['value' => $index, 'label' => $value];
        }
        return $res;
    }
}