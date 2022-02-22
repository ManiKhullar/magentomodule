<?php
/**
 * RemoveDuplicateImageDropbox.php
 * @package   Altayer\Support\Console
 * @author   Mani <kmanidev6@gmail.com>
 */

namespace Altayer\Support\Console;

use Altayer\Support\Helper\Data as HelperData;
use Altayer\Integration\Helper\RepositoryHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface as PsrLogger;
use Altayer\Integration\Helper\DropBoxHelper;
use Altayer\Integration\Helper\Utility as ImportUtility;



/**
 * Class UpdateSkuVpnData
 * @package Altayer\Support\Console
 */
class RemoveDuplicateImageDropbox extends Command
{
    const NAME = 'catalog:products:RemoveDuplicateImageDropbox';
    const LOCK_ID = 'delete_duplicate_image_dropbox';

    protected $_dropboxHelper;
    protected $_utility;
    protected $_count;

    /**
     * RemoveDuplicateImageDropbox constructor.
     * @param PsrLogger $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param HelperData $helperData
     * @param DropBoxHelper $dropboxHelper
     * @param null $name
     */
    public function __construct(
        PsrLogger $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        HelperData $helperData,
        DropBoxHelper $dropboxHelper,
        ImportUtility $utility,
        $name = null
    )
    {
        parent::__construct($name);
        $this->_logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->helperData = $helperData;
        $this->_dropboxHelper = $dropboxHelper;
        $this->_utility = $utility;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::NAME);
        $this->setDescription('Remove Duplicate Image from dropbox');
        parent::configure();
    }

    /**
     * export report orders
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lockFile = $this->_utility->getLockFile(self::LOCK_ID,'RemoveDuplicateImageDropbox');
        if (!$lockFile) {
            return;
        }
        try {
            $lockFile->lock();
            $this->_logger->info('Loading all dropbox files to Delete duplicates.');

            $dropbox = $this->_dropboxHelper->getDropboxConnection();
            $listFolderContents = $this->_dropboxHelper->getItemList($dropbox);
            $itemList = $listFolderContents->getItems();
            if (empty($itemList)) {
                $this->_logger->addError('Dropbox: No item found to process');
            }
            $this->deleteDuplicateImage($output,$itemList, $dropbox);
            //Check if we have more items to process
            while ($listFolderContents->hasMoreItems()) {
                if($this->_count > 5000){
                    break;
                }
                //Fetch Cusrsor for listFolderContinue()
                $cursor = $listFolderContents->getCursor();
                //Paginate through the remaining items
                $listFolderContents = $dropbox->listFolderContinue($cursor);
                $remainingItems = $listFolderContents->getItems();
                //populate to duplicate images

                if (!empty($remainingItems)) {
                    $this->deleteDuplicateImage($output,$remainingItems, $dropbox);
                }
            }
        }catch (\Exception $e)
        {

        }finally{
            $lockFile->unlock();
        }
    }

    /**
     * @param $items
     */
    public function deleteDuplicateImage($output,$dropboxItems,$dropbox)
    {
        foreach ($dropboxItems as $item) {
            if (method_exists($item,'getTag') && trim($item->getTag()) === 'file') {
                $fileName = strtolower($item->getName());
                $info = pathinfo($fileName);

                if (strpos($fileName, "(") !== false) {
                    $this->_dropboxHelper->deleteFileByURL($dropbox, $item->getPathDisplay());
                    $output->writeln("<info>Deleting file name: ".$item->getPathDisplay()."</info>");
                    $this->_count = $this->_count+1;
                }
                if($this->_count > 5000){
                    break;
                }
            }
        }
    }

}
