<?php
namespace Resume\Career\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;

class Save extends \Magento\Backend\App\Action
{
    /**
     * @var PostDataProcessor
     */
    protected $dataProcessor;

    /**
     * @param Action\Context $context
     * @param PostDataProcessor $dataProcessor
     */
   /* public function __construct(Action\Context $context, PostDataProcessor $dataProcessor)
    {
        $this->dataProcessor = $dataProcessor;
        parent::__construct($context);
    }*/

    /**
     * {@inheritdoc}
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Resume_Career::save');
    }

    /**
     * Save action
     *
     * @return void
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        if ($data) {
            //$data = $this->dataProcessor->filter($data);
            $model = $this->_objectManager->create('Resume\Career\Model\Career');

            $id = $this->getRequest()->getParam('career_id');
            if ($id) {
                $model->load($id);
            }          
            

            $model->addData($data);

            
            try {                
                
                $model->save();
                $this->messageManager->addSuccess(__('The Career has been saved.'));
                $this->_objectManager->get('Magento\Backend\Model\Session')->setFormData(false);
                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', ['career_id' => $model->getId(), '_current' => true]);
                    return;
                }
                $this->_redirect('*/*/');
                return;
            } catch (\Magento\Framework\Model\Exception $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\RuntimeException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addException($e, __('Something went wrong while saving the Career.'));
            }

            $this->_getSession()->setFormData($data);
            $this->_redirect('*/*/edit', ['career_id' => $this->getRequest()->getParam('career_id')]);
            return;
        }
        $this->_redirect('*/*/');
    }
}
