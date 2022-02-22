<?php


namespace Altayer\Support\Setup;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductTypes\ConfigInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var EavSetup
     */
    private $eavSetup;

    /**
     * @var ModuleDataSetupInterface
     */
    private $setup;
    

    /**
     * @var ConfigInterface
     */
    protected $productTypeConfig;

    /**
     * UpgradeData constructor.
     * @param EavSetupFactory $eavSetupFactory
     * @param ConfigInterface $productTypeConfig
     *
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        ConfigInterface $productTypeConfig
    )
    {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->productTypeConfig = $productTypeConfig;

    }

    public function upgrade(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    )
    {
        $this->setup = $setup;

        $this->setup->startSetup();

        $this->eavSetup = $this->eavSetupFactory->create([
            'setup' => $this->setup,
        ]);
        
        if (version_compare($context->getVersion(), '1.0.3', '<')) {
            $this->addEnableVideo();
        }

        $this->setup->endSetup();
    }

    
    
    public function addEnableVideo()
    {
        $this->eavSetup->addAttribute(
            Product::ENTITY,
            'enable_video',
            [
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Enable Video',
                'input' => 'select',
                'class' => '',
                'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                'default' => 1,
                'global' => Attribute::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'unique' => false,
                'apply_to' => 'simple',
                'group' => 'General',
                'used_in_product_listing' => true,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'visible_on_front' => true,
                'is_used_for_promo_rules' => true,
            ]
        );
    }
}
