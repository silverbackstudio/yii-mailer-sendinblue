<?php 

Yii::setPathOfAlias( 'TijsVerkoyen', Yii::getPathOfAlias('ext') . DIRECTORY_SEPARATOR . basename(__DIR__) );

Yii::import( 'TijsVerkoyen.CssToInlineStyles.CssToInlineStyles' );

class SendinblueMailer extends CApplicationComponent {
    
    public $to = array();
    public $attributes = array();
    public $subject = '';
    public $view = null;
    public $replyTo = null;
    public $from;
    
    public $layout;
    
    public $cc = array();
    public $bcc = array();
    
    public $htmlContent = '';
    
    public $tags = array();
    
    public $apiKey = '';
    public $inlineCss = true;
    
    public $cssFileName = 'mail.css';
    public $cssFilePath;

	/**
	 * Default paths and private properties
	 */
	private $viewPath = 'application.views.mail';
	private $layoutPath = 'application.views.mail.layouts';
	private $baseDirPath = 'webroot.images.mail';
	private $testMode=false;
	private $savePath='webroot.assets.mail';
	
	/**
	 * Sets the CharSet of the message.
	 * @var string
	 */
	public $CharSet='UTF-8';

	/**
	 * Sets the text-only body of the message.
	 * @var string
	 */
	public $AltBody='';
	
	public $ErrorInfo = '';

    const API_ENDPOINT = 'https://api.sendinblue.com/v3/';
    
	/**
	 * Constants
	 */
	const CONFIG_FILE='mail.php'; //Define the name of the config file
	const CONFIG_PARAMS='SendinblueMailer'; //Define the key of the Yii params for the config array
    
	/**
	 * Configure parameters
	 * @param array $config Config parameters
	 * @throws CException
	 */
	private function setConfig($config)
	{
		if(!is_array($config))
			throw new CException("Configuration options must be an array!");
    
    		foreach ( $config as $property => $value ) {
    			if ( ! property_exists( $this, $property ) ) {
    				continue;
    			}
    			$this->$property = $value;
    		}
	}
    
    public function init()
    {

        if(!$this->cssFilePath){
            $this->cssFilePath = Yii::app()->theme->getBasePath() . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR;
        }
        
		//initialize config
		if(isset(Yii::app()->params[self::CONFIG_PARAMS]))
			$config=Yii::app()->params[self::CONFIG_PARAMS];
		else
			$config=require( Yii::getPathOfAlias('application.config') . DIRECTORY_SEPARATOR . self::CONFIG_FILE );
			
		//set config
		$this->setConfig($config);

        parent::init();

    }    

    public function setFrom( $from, $name = '' ){
        $this->from = self::castAddress( $from, $name );
    }    

    public function setTo( $tos, $clear = false ){
	    if ( $clear ) {
	        $this->to = array();
	    }
	    
	    foreach( (array)$tos as $to ) {
		    $this->to[] = self::castAddress( $to );
	    }
    }
    
    public static function castAddress( $address, $name = '' ){
        if( $name ) {
            return [ 'email' => $address, 'name' => $name ];
        } else {
            return [ 'email' => $address ];
        }
    }
    
    public function addReplyTo( $replyTo ){
        $this->replyTo = self::castAddress( $replyTo );
    }

	/**
	 * Set one or more CC email addresses
	 * @param mixed $addresses Email address or array of email addresses
	 * @return boolean True on success, false if addresses not valid
	 */
	public function setCc( $ccs, $clear = false )
	{
	    if ( $clear ) {
	        $this->cc = array();
	    }
	    
	    foreach( (array)$ccs as $cc ) {
		    $this->cc[] = self::castAddress( $cc );
	    }
	}

	/**
	 * Set one or more BCC email addresses
	 * @param mixed $addresses Email address or array of email addresses
	 * @return boolean True on success, false if addresses not valid
	 */
	public function setBcc($bccs, $clear = false )
	{
	    if ( $clear ) {
	        $this->bcc = array();
	    }
	    
	    foreach( (array)$bccs as $bcc ) {
		    $this->bcc[] = self::castAddress( $bcc );
	    }
	}

    public function setSubject( $subject ){
        $this->subject = $subject;
    }   
    
    public function setView( $view ){
		if($view == '')
		{
		    return;
		}
		
		if( ! is_file( $this->getViewFile( $this->viewPath . '.' . $view ) ) ) {
			throw new CException('View "'.$view.'" not found');
		}
		
		$this->view = $view;
		
    }
    
    public function setLayout( $layout ){
		if($layout!='')
		{
			if(!is_file($this->getViewFile($this->layoutPath.'.'.$layout)))
				throw new CException('Layout "'.$layout.'" not found!');
			$this->layout=$layout;
		}
    }    

    public function setBody( $content ){
        $this->htmlContent = $content;
    }

    public function setData( $attributes ){
        $this->attributes = $attributes;
    }    

    public function render( $inlineCss = true ){
        
        if ( $this->view && ! is_numeric( $this->view ) ) {
            $htmlContent  = $this->renderView( $this->viewPath.'.'.$this->view, $this->attributes );
        } 
        
        if( $this->layout ) {
            $htmlContent = $this->renderView( $this->layoutPath . '.' . $this->layout, array( 'content' => $htmlContent, 'data' => $this->attributes ), Yii::getPathOfAlias($this->baseDirPath) );
        }
        
        if( $this->inlineCss && $inlineCss ) {
            $this->htmlContent = $this->inlineCss( $htmlContent );
        } else {
            $this->htmlContent = $htmlContent;
        }
        
        $this->onRender( new CEvent($this, array( 'htmlContent' => $htmlContent ) ) );
        
    }
    
    public function inlineCss( $html ){
        
        try{
            
            $inliner = new \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles($html, file_get_contents($this->cssFilePath.$this->cssFileName));
            $html = $inliner->convert();
        
        } catch (Exception $e){
            Yii::trace('Cannot convert CSS to inline. Error:'.$e);
        }
        
        return $html;
    }    
    
	/**
	 * Render the view file
	 * @param string $viewName Name of the view
	 * @param array $viewData Data for extraction
	 * @return string The rendered result
	 * @throws CException
	 */
	public function renderView($viewName,$viewData=null)
	{
		//resolve the file name
		if(($viewFile=$this->getViewFile($viewName))!==false)
		{
			//use controller instance if available or create dummy controller for console applications
			if(isset(Yii::app()->controller))
				$controller=Yii::app()->controller;
			else
				$controller=new CController(__CLASS__);

			//render and return the result
			return $controller->renderInternal($viewFile,$viewData,true);
		}
		else
		{
			//file name does not exist
			throw new CException('View "'.$viewName.'" does not exist!');
		}

	}
	
	/**
	 * Find the view file for the given view name
	 * @param string $viewName Name of the view
	 * @return string The file path or false if the file does not exist
	 */
	public function getViewFile($viewName)
	{
		//In web application, use existing method
		if(isset(Yii::app()->controller))
			return Yii::app()->controller->getViewFile($viewName);
		//resolve the view file
		//TODO: support for themes in console applications
		if(empty($viewName))
			return false;

		$viewFile=Yii::getPathOfAlias($viewName);
		if(is_file($viewFile.'.php'))
			return Yii::app()->findLocalizedFile($viewFile.'.php');
		else
			return false;
	}	
    
    public static function flatten($array, $prefix = '') {
        $output = array();
        foreach ($array as $key => $value) {
            if (is_array($value)){
                $output = array_merge( $output, self::flatten($value, $key . '_' ));
            } elseif( $value instanceof CModel ) {
                $output = array_merge( $output, self::flatten( $value->attributes, $key . '_' ) );
            } else {
                $output[$prefix . $key] = $value;
            }
        }
    
        return $output;
    }    

    public function onRender($event){
        $this->raiseEvent('onRender', $event);
    }
    
    public function onSend($event){
        $this->raiseEvent('onSend', $event);
    }
    
    public function send(){
    
            $params = array(
                'to' => $this->to,
                'params' => self::flatten( $this->attributes ),
                'subject' => $this->subject,
                'replyTo' => $this->replyTo,
            );
            
            if ( $this->view && is_numeric( $this->view ) ) {
                $params['templateId'] = $this->view;
            } else {
		        $this->render();
                $params['htmlContent']  = $this->htmlContent;
                $params['sender'] = $this->from;
            }

            $this->onSend( new CEvent($this, $params) );
    
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
              CURLOPT_URL => self::API_ENDPOINT . '/smtp/email',
              CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'api-key: ' . $this->apiKey,
              ),              
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => json_encode( array_filter( $params ) ),
            ));
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);
            
            curl_close($curl);
            
            if ($err) {
                $this->SetError( $err );
                return false;
            } else if( substr($httpCode, 0, 1) !== '2'  ) {
                $respObject = json_decode( $response );
                if( isset( $respObject->message ) ) {
                    $this->SetError( 'Sendinblue: ' . $respObject->message );
                }
                return false;
            } else {
                return true;
            }      
    }
    
	/**
	 * Get current error message
	 * @return string Error message
	 */
	public function getError()
	{
		return $this->ErrorInfo;
	}   
	
	/**
	 * Set current error message
	 * @return string Error message
	 */
	public function setError( $message )
	{
	    
	    Yii::log( $message, 'error', 'ext.yii-mailer-sendinblue.SendinblueMailer' );
		$this->ErrorInfo = $message;
	}   	
    
}