<?php

namespace sgdot\helpers;

use Yii;
use WideImage\WideImage;
use yii\base\Component;

class Thumbnail extends Component {

    public $img;
    public $width;
    public $height;
    public $noImage = 'images/noimage.jpg';
    public $thumb_dir = '@webroot/thumbs';
    public $time_old_thumbs = 3000000;
    public $forceThumb = false;
    public $water = false;
    public $fit = 'outside';
    public $ignoreFiles = [
        '.',
        '..',
        '.gitignore',
    ];
    public $watermarkFile = '@webroot/files/watermark.png';

    /**
     * Top position of the overlay, smart coordinate
     * @var mixed
     */
    public $watermarkTop = 'bottom-10';

    /**
     * Left position of the overlay, smart coordinate
     * @var mixed
     */
    public $watermarkLeft = 'right-10';

    /**
     * The opacity of the overlay
     * @var int
     */
    public $watermarkOpacity = 100;

    public function imgpath($img) {
        if (empty($img)) {
            $img = $this->noImage;
        }
        if ($this->isUrl($img)) {
            return $img;
        }

        $webroot = Yii::getAlias('@webroot');

        $image = str_replace('../', '/', $img);
        if (!strpos($image, $webroot)) {
            rtrim($image, '/');
            $image = $webroot . "/" . $img;
        }

        if (!is_file($image)) {
            return false;
        }
        return $image;
    }

    public static function thumb($img, $width, $height, $options = []) {
        $thumb = Yii::createObject(self::className(), [$img, $width, $height, $options]);
        return $thumb->getThumb();
    }

    /**
     * Create a thumbnail of an image and returns relative path in webroot
     *
     * @param string $img
     * @param array $options
     */
    protected function innerThumb($thumbName, $img) {
        $thumbDir = Yii::getAlias($this->thumb_dir);
        $thumbFullname = $thumbDir . "/" . $thumbName;
        if ($this->forceThumb || !file_exists($thumbFullname) || $this->isUrl($img) || filemtime($thumbFullname) < filemtime($img)) {
            self::deleteOldThumbs();
            try {
                $new = $this->generateImage($img);
                $new->saveToFile($thumbFullname);
            } catch (Exception $e) {
                return false;
            }
        }

        $webPath = str_replace(Yii::getAlias('@webroot'), '', $thumbFullname);

        return $webPath;
    }

    public function deleteOldThumbs() {
        $thumb_dir = Yii::getAlias($this->thumb_dir);
        $thumb_list = scandir($thumb_dir);
        $i = 0;
        foreach ($thumb_list as $filename) {
            $full_filename = $thumb_dir . '/' . $filename;
            if (!in_array($filename, $this->ignoreFiles) && (time() - filemtime($full_filename)) > $this->time_old_thumbs) {
                unlink($full_filename);
                $i++;
            }

            if ($i == $this->time_old_thumbs) {
                break;
            }
        }
    }

    public function getExtension($path) {

        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public function isUrl($img) {
        return strpos($img, 'http') === 0;
    }

    public function getThumb() {
        if ($this->width == 0 || $this->height == 0) {
            return $this->img;
        }
        $img = $this->imgpath($this->img);
        if ($img === false) {
            return false;
        }
        $extension = $this->getExtension($img);
        $thumbName = md5($img) . '_' . $this->width . '_' . $this->height . '.' . $extension;
        return $this->innerThumb($thumbName, $img);
    }

    public function __construct($img, $width, $height, $config = array()) {
        $this->img = $img;
        $this->width = $width;
        $this->height = $height;
        return parent::__construct($config);
    }

    public function generateImage($img) {
        $image = WideImage::load($img);
        $new = $image->resize($this->width, $this->height, $this->fit);
        // Если высота или ширина равны null, то не обрезать изображение
        if ($this->width !== null && $this->height !== null) {
            $new = $new->crop('center', 'center', $this->width, $this->height);
        }
        if ($this->water == true) {
            $watermark = WideImage::load(Yii::getAlias($this->watermarkFile));
            $new = $new->merge($watermark, $this->watermarkLeft, $this->watermarkTop, $this->watermarkOpacity);
        }
        return $new;
    }

}
