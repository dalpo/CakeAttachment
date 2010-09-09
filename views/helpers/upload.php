<?php
/**
 *  CakeAttachment Upload Helper
 *  @author Andrea Dal Ponte (dalpo85@gmail.com)
 *  @link http://www.dalpo.net (cooming soon)
 *
 */
App::import('Helper', 'Form');
class UploadHelper extends AppHelper {

    public $helpers = array('Html', 'Form');
    
    protected $_defaultOptions = array(
            'type' => 'file',   //FormHelper input option
            'format' => 'file', // image || file
            'preview' => false, //'preview' => array('url' => '/url/to/preview/image', 'label' => 'preview label'),
            'delete' => true
    );

    public function input($fieldName, $options = array()) {
        $options['type'] = 'file';
        $defaultOptions = $this->_defaultOptions;
        $defaultOptions['legend'] =  __($fieldName, true);

        $options = array_merge($defaultOptions, $options);

        $output = "<fieldset>";
        if(!empty($options['legend'])) {
            $output.= "<legend>" . $options['legend'] . "</legend>";
        }
        $output.= $this->Form->input($fieldName, $options);
        if($options['delete']) {
            $output.= $this->Form->input("delete_{$fieldName}", array('label' => __('Delete ' . $options['format'], true), 'type' => 'checkbox'));
        }
        if($options['preview']) {
            if(!isset($options['preview']['label'])) {
                $options['preview']['label'] = __('Current ' . $options['format'], true);
            }
            $output.= "<div class=\"preview preview_{$options['format']}\">" . $options['preview']['label'] . ":<br/>";
            switch ($options['format']) {
                case "image":
                    $output.= $this->Html->image($options['preview']['url']);
                    break;
                case "file":
                default:
                    $output.= $this->Html->link( __('Download', true) .' '. __($fieldName, true), $options['preview']['url'], array('target' => '_blank'));
                    break;
            }
            $output.= "</div>";
        }

        $output.= '</fieldset>';

        return $this->output($output);
    }

    public function imageInput($fieldName, $options = array()) {
        $options['format'] = 'image';
        return $this->input($fieldName, $options);
    }
    
    public function fileInput($fieldName, $options = array()) {
        $options['format'] = 'file';
        return $this->input($fieldName, $options);
    }

}