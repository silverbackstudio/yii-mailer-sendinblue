# Yii 1.x [Sendinblue](https://www.sendinblue.com/?tap_a=30591-fb13f0&tap_s=249195-890adb) Mailer

Send transactional emails via Sendinblue API (requires active sendinblue account). 

Supports:

* Local email PHP templates (Yii views)
* Local PHP Layouts
* Automatic CSS inlining via [CssToInlineStyles](https://github.com/tijsverkoyen/CssToInlineStyles)
* Sendinblue Templates
* Sendinblue Template Attributes
* Events: `onSend`, `onRender`

## Install

Clone this repository in `protected/extensions`

Fetch git submodules inside the cloned directory: `git submodule update --init`

Set up the mailer instance in application config:

```php

'components'=>array(
    'mailer'=>array(
        'class'=>'ext.SendinblueMailer.SendinblueMailer',
        'from' => [ 'email' => 'website@example.com', 'name' => 'My Website' ], // The default from address
        'apiKey' => 'XXXXXXXXXXXXXXXXXXXXX', // Sendinblue API Key
    ),
```

## Usage

The class methods are compatible with to the popular [YiiMailer](https://www.yiiframework.com/extension/yiimailer) class.

```php

$mailer = Yii::app()->mailer;
$mailer->setTo('recipient@example.com');
$mailer->setLayout('example_layout');           // default folder: protected/views/layouts/
$mailer->setView('example_view');               // default folder: protected/views/mail/
$mailer->setSubject('Example Subject');
$mailer->addReplyTo( 'replyto@example.com' );

/**
* The following cata will be flattened with this structure in Sendinblue
* template replacements
* 
* array(
*    'key1' => 'value1'
*    'key2_subkey1' => 'subvalue1'
*    'key3_attribute1' => 'attributevalue1'
* )
*/

$mailer->setData( 
    array( 
        'key1' => 'value1'
        'key2' => array(
            'subkey1' => 'subvalue1'
        ),
        'key3' => CModel {
            'attribute1' => 'attributevalue1'
        }
    ) 
);

if( !$mailer->send() ) {
	throw new CHttpException(500, 'Unable to send email: ' . $mailer->GetError() );
}

```