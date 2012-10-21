# CakeAttachment

CakeAttachment is a plugin for CakePHP to improved easy file upload. The CakeAttachment upload Behavior intent was to keep setup as easy as possible and to treat files as a simple table column. It manages simple validations and can transform its assigned image into thumbnails if needed.


## Installation:

#### Add a git submodule

```shell
  git submodule add git://github.com/dalpo/CakeAttachment.git app/Plugin/CakeAttachment
  git submodule init && git submodule update
```

#### Bootstrap

Add this to load correctly the plugin under app/config/bootstrap.php

```php
  CakePlugin::load('CakeAttachment');
```

#### Model Setup

```php
  class MyUploadModel extends AppModel {

    public $actsAs = array(
      'CakeAttachment.Upload' => array(
        'fieldname' => array(
          'dir' => "{IMAGES}my_upload_directory",
            //thumbnails declaration
            'thumbsizes' => array(
              'main' => array('width' => 500, 'height' => 'auto', 'name' =>  'main.{$file}.{$ext}'),
              'preview' => array('width' => 250, 'height' => 250, 'name' => 'preview.{$file}.{$ext}')
              'thumb' => array('width' => 100, 'height' => 100, 'name' =>  'thumb.{$file}.{$ext}', 'proportional' => false)
            )
        ),
        'another_field' => array(
          'uniqidAsFilenames' => true,
          'dir' => "{FILES}my_second_upload_directory"
        )
      )
    );

  }
```

#### Validations

```php
  public $validate = array(
    'fieldname' => array(
      'notEmpty' => array(
        'rule' => 'requiredFile',
        'message' => 'File required',
        'on' => 'create'
      ),
      'validUpload' => array (
        'rule' => array('validateUploadedFile'),
        'message' => 'Invalid file'
      ),
      'validExtension' => array (
        'rule' => array('validateFileExtension', array('jpg', 'jpeg', 'png', 'gif')),
        'message' => 'Invalid file: upload only jpg, png or gif'
      ),
      'maxFileSize' => array(
        'rule' => array('maxFileSize', 2097152),
       'message' => 'Invalid file'
      )
    )
  );
```

#### Magic Fields
Foreach declared field, theese fields will be automatically updated on every save:

```php
  "{$field}_dir"        // uploaded directory
  "{$field}_mimetype"   // field mimetype
  "{$field}_filesize"   // file size
  "{$field}_filename"   // original file name
```

#### Options

```php
  array(
    //'allowedMime' => array('image/jpeg', 'image/pjpeg', 'image/gif', 'image/png', 'image/x-png'),
    'allowedMime' => array('*'),
    //'allowedExt' => array('jpg','jpeg','gif','png'),
    'allowedExt' => array('*'),
    'overwriteExisting' => true,
    'deleteMainFile' => false,
    'createDirectory' => true,
    'uniqidAsFilenames' => false,
    'thumbsizes' => array(
      //  'small' => array('width' => 100, 'height' => 100, 'name' => '{$file}.small.{$ext}', 'proportional' => true),
      //  'medium' => array('width' => 220, 'height' => 220, 'name' => '{$file}.medium.{$ext}', 'proportional' => true),
      //  'large' => array('width' => 800, 'height' => 600, 'name' => '{$file}.large.{$ext}', 'proportional' => true)
    ),
    'dir' => '{IMAGES}uploads',
    'nameCleanups' => array(
      //'/&(.)(tilde);/' => "$1y", // ñs
      //'/&(.)(uml);/' => "$1e", // umlauts but umlauts are not pronounced the same is all languages.
      //'/�/' => 'ss', // German double s
      //'/&(.)elig;/' => '$1e', // ae and oe symbols
      //'/�/' => 'eth' // Icelandic eth symbol
      //'/�/' => 'thorn' // Icelandic thorn
      '/&(.)(acute|caron|cedil|circ|elig|grave|horn|ring|slash|th|tilde|uml|zlig);/' => '$1', // strip all
      'decode' => true, // html decode at this point
      '/\&/' => ' and ', // Ampersand
      '/\+/' => ' plus ', // Plus
      '/([^a-z0-9\.]+)/' => '_', // None alphanumeric
      '/\\_+/' => '_' // Duplicate sperators
    )
  )
```


## Capistrano

If you use submodules with capistrano you should put this conf under deploy.rb

```ruby
  set :git_enable_submodules, 1
```

