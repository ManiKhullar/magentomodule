<?php

namespace Echidna\NetsuiteSalesOrder\Model;

use Echidna\Netsuite\Model\Config as NetsuiteConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory;
use Magento\Store\Model\ScopeInterface;

/**
 * Retrieve all the configurations related to NetSuite API
 */
class ScheduleHandler
{
    private $directoryWrite;
    private Filesystem $filesystem;

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        try {
            $this->directoryWrite = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        } catch (FileSystemException $e) {
            // do nothing
        }
    }

    /**
     * Check if another instance of this job is running or not.
     * @return bool return true is not and false otherwise
     * @throws FileSystemException
     */
    public function canProceedWithSchedule(string $flag): bool
    {
        if ($this->directoryWrite->isExist($flag)) {
            $file = $this->directoryWrite->openFile($flag);
            $stat = $file->stat();
            $lastAccessed = $stat['atime'];
            $now = time();
            $expirySeconds = 60 * 60 * 4; // 4 hours
            $flagExpiry = $lastAccessed + $expirySeconds;
            if ($now > $flagExpiry) {
                $this->directoryWrite->delete($flag);
            } else {
                return false;
            }
        }
        $this->directoryWrite->touch($flag);
        return true;
    }

    /**
     * Clean the flag file
     */
    public function cleanFlag(string $flag)
    {
        $this->directoryWrite->delete($flag);
    }
}
