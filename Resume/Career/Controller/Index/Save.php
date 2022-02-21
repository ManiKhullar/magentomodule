<?php
namespace Resume\Career\Controller\Index;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\Filesystem;

class Save extends \Magento\Framework\App\Action\Action
{

    const XML_PATH_EMAIL_RECIPIENT_NAME = 'trans_email/ident_support/name';
    const XML_PATH_EMAIL_RECIPIENT_EMAIL = 'trans_email/ident_support/email';
     
    protected $_inlineTranslation;
    protected $_transportBuilder;
    protected $_scopeConfig;
    protected $_logLoggerInterface;
    protected $_careerFactory;
    protected $_interestFactory;
    protected $_positionFactory;
    //protected $_locationFactory;
    protected $uploaderFactory;
    protected $adapterFactory;
    protected $filesystem;   
    
    
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
       \Resume\Career\Model\CareerFactory $careerFactory,
        UploaderFactory $uploaderFactory,
        AdapterFactory $adapterFactory,
        Filesystem $filesystem,
         \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $loggerInterface,
        \Resume\Career\Model\InterestFactory $interestFactory,
        \Resume\Career\Model\PositionFactory $positionFactory
        //\Amasty\Storelocator\Model\LocationFactory $locationFactory
        ){
        $this->_careerFactory = $careerFactory;
        $this->_interestFactory = $interestFactory;
        $this->_positionFactory = $positionFactory;
        //$this->_locationFactory = $locationFactory;
        $this->uploaderFactory = $uploaderFactory;
        $this->adapterFactory = $adapterFactory;
        $this->filesystem = $filesystem;
        $this->_inlineTranslation = $inlineTranslation;
        $this->_transportBuilder = $transportBuilder;
        $this->_scopeConfig = $scopeConfig;
        $this->_logLoggerInterface = $loggerInterface;        
        parent::__construct($context);
    }
    
    public function execute()
    {
    
    $data = $this->getRequest()->getPostValue();
    $careerObj = $this->_careerFactory->create();  
    
    if($this->validateData($data)){
     
      if(isset($_FILES['resume']['name']) && $_FILES['resume']['name'] != '') {
        try{
			
			$uploadedfileName = $_FILES['resume']['name'];
			$fileExtension = explode('.', $uploadedfileName);
			$fileExtension = end($fileExtension);
			$allowed = array('pdf');
			$ext = pathinfo($uploadedfileName, PATHINFO_EXTENSION);
			 /* File Type Validation */
			if (!in_array($ext, $allowed)) {
				
				$errorMsg = "Uploaded File Type Not Allowed.File Type Must be PDF.";
				$this->messageManager->addError($errorMsg);				
				$this->_redirect('work-with-us');
				return;
			}
						
          $uploaderFactory = $this->uploaderFactory->create(['fileId' => 'resume']);                              
          $uploaderFactory->setAllowedExtensions(['pdf']);
          $imageAdapter = $this->adapterFactory->create();
          //$uploaderFactory->addValidateCallback('custom_image_upload',$imageAdapter,'validateUploadFile');
          $uploaderFactory->setAllowRenameFiles(true);
          $uploaderFactory->setFilesDispersion(true);
          $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
          $destinationPath = $mediaDirectory->getAbsolutePath('workwithus');          
          $random_fileName = 'Resume_'.time();
          $result = $uploaderFactory->save($destinationPath,$random_fileName.'.'.$uploaderFactory->getFileExtension());         
          if (!$result) {
            throw new LocalizedException(
              __('File cannot be saved to path: $1', $destinationPath)
            );
          }
          $imagePath = 'workwithus'.$result['file'];
          $data['resume'] = $imagePath;
        } catch (\Exception $e) {
        }
      }
      //print_r($data);
      unset($data['form_key']);
      //unset($data['submit_btn']);
      $careerObj->setData($data)->save();
      //$this->sendReplyEmail($data);
      //$this->sendHrEmail($data);      
      $this->messageManager->addSuccess('Your Application has been submitted successfully.');
    }else{      
      $this->messageManager->addError('Please fill all the required fields.');
    } 
        
        
        $resultRedirect = $this->resultRedirectFactory->create();
        $url = $this->_redirect->getRefererUrl();
        return $resultRedirect->setPath($url);
    }
    
    
    public function validateData($data){
    $require_field = array('work_area', 'work_skill', 'work_experience', 'about_you');
    foreach($data as $key => $val){
        if(in_array($key,$require_field)){
         if($val == '')
          return false;  
         }
      }
    return true;      
  }
  
  
  public function sendReplyEmail($data){
    try
        {
      // Send Mail
            $this->_inlineTranslation->suspend();
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;             
            
             
            $senderEmail = $this->_scopeConfig ->getValue('trans_email/ident_general/email',\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
             
            $senderName = $this->_scopeConfig ->getValue('trans_email/ident_general/name',\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            
            $sentToEmail = $data['email'];
            $sentToName = $data['name'];
            $sender = [
                'name' => $senderName,
                'email' => $senderEmail
            ]; 
             
             
            $transport = $this->_transportBuilder
            ->setTemplateIdentifier('career_email_template')
            ->setTemplateOptions(
                [
                    'area' => 'frontend',
                    'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                ]
                )
                ->setTemplateVars([
                    'name'  => $data['name'],
                    'email'  => $data['email']
                ])
                ->setFrom($sender)
                ->addTo($sentToEmail,$sentToName)
                //->addTo('owner@example.com','owner')
                ->getTransport();
                 
                $transport->sendMessage();
                 
                $this->_inlineTranslation->resume();               
                 
        } catch(\Exception $e){
            $this->messageManager->addError($e->getMessage());
            $this->_logLoggerInterface->debug($e->getMessage());
            exit;
        }
  }
  
  public function sendHrEmail($data){
    
    try
        {
            // Send Mail
            $this->_inlineTranslation->suspend();
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;             
           
            
          
             /****************************/
             $data['interest'] ='';
             $data['position'] ='';
             $data['location'] ='';
             if(isset($data['interest_id'])){
         $interest = $this->_interestFactory->create()->load($data['interest_id']);
         if(is_object($interest))
         $data['interest'] = $interest->getTitle();
         
       } 
       if(isset($data['position_id'])){
         $position = $this->_positionFactory->create()->load($data['position_id']);
         if(is_object($position))
         $data['position'] = $position->getTitle();
       }
       if(isset($data['store_location'])){
         
         
       }             
                              
             /****************************/
            $senderEmail = $this->_scopeConfig ->getValue('trans_email/ident_general/email',\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
             
            $senderName = $this->_scopeConfig ->getValue('trans_email/ident_general/name',\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            
           
            $sentToEmail = $this->_scopeConfig ->getValue('career/general/hr_email',\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $sentToName = 'HR';
            $sender = [
                'name' => $senderName,
                'email' => $senderEmail
            ]; 
             
            $transport = $this->_transportBuilder
            ->setTemplateIdentifier('RESUME_hr_email_template')
            ->setTemplateOptions(
                [
                    'area' => 'frontend',
                    'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                ]
                )
                ->setTemplateVars([                    
                    'name' => $data['name'],
                    'email'  => $data['email'],
                    'telephone' => $data['telephone'],
                    'interest'  => $data['interest'],
                    'position' => $data['position'],
                    'location'  => $data['location'],
                    'current_location' => $data['current_location'],
                    'current_ctc'  => $data['current_ctc'],
                    'linkedin_profile'  => $data['linkedin_profile']
                ])
                ->setFrom($sender)
                ->addTo($sentToEmail,$sentToName)
                //->addTo('owner@example.com','owner')
                ->getTransport();
                 
                $transport->sendMessage();
                 
                $this->_inlineTranslation->resume();               
                 
        } catch(\Exception $e){
            $this->messageManager->addError($e->getMessage());
            $this->_logLoggerInterface->debug($e->getMessage());
            exit;
        }
  } 
}
