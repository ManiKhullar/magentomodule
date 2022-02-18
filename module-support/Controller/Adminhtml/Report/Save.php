<?php

namespace Altayer\Support\Controller\Adminhtml\Report;

use Magento\Backend\App\Action;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use Magento\Backend\App\Action\Context;

class Save extends Action
{

    /**
     * Logger Interface
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->logger = $logger;
        $this->_scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    /**
     * Save action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        // check if data sent
        $data = $this->getRequest()->getPostValue();
        if ($data) {
            $id = $this->getRequest()->getParam('report_id');
            $model = $this->_objectManager->create('Altayer\Support\Model\Report')->load($id);
            if (!$model->getId() && $id) {
                $this->messageManager->addError(
                    __('This Report  no longer exists.')
                );
                return $resultRedirect->setPath('*/*/');
            }

            // init model and set data
            $this->_populateLoggedinUser($data, $id);
            $model->setData($data);
            try {
                $model->save();
                // display success message

                $reportName = $model->getData("report_name");

                $this->messageManager->addSuccess(
                    __('Report  ' . $reportName . ' has been created successfully.')
                );
                // clear previously saved data from session
                $this->_objectManager->get('Magento\Backend\Model\Session')->setFormData(false);

                // check if 'Save and Continue'
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath(
                        '*/*/edit', ['report_id' => $model->getId()]
                    );
                }
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addError($e->getMessage());
                // save data in session
                $this->_objectManager->get('Magento\Backend\Model\Session')
                    ->setFormData($data);
                // redirect to edit form
                return $resultRedirect->setPath(
                    '*/*/edit',
                    ['report_id' => $this->getRequest()->getParam('report_id')]
                );
            }
        }
        return $resultRedirect->setPath('*/*/');
    }

    /**
     * @param $data
     * @param $id
     */
    protected function _populateLoggedinUser($data, $id)
    {
        $user = $this->_auth->getUser();
        if (isset($id)) {
            $data['created_by'] = $user->getUserName();
            //$model->setData('created_by',$user->getUserName());
        } else {
            $data['updated_by'] = $user->getUserName();
        }
    }
}
