<?php
class ImageResize {
	/**
	 * Images Management Class
	 *
	 * @author Andrea Dal Ponte
	 */
	protected $imgPt;
	protected $width;
	protected $height;
	protected $mime;
	protected $path;
	protected $attr;
	/**
	 * Construct
	 *
	 * @access public
	 * @param string $path
	 */
	public function __construct($path = null) {
		$this->setNewPath($path);
	}
	/**
	 * Set new image's path
	 *
	 * @access public
	 * @param string $path
	 * @return void
	 */
	public function setNewPath($path = null) {
		$this->imageSetting($path);
	}
	/**
	 * Image Settings
	 *
	 * @access protected
	 * @param string $path
	 */
	protected function imageSetting($path = null) {
		$this->path = $path;
		$imgData = getimagesize($this->path);
		$this->width  = $imgData[0];
		$this->height = $imgData[1];
		$this->mime	  = $imgData["mime"];
		$this->attr   = $imgData;
	}
	/**
	 * Get Mime type of image
	 *
	 * @access public
	 * @return string
	 */
	public function getFileMime() {
		return $this->mime;
	}
	/**
	 * Get Image File Type
	 *
	 * @access public
	 * @return string
	 */
	public function getFileType() {
		switch ($this->mime) {
			case "image/gif":
				return "gif";
				break;
			case "image/jpeg":
			case "image/pjpeg":
				return "jpg";
				break;
			case "image/png":
				return "png";
				break;	
			default:
				return null;
				break;
		}			
	}
	/**
	 * Get the image width
	 *
	 * @access public
	 * @return int
	 */
	public function getWidth() {
		return (int)$this->width;
	}
	/**
	 * Get the image height
	 *
	 * @access public
	 * @return int
	 */
	public function getHeight() {
		return (int)$this->height;
	}
	/**
	 * Get the image attributes
	 *
	 * @access public
	 * @return array
	 */
	public function getImageAttributes() {
		return $this->attr;
	}
	/**
	 * Create a new image resourse
	 *
	 * @access protected
	 * @return void
	 */
	protected function createNewImage() {
		switch($this->mime)	{
			case "image/jpeg":
				$this->imgPt = imagecreatefromjpeg($this->path);
				break;
			case "image/gif":
				$this->imgPt = imagecreatefromgif($this->path);
				break;
			case "image/png":
				$this->imgPt = imagecreatefrompng($this->path);
				break;
			default:
				throw new Exception("Invalid Image!");
				break;
		}
	}
	/**
	 * Return the proportional height from the width image
	 *
	 * @param int height
	 * @access protected
	 * @return int
	 */
	public function getHeightFromWidth($width = 250) {
		$newHeight = ( $this->getHeight() * $width ) / $this->getWidth();
		return round( $newHeight );
	}
	/**
	 * Return the proportional width from the height image
	 *
	 * @param int height
	 * @access protected
	 * @return int
	 */
	public function getWidthFromHeight($Height = 250) {
		$newWidth = ( $this->getWidth() * $Height ) / $this->getHeight();
		return round( $newWidth );
	}
	/**
	 * Resize the image and save it in $newPath (es. 'images/photo.jpg')  as type $saveAs: jpg, gif or png
	 *
	 * @param int height
	 * @param int width
	 * @param string newPath
	 * @param string saveAs
	 * @access public
	 * @return void
	 */
	public function resize($width = 250, $height = 250, $newPath = "self", $saveAs = "jpeg" ) {
		if ($newPath == "self") {
			$path = $this->path;
		}
		else {
			$path = $newPath;
		}
		$this->createNewImage();
		$newImagePt = imagecreatetruecolor($width, $height);
		imagecopyresampled($newImagePt, $this->imgPt, 0, 0, 0, 0, $width, $height,$this->width, $this->height);
		switch ($saveAs) {
			case "jpg":
			case "jpeg":
				imagejpeg($newImagePt, $path);
				break;
			case "png":
				imagepng($newImagePt, $path);
				break;
			case "gif":
				imagegif($newImagePt, $path);
				break;
			default:
				throw new Exception("Invalid Format!");
				break;
		}

		$this->setNewPath($this->path);
	}
	/**
	 * Resize the image proportionaly by Height
	 *
	 * @param int height
	 * @param string newPath
	 * @param string saveAs
	 * @access public
	 * @return void
	 */
	public function resizeByHeight($height = 250, $newPath = "self", $saveAs = "jpeg") {
		$width = $this->getWidthFromHeight($height);
		$this->resize($width, $height, $newPath, $saveAs);
	}
	/**
	 * Resize the image proportionaly by Width
	 *
	 * @param int width
	 * @param string newPath
	 * @param string saveAs
	 * @access public
	 * @return void
	 */
	public function resizeByWidth($width = 250, $newPath = "self", $saveAs = "jpeg") {
		$height = $this->getHeightFromWidth($width);
		$this->resize($width, $height, $newPath, $saveAs);
	}
}
?>