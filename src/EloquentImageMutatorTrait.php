<?php 

namespace SahusoftCom\EloquentImageMutator;

trait EloquentImageMutatorTrait 
{

    public $imagine=null;

    protected function getImagineObject() 
    {
        $library = \Config::get('image.library', 'gd');

        if ($library == 'imagick')
            $imagine = new \Imagine\Imagick\Imagine();
        else if ($library == 'gmagick')
            $imagine = new \Imagine\Gmagick\Imagine();
        else if ($library == 'gd')
            $imagine = new \Imagine\Gd\Imagine();
        else
            $imagine = new \Imagine\Gd\Imagine();

        return $imagine;
    }

    public function getAttributeValue($key)
    {
        $value = parent::getAttributeValue($key);

        if(in_array($key, $this->image_fields))
        {
            $value = $this->retrievePhotoFieldValue($key, $value);
        }
        
        return $value;
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->image_fields) && $value)
        {
            return $this->setPhotoAttribute($key, $value);
        }

        return parent::setAttribute($key, $value);
    }

    public function retrievePhotoFieldValue($key, $value)
    {
        if(empty($value)) {

            $dimensions = \Config::get('image.dimensions');

            $stdClass = new \stdClass();
            
            foreach($dimensions as $key => $item) {

                $stdClass->$item['4'] = null;
            }
        } else {

            $result = $this->getAllTheSizes($value);
            $stdClass = new \stdClass();
            
            foreach($result as $key => &$item) {

                $stdClass->$key = asset('/uploads/'.$item['urn']);
            }

        }
        
        return $stdClass;
    }

    public function getANewFolder()
    {
        return  'user/'.date('Y/m/d/i/s');
    }

    public function getUploadStoragePath()
    {
        return base_path().'/'.\Config::get('image.assets_upload_path');
    }

    public function getANewFileName($ext)
    {
        return $this->getANewFolder().'/'.str_random(16).'.'.$ext;
    }

    public function setPhotoAttribute($key, $value)
    {

        $destination = $this->getANewFileName($value->getClientOriginalExtension());

        $value->move($this->getUploadStoragePath().'/'.dirname($destination), basename($destination));

        $urn = $this->makeFromFile($destination, $value->getClientOriginalName());
        
        $this->attributes[$key] = ($urn === null) ? null : $urn;
    }

    public function makeFromFile($urn, $original_name = '', $title='')
    {
        if(!$original_name)
            $original_name = basename($urn);

        $absFile = $this->getUploadStoragePath().'/'.$urn;

        $tempValue = 0;

        $this->createDimensions($absFile);

        return $urn;
    }

    public function createDimensions($url, $dimensions = array())
    {
        $defaultDimensions = \Config::get('image.dimensions');

        if (is_array($defaultDimensions)) $dimensions = array_merge($defaultDimensions, $dimensions);

        foreach ($dimensions as $dimension)
        {
            $width   = (int) $dimension[0];
            $height  = isset($dimension[1]) ?  (int) $dimension[1] : $width;
            $crop    = isset($dimension[2]) ? (bool) $dimension[2] : false;
            $quality = isset($dimension[3]) ?  (int) $dimension[3] : \Config::get('image.quality');

            $dest = dirname($url).'/'.$width.'x'.$height.($crop?'_crop':'').'/'.basename($url);

            $img = $this->resize($url, $dest, $width, $height, $crop, $quality);

        }
    }

    public function getAllTheSizes($url, $getImageSize = true)
    {
        $dimensions = array();

        $defaultDimensions = \Config::get('image.dimensions');
     
        if (is_array($defaultDimensions))
            $dimensions = array_merge($defaultDimensions, $dimensions);

        $ret = array();

        foreach ($dimensions as $dimension) {
            // Get dimmensions and quality
            $width   = (int) $dimension[0];
            $height  = isset($dimension[1]) ?  (int) $dimension[1] : $width;
            $crop    = isset($dimension[2]) ? (bool) $dimension[2] : false;
            $quality = isset($dimension[3]) ?  (int) $dimension[3] : \Config::get('image.quality');

            $info = pathinfo($url);

            // Directories and file names
            $fileName       = $info['basename'];
            $sourceDirPath  = $this->getUploadStoragePath().'/'.$info['dirname'];
            $sourceFilePath = $sourceDirPath.'/'.$fileName;
            $targetDirName  = $width.'x'.$height.($crop ? '_crop' : '');
            $targetDirPath  = $sourceDirPath.'/'.$targetDirName.'/';
            $targetFilePath = $targetDirPath.$fileName;
            //$targetUrl      = asset($info['dirname'].'/'.$targetDirName.'/'.$fileName);

            $file = $this->getUploadStoragePath().'/'.$info['dirname'].'/'.$targetDirName.'/'.$fileName;

            if($getImageSize) {
                if(!file_exists($file)) {
                    $width = 0;
                    $height = 0;
                }
                else {
                    
                    list($width, $height) = getimagesize($file);
                }
            }

            $ret[$dimension[4]] = [
                'urn'=> $info['dirname'].'/'.$targetDirName.'/'.$fileName,
                'width' => $width,
                'height' => $height
            ];

        }

        return $ret;
    }

    public function crop($x, $y, $width, $height)
    {
        $ext = \File::extension($this->urn);
        $destinationImage = $this->getANewFileName($ext,true);

        $destinationAbsImage = $this->getUploadStoragePath().'/'.$destinationImage;

        $sourceAbsImage = $this->getUploadStoragePath().'/'.$this->urn;
            $this->imageCrop($sourceAbsImage,$destinationAbsImage,$x,$y,$width,$height);

        if(!\File::exists($destinationAbsImage)) {
            throw new Exception("Image not cropped and saved");
        }

        $photo = $this->makeFromFile($destinationImage,true,'','Image File');

        return $photo;
    }

    public function imageCrop($source, $destination, $x=0, $y=0, $width=1, $height=1, $quality=90)
    {
        if(!\File::exists($source))
            throw new Exception("[IMAGE SERVICE] Source file does not exist");

        $destinationFolder = dirname($destination);

        if(!\File::isDirectory($destinationFolder))
            \File::makeDirectory($destinationFolder, 0777, true);

        list($imgWidth,$imgHeight) = getimagesize($source);

        $cropXPixels = $imgWidth*$x;
        $cropYPixels = $imgHeight*$y;
        $cropWidthPixels = $width * $imgWidth;
        $cropHeightPixels = $height * $imgHeight;

        $point = new \Imagine\Image\Point($cropXPixels,$cropYPixels);
        $box = new \Imagine\Image\Box($cropWidthPixels,$cropHeightPixels);

        if(empty($imagine))
            $imagine = $this->getImagineObject();

        try {

            $imagine->open($source)
                    ->crop($point,$box)
                    ->save($destination, array('quality' => $quality)); 

        } catch (\Exception $e) {

            \Log::error('[IMAGE SERVICE] Image crop Failed to crop image  [' . $e->getMessage() . ']');

        }

        return $destination;            
    }

    public function resize($source, $destination, $width = 100, $height = null, $crop = false, $quality = 90)
    {
        if(!\File::exists($source))
            throw new Exception("[IMAGE SERVICE] Source file does not exist");

        $destinationFolder = dirname($destination);

        if(!\File::isDirectory($destinationFolder))
            \File::makeDirectory($destinationFolder, 0777, true);

        // Set the size
        $size = new \Imagine\Image\Box($width, $height);

        // Now the mode
        $mode = $crop ? \Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND : \Imagine\Image\ImageInterface::THUMBNAIL_INSET;
        
        if(empty($imagine))
            $imagine = $this->getImagineObject();

        try {

            $imagine->open($source)
                ->thumbnail($size, $mode)
                ->save($destination, array('quality' => $quality));
    
        } catch (\Exception $e) {

            \Log::error('[IMAGE SERVICE] Image resize Failed to crop image  [' . $e->getMessage() . ']');

        }

        return $destination;
    }

}