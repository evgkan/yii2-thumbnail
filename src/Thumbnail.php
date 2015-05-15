<?php

namespace sgdot\helpers;

use Yii;
use WideImage\WideImage;

class Thumbnail {

    const NOIMAGE = 'images/noimage.jpg';
    const ROUNDNOIMAGE = 'images/rnoimage.png';
    const DEFAULT_PRESET = 'resize';
    const THUMB_DIR = 'thumbs';
    const COUNT_OLD_THUMBS = 5;
    const TIME_OLD_THUMBS = 3000000;

    public static $ignoreFiles = [
        '.',
        '..',
        '.gitignore',
    ];

    public static function imgpath($img, $noImage = self::NOIMAGE) {
        if ($img == "" || $img == NULL) {
            return $noImage;
        }
        if (self::isUrl($img)) {
            return $img;
        }

        $webroot = Yii::getAlias('@webroot');

        $img = str_replace('../', '/', $img);
        if (!strpos($img, $webroot))
            if (substr($img, 0, 1) == '/')
                $img = $webroot . $img;
            else
                $img = $webroot . "/" . $img;

        if (!file_exists($img)) {
            return false;
        }
        return $img;
    }

    public static function thumb($img, $width, $height, $forceThumb = false, $water = false, $fit = 'outside') {
        if ($width == 0 || $height == 0)
            return $img;
        $img = self::imgpath($img);
        if ($img === false)
            return false;

        $pathInfo = self::pathinfo_utf($img);
        $thumbName = md5($img) . '_' . $width . '_' . $height . '.' . $pathInfo['extension'];

        return self::innerThumb($thumbName, $img, $width, $height, $forceThumb, $water, $fit);
    }

    public static function thumbAnyWay($img, $width, $height, $forceThumb = false, $water = false, $fit = 'outside') {
        if ($width == 0 || $height == 0)
            return $img;
        $img = self::imgpath($img);
        if ($img === false)
            $img = self::NOIMAGE;

        $pathInfo = self::pathinfo_utf($img);
        $thumbName = md5($img) . '_' . $width . '_' . $height . '.' . $pathInfo['extension'];

        return self::innerThumb($thumbName, $img, $width, $height, $forceThumb, $water, $fit);
    }

    /**
     * Create a thumbnail of an image and returns relative path in webroot
     *
     * @param string $img
     * @param array $options
     */
    protected static function innerThumb($thumbName, $img, $width, $height, $forceThumb = false, $water = false, $fit = 'fill') {
        $thumbDir = Yii::getAlias('@webroot') . '/' . self::THUMB_DIR;
        $thumbFullname = $thumbDir . "/" . $thumbName;

        if ($forceThumb || !file_exists($thumbFullname) || self::isUrl($img) || filemtime($thumbFullname) < filemtime($img)) {
            self::deleteOldThumbs();
            try {
                $image = WideImage::load($img);
                $image = $image->resize($width, $height, $fit);
                // Если высота или ширина равны null, то не обрезать изображение
                if ($width !== null && $height !== null)
                    $image = $image->crop('center', 'center', $width, $height);

                if ($water == true) {
                    $watermark = WideImage::load(Yii::getAlias('@webroot') . '/files/watermark.png');
                    $image = $image->merge($watermark, 'right-10', 'bottom-10');
                }
                /*
                  $canvas = $image->getCanvas();
                  $canvas->useFont(Yii::getPathOfAlias('application.fonts') . '/LiberationSerif-Regular.ttf','16');
                  $canvas->writeText('left+5', 'bottom-5', Yii::app()->request->serverName);
                 */
                $image->saveToFile($thumbFullname);
            } catch (Exception $e) {
                return false;
            }
        }

        $webPath = str_replace(Yii::getAlias('@webroot'), '', $thumbFullname);

        return $webPath;
    }

    public static function thumbNoImage($width, $height, $forceThumb = false, $water = false) {

        return self::thumb(self::NOIMAGE, $width, $height, $forceThumb, $water);
    }

    public static function deleteOldThumbs() {
        $thumb_dir = Yii::getAlias('@webroot') . '/' . self::THUMB_DIR;
        $thumb_list = scandir($thumb_dir);
        $i = 0;
        foreach ($thumb_list as $filename) {
            $full_filename = $thumb_dir . '/' . $filename;
            if (!in_array($filename, self::$ignoreFiles) && (time() - filemtime($full_filename)) > self::TIME_OLD_THUMBS) {
                unlink($full_filename);
                $i++;
            }

            if ($i == self::TIME_OLD_THUMBS)
                break;
        }
    }

    public static function pathinfo_utf($path) {
        if (strpos($path, '/') !== false) {
            $arr = explode('/', $path);
            $basename = end($arr);
        } elseif (strpos($path, '\\') !== false) {
            $arr = explode('\\', $path);
            $basename = end($arr);
        } else
            return false;
        if (empty($basename))
            return false;

        $dirname = substr($path, 0, strlen($path) - strlen($basename) - 1);

        if (strpos($basename, '.') !== false) {
            $arr = explode('.', $path);
            $extension = end($arr);
            $filename = substr($basename, 0, strlen($basename) - strlen($extension) - 1);
        } else {
            $extension = '';
            $filename = $basename;
        }

        return array(
            'dirname' => $dirname,
            'basename' => $basename,
            'extension' => $extension,
            'filename' => $filename
        );
    }

    public static function isImage($filename) {
        $filename = self::imgpath($filename);
        $is = @getimagesize($filename);
        if (!$is) {
            return false;
        } elseif (!in_array($is[2], array(1, 2, 3))) {
            return false;
        } else {
            return true;
        }
    }

    public static function watermark($img, $water = true) {

        $img = self::imgpath($img);
        if ($img === false)
            return false;

        $pathInfo = self::pathinfo_utf($img);
        $thumbName = md5($img) . '_water.' . $pathInfo['extension'];

        // return self::innerThumb($thumbName, $img, $width, $height, $forceThumb, $water, $fit);
        $thumbDir = Yii::getAlias('@webroot') . '/' . self::THUMB_DIR;
        $thumbFullname = $thumbDir . "/" . $thumbName;

        if (!file_exists($thumbFullname) || filemtime($thumbFullname) < filemtime($img)) {
            self::deleteOldThumbs();
            try {
                $image = WideImage::load($img);
                $watermark = WideImage::load(Yii::getAlias('@webroot') . '/files/watermark.png');
                $image = $image->merge($watermark, 'center', 'center');
                /*
                  $canvas = $image->getCanvas();
                  $canvas->useFont(Yii::getPathOfAlias('application.fonts') . '/LiberationSerif-Regular.ttf','16');
                  $canvas->writeText('left+5', 'bottom-5', Yii::app()->request->serverName);
                 */
                $image->saveToFile($thumbFullname);
            } catch (Exception $e) {
                return false;
            }
        }

        $webPath = str_replace(Yii::getAlias('@webroot'), '', $thumbFullname);

        return $webPath;
    }

    public static function isUrl($img) {
        return strpos($img, 'http') === 0;
    }

}
