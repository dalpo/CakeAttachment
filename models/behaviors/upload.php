<?php
/**
 *  Improved Upload Behaviour
 *  This behaviour was based on Chris Partridge's upload behaviour (http://bin.cakephp.org/saved/17539)
 *  @author Andrea Dal Ponte (dalpo85@gmail.com)
 *  @link http://www.dalpo.net (cooming soon)
 *
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

                //                  'small' => array('width' => 100, 'height' => 100, 'name' => '{$file}.small.{$ext}', 'proportional' => true),
                //
                //                  'medium' => array('width' => 220, 'height' => 220, 'name' => '{$file}.medium.{$ext}', 'proportional' => true),
                //
                //                  'large' => array('width' => 800, 'height' => 600, 'name' => '{$file}.large.{$ext}', 'proportional' => true)

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

    function setup(&$model, $config=array()) {
        $settings = am ($this->_defaultSettings, $config);
        uses('folder');
        $this->settings[$model->name] = $settings;
    }


    /**
     * Before Save Method..
     *
     * @param object $model
     * @return boolean
     */
    function beforeSave(&$model) {
        $uploadedFiles	= array();
        $error = true;
        foreach ($this->settings[$model->name] as $field => $fileValues) {
            if($field == 'defaultSettings') continue;
            extract(am($this->settings[$model->name]['defaultSettings'],$fileValues));

            // Check for upload
            if(isset($model->data[$model->name]["delete_".$field]) && $model->data[$model->name]["delete_".$field]) {
                //$model->data[$model->name][$field] = '';
                continue;
            }

            // Check for upload
            if(!isset($model->data[$model->name][$field])) {
                continue;
            }
            if(is_array($model->data[$model->name][$field]) && $model->data[$model->name][$field]['error'] == 4) {
                unset($model->data[$model->name][$field]);
                continue;
            }

            // is it a file?
            if (!is_array($model->data[$model->name][$field])) {
                $this->log($field.' is not an array: Form must be multipart/form-data');
                $error = false;
                break;
            }

            // Check error
            if($model->data[$model->name][$field]['error'] > 0) {
                $this->log('Not valid file. error on upload data');
                $error = false;
                break;
            }

            // Check mime
            if(count($allowedMime) > 0 && !in_array($model->data[$model->name][$field]['type'], $allowedMime) && !in_array('*', $allowedMime)) {
                $this->log($field.' > '.$model->data[$model->name][$field]['type'].' is not a valid file\nerror in mime type');
                $error = false;
                break;
            }

            //save original filename
            $originalName =  $model->data[$model->name][$field]['name'];

            // Check extensions
            $parts = explode('.', low($model->data[$model->name][$field]['name']));
            $extension = null;

            if(count($parts)) { $extension = array_pop($parts); }
            
            $filename = implode('.', $parts);

            if(count($allowedExt) > 0 && !in_array($extension, $allowedExt) && !in_array('*', $allowedExt)) {
                $this->log($field.' is not a valid file\nerror with extension '. $extension);
                $error = false;
                break;
            }

            // Get filename
            $filename = $this->_getFilename($model, $fileValues, $filename);
            $model->data[$model->name][$field]['name'] = $filename;

            if($extension) {
              $model->data[$model->name][$field]['name'].= '.'.$extension;
            }

            // Get file path
            $dir = $this->_getPath($model, $fileValues, $dir);

            if (!$dir) {
                $this->log('couldn\'t determine or create directory for the field '.$field);
                $error = false;
                break;
            }

            // Create final save path
            $saveAs = $dir . DS . $model->data[$model->name][$field]['name'];

            // Check if file exists
            if(file_exists($saveAs)) {
                if(!$overwriteExisting || !unlink($saveAs)) {
                    $model->data[$model->name][$field]['name'] = uniqid("") . '.' . $extension;
                    $saveAs = $dir . DS . $model->data[$model->name][$field]['name'];
                }
            }

            // Attempt to move uploaded file
            if(!move_uploaded_file($model->data[$model->name][$field]['tmp_name'], $saveAs)) {
                $this->log('could not move file');
                $error = false;
                break;
            }


            $this->log("File uploaded: tmpfile {$model->data[$model->name][$field]['tmp_name']} >>> {$saveAs}", LOG_DEBUG);

            $uploadedFiles[$field] = array(
                    'dir' => $dir,
                    'filename' => $model->data[$model->name][$field]['name'],
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
        foreach ($this->settings[$model->name] as $field => $fileValues) {
            if($field == 'defaultSettings') continue;
            extract(am($this->settings[$model->name]['defaultSettings'], $fileValues));
            // Check for upload
            if( !isset($model->data[$model->name][$field])
                && ( !isset($model->data[$model->name]["delete_".$field]) || !$model->data[$model->name]["delete_".$field] ) ) {
                continue;
            }


            //on Edit or Update delete old files
            if(isset($model->data[$model->name]['id']) && $model->data[$model->name][$field]) {
                $oldFileName = $model->findById($model->id,array($model->name.'.'.$field));

                if( ($oldFileName && $model->data[$model->name][$field]['name'] != $oldFileName[$model->name][$field])
                    || ( isset($model->data[$model->name]["delete_".$field]) && $model->data[$model->name]["delete_".$field]) ) {
                    //delete all thumbnails
                    foreach ($thumbsizes as $key => $thumbsize) {
                        //unlink($this->_getPath($model,$dir).DS.$this->getThumbname($model, $key, $fileName[$model->name][$field]));
                        if(file_exists($this->_getPath($model,$fileValues,$dir).DS.$this->getThumbname($model, $fileValues, $key, $oldFileName[$model->name][$field]))
                            && !is_dir($this->_getPath($model,$fileValues,$dir).DS.$this->getThumbname($model, $fileValues, $key, $oldFileName[$model->name][$field]))) {
                            unlink($this->_getPath($model,$fileValues,$dir).DS.$this->getThumbname($model, $fileValues, $key, $oldFileName[$model->name][$field]));
                        }
                    }
                    //delete main file
                    if(file_exists($this->_getPath($model,$fileValues,$dir).DS.$oldFileName[$model->name][$field])
                        && !is_dir($this->_getPath($model,$fileValues,$dir).DS.$oldFileName[$model->name][$field])) {
                        unlink($this->_getPath($model,$fileValues,$dir).DS.$oldFileName[$model->name][$field]);
                    }
                }
            }


            if( isset($model->data[$model->name]["delete_".$field]) && $model->data[$model->name]["delete_".$field] ) {
                $model->data[$model->name][$field] = '';
                continue;
            }

            // Create thumbnail of uploaded image
            // This is hard-coded to only support JPEG + PNG + GIF at this time
            if (count($allowedExt) > 0 && (in_array($model->data[$model->name][$field]['type'], $allowedMime)) ||  in_array('*', $allowedMime)) {
                foreach ($thumbsizes as $key => $value) {
                    if(!isset($value['proportional'])) $value['proportional'] = true;
                    $thumbName = $this->getThumbname ($model, $fileValues, $key, $uploadedFiles[$field]['filename']);
                    $this->createthumb($model, $uploadedFiles[$field]['saveas'], $uploadedFiles[$field]['dir'] . DS . $thumbName, $value['width'], $value['height'], $value['proportional']);
                }
            }

            // Update model data
            $model->data[$model->name]["{$field}_dir"] = str_replace(ROOT . DS . APP_DIR . DS, '', $dir);
            $model->data[$model->name]["{$field}_mimetype"] =  $model->data[$model->name][$field]['type'];
            $model->data[$model->name]["{$field}_filesize"] = $model->data[$model->name][$field]['size'];
            $model->data[$model->name]["{$field}_filename"] = $originalName;
            $model->data[$model->name][$field] = $model->data[$model->name][$field]['name'];

            //if deleteMainFile = true then delete it and keep only thumbnails
            if($deleteMainFile && file_exists($uploadedFiles[$field]['saveas'])) {
                unlink($uploadedFiles[$field]['saveas']);
            }
        }
    }

    function beforeDelete(&$model) {
        foreach ($this->settings[$model->name] as $field => $fileValues) {
            if($field == 'defaultSettings') continue;
            extract(am($this->settings[$model->name]['defaultSettings'],$fileValues));

            $fileName = $model->findById($model->id,array($model->name.'.'.$field));
            foreach ($thumbsizes as $key => $thumbsize) {
                $dFile = $this->_getPath($model,$fileValues,$dir).DS.$this->getThumbname($model, $fileValues, $key, $fileName[$model->name][$field]);
                if(file_exists($dFile) && !is_dir($dFile)) {
                    unlink($dFile);
                }
            }
            $dFile = $this->_getPath($model,$fileValues,$dir).DS.$fileName[$model->name][$field];
            if(file_exists($dFile) && !is_dir($dFile)) {
                unlink($dFile);
            }
        }
    }



    // Method to create thumbnail image

    function createthumb(&$model, $name, $filename, $new_w, $new_h, $proportional = true) {

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

        $model->data[$model->name]['width'] = $old_x;

        $model->data[$model->name]['height'] = $old_y;

        //Calculate the new dimensions in proportion

        if($new_w == 'auto' && $new_h != 'auto') {

            $thumb_h = $new_h;
            $ratio = $old_x / $old_y;
            $thumb_w = $ratio * $new_h;

        } elseif($new_h == 'auto' && $new_w != 'auto') {

            $thumb_w = $new_w;
            $ratio = $old_y / $old_x;
            $thumb_h = $ratio * $new_w;

        } elseif($proportional && $new_h != 'auto' && $new_w != 'auto') {

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

        //imagecolortransparent($dst_img, imagecolorallocate($dst_img,0,0,0));
        //imagealphablending($dst_img, false);

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



    function getThumbname ($model, $fileValues, $thumbsize, $filename, $extension = null) {

        if ($extension == null ) {
            $parts = explode('.', low($filename));
            $extension = array_pop($parts);
            $filename = implode('.', $parts);
        }

        $mergedSettings = am($this->settings[$model->name]['defaultSettings']['thumbsizes'],$fileValues['thumbsizes']);
        extract($mergedSettings[$thumbsize]);

        if (strpos($name, '{') === false) {
            return $name.$filename.'.'.$extension;
        }

        $markers = array('{$file}', '{$ext}');
        $replace = array( $filename, $extension);
        return str_replace($markers, $replace, $name);
    }



    function getThumbSizes(&$model, $size = null) {

        extract($this->settings[$model->name]);

        if ($size) {
            return $thumbsizes[$size];
        }

        return $thumbsizes;
    }

    function initDir(&$model, $dirToCheck = null) {

        extract($this->settings[$model->name]);

        if ($dirToCheck) {

            $dir = $dirToCheck;

        }

        // Check if directory exists and create it if required

        if(!is_dir($dir)) {

            if($create_directory && !$this->Folder->mkdir($dir)) {

                unset($config[$field]);

                unset($model->data[$model->name][$field]);

                $this->log("Could not write {$dir}");

            }
        }

        // Check if directory is writable

        if(!is_writable($settings['dir'])) {

            unset($config[$field]);

            unset($model->data[$model->name][$field]);

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
     * @param object $model
     * @param array $fileValues
     * @param string $string
     * @return string
     */
    protected function _getFilename($model, $fileValues, $string) {

        extract(am($this->settings[$model->name]['defaultSettings'],$fileValues));

        if ($uniqidAsFilenames) {
            return uniqid("");
        }

        $string = htmlentities(low($string), null, 'UTF-8');

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
     * @param object $model
     * @param array $fileValues
     * @param string $path
     * @return string
     */
    protected function _getPath ($model, $fileValues, $path) {

        extract(am($this->settings[$model->name]['defaultSettings'],$fileValues));

        if (strpos($path,'{') === false) {
            return $path;
        }

        $markers = array('{APP}', '{DS}', '{IMAGES}', '{WWW_ROOT}', '{FILES}');

        $replace = array( APP, DS, IMAGES, WWW_ROOT, WWW_ROOT.'files'.DS );

        $folderPath = str_replace ($markers, $replace, $path);

        new Folder ($folderPath, true);

        return $folderPath;

    }

    /// validation functions

    /**
     * Validate an uploaded file
     *
     * @return boolean
     */
    function validateUploadedFile($model, $data) {
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
    function validateFileExtension($model, $data, $extensions) {
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
        $filename = low($upload_info['name']);
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
    function maxFileSize($model, $data, $fileSize) {

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
    function requiredFile($model, $data, $required = true) {
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
    function denyOverwriteFile($model, $data) {

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

        extract(am($this->settings[$model->name]['defaultSettings'],$this->settings[$model->name][$fieldname]));

        $dir = $this->_getPath($model, $fieldname, $dir);

        if (!$dir) {
            $this->log('couldn\'t determine or create directory for the field '.$field);
            $error = false;
            break;
        }

        $filename = $data[$fieldname]['name'];
        $filename = $this->_getFilename($model, $this->settings[$model->name][$fieldname], $filename );

        //On Update Retrive existing data
        if($model->id) {
            $entity = $model->find('first', array('conditions' => array("{$model->name}.{$model->primaryKey}" => $model->id)));
            //and skip if you are loading the same file
            if($entity[$model->name][$fieldname] == $filename) {
                return true;
            }
        }

        $filePath = $dir . DS . $filename;

        if($deleteMainFile) {

            $eachArray = each($thumbsizes);
            reset($thumbsizes);

            $firstThumbName = $eachArray[0];

            $thumbFileName = $this->_getPath($model, $this->settings[$model->name][$fieldname], $dir) . DS;
            $thumbFileName.= $this->getThumbname($model, $this->settings[$model->name][$fieldname], $firstThumbName, $filename);

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

?>
