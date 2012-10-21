<?php
/**
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('Folder', 'Utility');

/**
 *  Upload Behaviour
 *  @author Andrea Dal Ponte (dalpo85@gmail.com)
 */
class UploadBehavior extends ModelBehavior {

  protected $_defaultSettings = array(
    'defaultSettings' => array(
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
  );

  public function setup(Model $Model, $config = array()) {
    $settings = am ($this->_defaultSettings, $config);
    $this->settings[$Model->alias] = $settings;
  }


  /**
   * Before Save Method..
   *
   * @param object $Model
   * @return boolean
   */
  public function beforeSave(Model $Model, $cascade = true) {
    parent::beforeDelete($Model);

    $uploadedFiles  = array();
    $error = true;
    foreach ($this->settings[$Model->alias] as $field => $fileValues) {
      if($field == 'defaultSettings') continue;
      extract(am($this->settings[$Model->alias]['defaultSettings'],$fileValues));

      // Check for upload
      if(isset($Model->data[$Model->alias]["delete_".$field]) && $Model->data[$Model->alias]["delete_".$field]) {
        //$Model->data[$Model->alias][$field] = '';
        continue;
      }

      // Check for upload
      if(!isset($Model->data[$Model->alias][$field])) {
        continue;
      }
      if(is_array($Model->data[$Model->alias][$field]) && $Model->data[$Model->alias][$field]['error'] == 4) {
        unset($Model->data[$Model->alias][$field]);
        continue;
      }

      // is it a file?
      if (!is_array($Model->data[$Model->alias][$field])) {
        $this->log($field.' is not an array: Form must be multipart/form-data');
        $error = false;
        break;
      }

      // Check error
      if($Model->data[$Model->alias][$field]['error'] > 0) {
        $this->log('Not valid file. error on upload data');
        $error = false;
        break;
      }

      // Check mime
      if(count($allowedMime) > 0 && !in_array($Model->data[$Model->alias][$field]['type'], $allowedMime) && !in_array('*', $allowedMime)) {
        $this->log($field.' > '.$Model->data[$Model->alias][$field]['type'].' is not a valid file\nerror in mime type');
        $error = false;
        break;
      }

      //save original filename
      $originalName =  $Model->data[$Model->alias][$field]['name'];

      // Check extensions
      $parts = explode('.', strtolower($Model->data[$Model->alias][$field]['name']));
      $extension = null;

      if(count($parts)) { $extension = array_pop($parts); }

      $filename = implode('.', $parts);

      if(count($allowedExt) > 0 && !in_array($extension, $allowedExt) && !in_array('*', $allowedExt)) {
        $this->log($field.' is not a valid file\nerror with extension '. $extension);
        $error = false;
        break;
      }

      // Get filename
      $filename = $this->_getFilename($Model, $fileValues, $filename);
      $Model->data[$Model->alias][$field]['name'] = $filename;

      if($extension) {
        $Model->data[$Model->alias][$field]['name'].= '.'.$extension;
      }

      // Get file path
      $dir = $this->_getPath($Model, $fileValues, $dir);

      if (!$dir) {
        $this->log('couldn\'t determine or create directory for the field '.$field);
        $error = false;
        break;
      }

      // Create final save path
      $saveAs = $dir . DS . $Model->data[$Model->alias][$field]['name'];

      // Check if file exists
      if(file_exists($saveAs)) {
        if(!$overwriteExisting || !unlink($saveAs)) {
          $Model->data[$Model->alias][$field]['name'] = uniqid("") . '.' . $extension;
          $saveAs = $dir . DS . $Model->data[$Model->alias][$field]['name'];
        }
      }

      // Attempt to move uploaded file
      if(!move_uploaded_file($Model->data[$Model->alias][$field]['tmp_name'], $saveAs)) {
        $this->log('could not move file');
        $error = false;
        break;
      }


      $this->log("File uploaded: tmpfile {$Model->data[$Model->alias][$field]['tmp_name']} >>> {$saveAs}", LOG_DEBUG);

      $uploadedFiles[$field] = array(
          'dir' => $dir,
          'filename' => $Model->data[$Model->alias][$field]['name'],
          'saveas' => $saveAs
      );
    }

    // If there are errors delete all uploaded files
    if(!$error) {
      foreach ($uploadedFiles as $file) {
        unlink($file['saveas']);
      }
      return false;
    }

    //if all files are uploaded then
    foreach ($this->settings[$Model->alias] as $field => $fileValues) {
      if($field == 'defaultSettings') continue;
      extract(am($this->settings[$Model->alias]['defaultSettings'], $fileValues));
      // Check for upload
      if( !isset($Model->data[$Model->alias][$field])
        && ( !isset($Model->data[$Model->alias]["delete_".$field]) || !$Model->data[$Model->alias]["delete_".$field] ) ) {
        continue;
      }


      //on Edit or Update delete old files
      if(isset($Model->data[$Model->alias]['id']) && $Model->data[$Model->alias][$field]) {
        $oldFileName = $Model->findById($Model->id,array($Model->alias.'.'.$field));

        if( ($oldFileName && $Model->data[$Model->alias][$field]['name'] != $oldFileName[$Model->alias][$field])
          || ( isset($Model->data[$Model->alias]["delete_".$field]) && $Model->data[$Model->alias]["delete_".$field]) ) {
          //delete all thumbnails
          foreach ($thumbsizes as $key => $thumbsize) {
            //unlink($this->_getPath($Model,$dir).DS.$this->getThumbname($Model, $key, $fileName[$Model->alias][$field]));
            if(file_exists($this->_getPath($Model,$fileValues,$dir).DS.$this->getThumbname($Model, $fileValues, $key, $oldFileName[$Model->alias][$field]))
              && !is_dir($this->_getPath($Model,$fileValues,$dir).DS.$this->getThumbname($Model, $fileValues, $key, $oldFileName[$Model->alias][$field]))) {
              unlink($this->_getPath($Model,$fileValues,$dir).DS.$this->getThumbname($Model, $fileValues, $key, $oldFileName[$Model->alias][$field]));
            }
          }
          //delete main file
          if(file_exists($this->_getPath($Model,$fileValues,$dir).DS.$oldFileName[$Model->alias][$field])
            && !is_dir($this->_getPath($Model,$fileValues,$dir).DS.$oldFileName[$Model->alias][$field])) {
            unlink($this->_getPath($Model,$fileValues,$dir).DS.$oldFileName[$Model->alias][$field]);
          }
        }
      }


      if( isset($Model->data[$Model->alias]["delete_".$field]) && $Model->data[$Model->alias]["delete_".$field] ) {
        $Model->data[$Model->alias][$field] = '';
        continue;
      }

      // Create thumbnail of uploaded image
      // This is hard-coded to only support JPEG + PNG + GIF at this time
      if (count($allowedExt) > 0 && (in_array($Model->data[$Model->alias][$field]['type'], $allowedMime)) ||  in_array('*', $allowedMime)) {
        foreach ($thumbsizes as $key => $value) {
          if(!isset($value['proportional'])) $value['proportional'] = true;
          $thumbName = $this->getThumbname ($Model, $fileValues, $key, $uploadedFiles[$field]['filename']);
          $this->createthumb(
            $Model,
            $uploadedFiles[$field]['saveas'],
            $uploadedFiles[$field]['dir'] . DS . $thumbName,
            $value['width'],
            $value['height'],
            $value['proportional']);
        }
      }

      // Update model data
      $Model->data[$Model->alias]["{$field}_dir"] = str_replace(ROOT . DS . APP_DIR . DS, '', $dir);
      $Model->data[$Model->alias]["{$field}_mimetype"] =  $Model->data[$Model->alias][$field]['type'];
      $Model->data[$Model->alias]["{$field}_filesize"] = $Model->data[$Model->alias][$field]['size'];
      $Model->data[$Model->alias]["{$field}_filename"] = $originalName;
      $Model->data[$Model->alias][$field] = $Model->data[$Model->alias][$field]['name'];


      //if deleteMainFile = true then delete it and keep only thumbnails
      if($deleteMainFile && file_exists($uploadedFiles[$field]['saveas'])) {
        unlink($uploadedFiles[$field]['saveas']);
      }
    }

    return true;
  }

  public function beforeDelete(Model $Model, $cascade = true) {
    foreach ($this->settings[$Model->alias] as $field => $fileValues) {
      if($field == 'defaultSettings') continue;
      extract(am($this->settings[$Model->alias]['defaultSettings'],$fileValues));

      $fileName = $Model->findById($Model->id,array($Model->alias.'.'.$field));
      foreach ($thumbsizes as $key => $thumbsize) {
        $dFile = $this->_getPath($Model,$fileValues,$dir).DS.$this->getThumbname($Model, $fileValues, $key, $fileName[$Model->alias][$field]);
        if(file_exists($dFile) && !is_dir($dFile)) {
          unlink($dFile);
        }
      }
      $dFile = $this->_getPath($Model,$fileValues,$dir).DS.$fileName[$Model->alias][$field];
      if(file_exists($dFile) && !is_dir($dFile)) {
        unlink($dFile);
      }
    }

    return true;
  }



  // Method to create thumbnail image

  public function createthumb(&$Model, $name, $filename, $new_w, $new_h, $proportional = true) {

    $system = explode(".", basename($name));
    $extension = array_pop($system);

    if (preg_match("/jpg|jpeg/i", $extension)) {
      $src_img = imagecreatefromjpeg($name);
    } elseif (preg_match("/png/i", $extension)) {
      $src_img = imagecreatefrompng($name);
    } elseif (preg_match("/gif/i", $extension)) {
      $src_img = imagecreatefromgif($name);
    } else {
      $this->log("Unable to create an image from this file: ".$name);
      $src_img = null;
    }

    $old_x = imagesx($src_img);
    $old_y = imagesy($src_img);

    if ($new_w == 'auto' && $new_h != 'auto') {

      $thumb_h = $new_h;
      $ratio = $old_x / $old_y;
      $thumb_w = $ratio * $new_h;

    } elseif ($new_h == 'auto' && $new_w != 'auto') {

      $thumb_w = $new_w;
      $ratio = $old_y / $old_x;
      $thumb_h = $ratio * $new_w;

    } elseif ($proportional && $new_h != 'auto' && $new_w != 'auto') {

      if ($old_x >= $old_y) {

        $thumb_w = $new_w;
        $ratio = $old_y / $old_x;
        $thumb_h = $ratio * $new_w;

        if($thumb_h > $new_h) {

          $thumb_h = $new_h;
          $ratio = $thumb_w / $thumb_h;
          $thumb_w = $ratio * $new_h;

        }

      } elseif ($old_x < $old_y) {

        $thumb_h = $new_h;
        $ratio = $old_x / $old_y;
        $thumb_w = $ratio * $new_h;

        if($thumb_w > $new_w) {
          $thumb_w = $new_w;
          $ratio = $thumb_h / $thumb_w;
          $thumb_h = $ratio * $new_w;
        }

      }
    } else {
      $thumb_w = $new_w;
      $thumb_h = $new_h;
    }


    $dst_img = imagecreatetruecolor($thumb_w, $thumb_h);

    //transparent background..

    imagealphablending($dst_img, false);
    imagesavealpha($dst_img, true);

    //end

    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y);

    if (preg_match("/png/", $extension)) {
      imagepng($dst_img, $filename);
    }
    elseif(preg_match("/gif/", $extension)) {
      imagegif($dst_img, $filename);
    }
    else {
      imagejpeg($dst_img, $filename);
    }
    imagedestroy($dst_img);
    imagedestroy($src_img);
  }



  public function getThumbname ($Model, $fileValues, $thumbsize, $filename, $extension = null) {

    if ($extension == null ) {
      $parts = explode('.', strtolower($filename));
      $extension = array_pop($parts);
      $filename = implode('.', $parts);
    }

    $mergedSettings = am($this->settings[$Model->alias]['defaultSettings']['thumbsizes'],$fileValues['thumbsizes']);
    extract($mergedSettings[$thumbsize]);

    if (strpos($name, '{') === false) {
      return $name.$filename.'.'.$extension;
    }

    $markers = array('{$file}', '{$ext}');
    $replace = array( $filename, $extension);
    return str_replace($markers, $replace, $name);
  }



  public function getThumbSizes(&$Model, $size = null) {

    extract($this->settings[$Model->alias]);

    if ($size) {
      return $thumbsizes[$size];
    }

    return $thumbsizes;
  }

  public function initDir(&$Model, $dirToCheck = null) {

    extract($this->settings[$Model->alias]);

    if ($dirToCheck) {

      $dir = $dirToCheck;

    }

    // Check if directory exists and create it if required

    if(!is_dir($dir)) {

      if($create_directory && !$this->Folder->mkdir($dir)) {

        unset($config[$field]);

        unset($Model->data[$Model->alias][$field]);

        $this->log("Could not write {$dir}");

      }
    }

    // Check if directory is writable

    if(!is_writable($settings['dir'])) {

      unset($config[$field]);

      unset($Model->data[$Model->alias][$field]);

      $this->log("Missing permission to write {$dir}");

    }

    // Check that the given directory does not have a DS on the end

    if($settings['dir'][strlen($settings['dir'])-1] == DS) {

      $settings['dir'] = substr($settings['dir'],0,strlen($settings['dir'])-2);

    }

  }


  /**
   * return the cleaned filename (without the file extension)
   *
   * @param object $Model
   * @param array $fileValues
   * @param string $string
   * @return string
   */
  protected function _getFilename($Model, $fileValues, $string) {

    extract(am($this->settings[$Model->alias]['defaultSettings'],$fileValues));

    if ($uniqidAsFilenames) {
      return uniqid("");
    }

    $string = htmlentities(strtolower($string), null, 'UTF-8');

    foreach ($nameCleanups as $regex => $replace) {
      if ($regex == 'decode') {
        $string = html_entity_decode($string);
      }
      else {
        $string = preg_replace($regex, $replace, $string);
      }
    }
    return $string;
  }


  /**
   * return the absolute file path
   *
   * @param object $Model
   * @param array $fileValues
   * @param string $path
   * @return string
   */
  protected function _getPath ($Model, $fileValues, $path) {

    extract(am($this->settings[$Model->alias]['defaultSettings'],$fileValues));

    if (strpos($path,'{') === false) {
      return $path;
    }

    $markers = array('{APP}', '{DS}', '{IMAGES}', '{WWW_ROOT}', '{FILES}');

    $replace = array( APP, DS, IMAGES, WWW_ROOT, WWW_ROOT.'files'.DS );

    $folderPath = str_replace ($markers, $replace, $path);

    new Folder ($folderPath, true);

    return $folderPath;

  }

  /// validation public functions

  /**
   * Validate an uploaded file
   *
   * @return boolean
   */
  public function validateUploadedFile($Model, $data) {
    //retrive the fieldname
    $eachArray = each($data);
    reset($data);

    //field's name
    $fieldname = $eachArray[0];

    //upload data...
    $upload_info = $data[$fieldname];
    if(!is_array($upload_info)) return true;
    if ($upload_info['error'] == 4) {
      return true;
    }
    if ($upload_info['error'] !== 0) {
      return false;
    }
    return is_uploaded_file($upload_info['tmp_name']);
  }

  /**
   * Check the file extension of an uploaded file
   *
   * @return boolean
   */
  public function validateFileExtension($Model, $data, $extensions) {
    $eachArray = null;

    //retrive the fieldname
    $eachArray = each($data);
    reset($data);

    //field's name
    $fieldname = $eachArray[0];

    //upload data...
    $upload_info = $data[$fieldname];

    if(!is_array($upload_info))  { return true; }
    if($upload_info['error']==4) { return true; }
    $filename = strtolower($upload_info['name']);
    $parts = explode('.', $filename);
    $ext = array_pop($parts);
    return in_array($ext, $extensions);
  }

  /**
   * Validation: Check the file size of an uploaded file
   *
   * @param unknown_type $data
   * @param unknown_type $extensions
   * @return unknown
   */
  public function maxFileSize($Model, $data, $fileSize) {

    $eachArray = each($data);
    reset($data);

    $fieldname =$eachArray[0];

    $upload_info = $data[$fieldname];

    if (isset($upload_info['size']) && $upload_info['size'] > $fileSize) {
      return false;
    } else {
      return true;
    }
  }

  /**
   * Validation: File required
   *
   * @return boolean
   */
  public function requiredFile($Model, $data, $required = true) {
    $eachArray = null;

    $eachArray = each($data);
    reset($data);

    $fieldname = $eachArray[0];

    $upload_info = $data[$fieldname];

    if (!is_array($upload_info) && $required) { return false; }
    if ($upload_info['error'] == 4 && $required) { return false; }
    if ($upload_info['error'] !== 0) { return false; }
    if ($upload_info['size'] == 0) { return false; }
    return true;
  }

  /**
   * Validate: return false if file already exists
   *
   * @return boolean
   */
  public function denyOverwriteFile($Model, $data) {

    $eachArray = null;

    $eachArray = each($data);
    reset($data);

    $fieldname = $eachArray[0];

    if(!is_array($data[$fieldname])) {
      $this->log("Error: This is not a file... Is the Form setted as multipart/form-data ??");
      return false;
    }

    //if there isn't a file continue without validate...
    if($data[$fieldname]['error'] == 4) {
      return true;
    }

    extract(am($this->settings[$Model->alias]['defaultSettings'],$this->settings[$Model->alias][$fieldname]));

    $dir = $this->_getPath($Model, $fieldname, $dir);

    if (!$dir) {
      $this->log('couldn\'t determine or create directory for the field '.$field);
      $error = false;
      break;
    }

    $filename = $data[$fieldname]['name'];
    $filename = $this->_getFilename($Model, $this->settings[$Model->alias][$fieldname], $filename );

    //On Update Retrive existing data
    if($Model->id) {
      $entity = $Model->find('first', array('conditions' => array("{$Model->alias}.{$Model->primaryKey}" => $Model->id)));
      //and skip if you are loading the same file
      if($entity[$Model->alias][$fieldname] == $filename) {
        return true;
      }
    }

    $filePath = $dir . DS . $filename;

    if($deleteMainFile) {

      $eachArray = each($thumbsizes);
      reset($thumbsizes);

      $firstThumbName = $eachArray[0];

      $thumbFileName = $this->_getPath($Model, $this->settings[$Model->alias][$fieldname], $dir) . DS;
      $thumbFileName.= $this->getThumbname($Model, $this->settings[$Model->alias][$fieldname], $firstThumbName, $filename);

      if(file_exists($thumbFileName)) {
        return false;
      } else {
        return true;
      }

    } else {

      if(file_exists($filePath)) {
        return false;
      } else {
        return true;
      }

    }
  }

}

