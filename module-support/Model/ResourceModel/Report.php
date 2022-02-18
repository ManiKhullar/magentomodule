<?php
namespace Altayer\Support\Model\ResourceModel;
class Report extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('atg_report', 'report_id');
    }

    /** Before save if you want to do any validation
     * @param \Magento\Framework\Model\AbstractModel $object
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {

        return parent::_beforeSave($object);

    }

    /**
     * @param \Magento\Framework\Model\AbstractModel $object
     * @param mixed $value
     * @param null $field
     * @return $this
     */
    public function load(\Magento\Framework\Model\AbstractModel $object, $value, $field = null)
    {

        return parent::load($object, $value, $field);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return \Magento\Framework\DB\Select
     */
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);

        return $select;
    }
}
