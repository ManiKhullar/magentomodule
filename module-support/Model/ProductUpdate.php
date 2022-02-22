<?php
/**
 * @category   Altayer
 * @package    Altayer_Support
 * @author   Mani <kmanidev6@gmail.com>
 */

namespace Altayer\Support\Model;

use Altayer\Support\Api\ProductUpdateInterface;

class ProductUpdate implements ProductUpdateInterface
{
	protected $_logger;

	private $_productRepository;

	public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
        $this->_logger = $logger;
        $this->_productRepository = $productRepository;
    }

	public function updateProductData($input)
	{
        $skuArray = explode(',', $input);
        foreach ($skuArray as $sku) {
            $updateBrandSize = $this->updateBrandSize($sku);
            if ($updateBrandSize) {
                $this->_logger->debug('SKU '.$sku.' has been saved');
            }else{
                $this->_logger->debug('SKU '.$sku.' has not been saved');
            }
        }
	}

	protected function updateBrandSize($sku)
    {
        try {
            $product = $this->_productRepository->get($sku);
            $product->setBrandSize("No");
            if($product->save($product)){
            	return true;
            }else{
            	return false;
            }
        } catch (Exception $e) {
        	$this->_logger->debug('SKU '.$sku.' has not been saved due to '. $e->getMessage());
            return false;
        }
    }
}

?>
