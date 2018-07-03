<?php
/**
 * CEmailLogRoute class file.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */
/**
 * CEmailLogRoute sends selected log messages to email addresses.
 *
 * The target email addresses may be specified via {@link setEmails emails} property.
 * Optionally, you may set the email {@link setSubject subject}, the
 * {@link setSentFrom sentFrom} address and any additional {@link setHeaders headers}.
 *
 * @property array $emails List of destination email addresses.
 * @property string $subject Email subject. Defaults to CEmailLogRoute::DEFAULT_SUBJECT.
 * @property string $sentFrom Send from address of the email.
 * @property array $headers Additional headers to use when sending an email.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.logging
 * @since 1.0
 */
class CSendinblueEmailLogRoute extends CEmailLogRoute
{
    
    public $mailer = null;
    public $apiKey = null;
    
    const API_ENDPOINT = 'https://api.sendinblue.com/v3/';
    
    public static function castAddress( $address, $name = '' ){
        if( $name ) {
            return [ 'email' => $address, 'name' => $name ];
        } else {
            return [ 'email' => $address ];
        }
    }    
    
	/**
	 * Sends an email.
	 * @param string $email single email address
	 * @param string $subject email subject
	 * @param string $message email content
	 */
	protected function sendEmail($email,$subject,$message)
	{

        $params = array(
            'to' => self::castAddress( $email ),
            'subject' => $subject,
           
        );
        
        $params['htmlContent'] = $message;
        
        $from = $this->getSentFrom();
        if ( $from ) {
            $params['replyTo'] = self::castAddress( $from );
            $params['sender'] = self::castAddress( $from );
        }
        
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
            return false;
        } else if( substr($httpCode, 0, 1) !== '2'  ) {
            return false;
        } 
        
        return true;

	}
    
}