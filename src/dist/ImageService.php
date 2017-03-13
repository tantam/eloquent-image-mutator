<?php

namespace SahusoftCom\EloquentImageMutator\Dist;

use Exception;

class ImageService
{

	public $imagine=null;

	public static function getImagineObject() 
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

	public static function getImageObject($jsonData=null)
	{
	 	return ImageFieldLocal::fromJson($jsonData);
	}

	public static function copyImage($key, $value)
	{
		$fileObject = new \SplFileInfo(public_path().$value->original->url);		
		$destination = ImageService::getANewFileName($fileObject->getExtension());
		$destinationDirectory = ImageService::getUploadStoragePath().'/'.dirname($destination);
		if(!\File::isDirectory($destinationDirectory))
		    \File::makeDirectory($destinationDirectory, 0777, true);

		copy(public_path().$value->original->url, $destinationDirectory.'/'.basename($destination));
		$urn = ImageService::makeFromFile($destination, basename($destination));
		$allTheSizes = ImageService::getAllTheSizes($urn);
		$arrayForDB = [];

		foreach ($allTheSizes as $keyTwo => $value) {
		    $arrayForDB[$keyTwo]['url'] = !empty($value['urn']) ? '/'.\Config::get('image.upload_dir').'/'.$value['urn'] : null;
		    $arrayForDB[$keyTwo]['height'] = !empty($value['height']) ? $value['height'] : null;
		    $arrayForDB[$keyTwo]['width'] = !empty($value['width']) ? $value['width'] : null;
		}
		$arrayForDB['original']['url'] = !empty($urn) ? '/'.\Config::get('image.upload_dir').'/'.$urn : null;

		$imageObj = new ImageFieldLocal($arrayForDB);
		return $imageObj;
	
	}

    /**
     * @param $key
     * @param \Illuminate\Http\UploadedFile | \Symfony\Component\HttpFoundation\File\UploadedFile $value
     * @return \SahusoftCom\EloquentImageMutator\Dist\ImageFieldLocal
     */
	public static function uploadImage($key, $value)
	{
	    $destination = ImageService::getANewFileName($value->getClientOriginalExtension());

//        $finfo = finfo_open(FILEINFO_MIME_TYPE);
//        $file_mime = finfo_file($finfo, $value);
//        finfo_close($finfo);


        $file = \Storage::putFileAs(ImageService::getUploadStoragePath().'/'.dirname($destination),$value, basename($destination),'public');

	    $urn = ImageService::makeFromFile($destination, $value->getClientOriginalName());

	    $allTheSizes = ImageService::getAllTheSizes($urn);
	    $arrayForDB = [];


	    foreach ($allTheSizes as $keyTwo => $value) {
	        $arrayForDB[$keyTwo]['url'] = !empty($value['urn']) ? '/'.\Config::get('image.upload_dir').'/'.$value['urn'] : null;
	        $arrayForDB[$keyTwo]['height'] = !empty($value['height']) ? $value['height'] : null;
	        $arrayForDB[$keyTwo]['width'] = !empty($value['width']) ? $value['width'] : null;
	    }
	    $arrayForDB['original']['url'] = !empty($urn) ? '/'.\Config::get('image.upload_dir').'/'.$urn : null;

	    $imageObj = new ImageFieldLocal($arrayForDB);
	    return $imageObj;
	}

	public static function downloadImage($key, $value)
	{
	    $fileObject = new \SplFileInfo($value);		

		$destination = ImageService::getANewFileName($fileObject->getExtension());
		$destinationDirectory = ImageService::getUploadStoragePath().'/'.dirname($destination);
		if(!\File::isDirectory($destinationDirectory))
		    \File::makeDirectory($destinationDirectory, 0777, true);

		file_put_contents($destinationDirectory.'/'.basename($destination), file_get_contents($value));
		$urn = ImageService::makeFromFile($destination, basename($destination));
		$allTheSizes = ImageService::getAllTheSizes($urn);
		$arrayForDB = [];

		foreach ($allTheSizes as $keyTwo => $value) {
		    $arrayForDB[$keyTwo]['url'] = !empty($value['urn']) ? '/'.\Config::get('image.upload_dir').'/'.$value['urn'] : null;
		    $arrayForDB[$keyTwo]['height'] = !empty($value['height']) ? $value['height'] : null;
		    $arrayForDB[$keyTwo]['width'] = !empty($value['width']) ? $value['width'] : null;
		}
		$arrayForDB['original']['url'] = !empty($urn) ? '/'.\Config::get('image.upload_dir').'/'.$urn : null;

		$imageObj = new ImageFieldLocal($arrayForDB);
		return $imageObj;
	}

	public static function getANewFolder()
	{
	    return  'user/'.date('Y/m/d');
	}

	public static function getUploadStoragePath()
	{
	    return \Config::get('image.assets_upload_path');
	}

	public static function getANewFileName($ext)
	{
	    return self::getANewFolder().'/'.str_random(16).'.'.$ext;
	}

	public static function makeFromFile($urn, $original_name = '', $title='')
	{
//	    if(!$original_name)
//	        $original_name = basename($urn);

	    $absFile = self::getUploadStoragePath().'/'.$urn;

	    $tempValue = 0;

	    self::createDimensions($absFile);

	    return $urn;
	}

	public static function createDimensions($url, $dimensions = array())
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

	        $img = self::resize($url, $dest, $width, $height, $crop, $quality);

	    }
	}

	public static function getAllTheSizes($url, $getImageSize = true)
	{
	    $dimensions = array();

	    $defaultDimensions = \Config::get('image.dimensions');
	 
	    if (is_array($defaultDimensions)){
            $dimensions = array_merge($defaultDimensions, $dimensions);
        }

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
	        $sourceDirPath  = self::getUploadStoragePath().'/'.$info['dirname'];
	        $sourceFilePath = $sourceDirPath.'/'.$fileName;
	        $targetDirName  = $width.'x'.$height.($crop ? '_crop' : '');
	        $targetDirPath  = $sourceDirPath.'/'.$targetDirName.'/';
	        $targetFilePath = $targetDirPath.$fileName;
	        //$targetUrl      = asset($info['dirname'].'/'.$targetDirName.'/'.$fileName);

	        $file = self::getUploadStoragePath().'/'.$info['dirname'].'/'.$targetDirName.'/'.$fileName;
	        
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

	public static function crop($x, $y, $width, $height)
	{
	    $ext = \File::extension(self::urn);
	    $destinationImage = self::getANewFileName($ext,true);

	    $destinationAbsImage = self::getUploadStoragePath().'/'.$destinationImage;

	    $sourceAbsImage = self::getUploadStoragePath().'/'.self::urn;
	        self::imageCrop($sourceAbsImage,$destinationAbsImage,$x,$y,$width,$height);

	    if(!\File::exists($destinationAbsImage)) {
	        throw new Exception("Image not cropped and saved");
	    }

	    $photo = self::makeFromFile($destinationImage,true,'','Image File');

	    return $photo;
	}

	public static function imageCrop($source, $destination, $x=0, $y=0, $width=1, $height=1, $quality=90)
	{
        if(!\Storage::exists($source)) {
            throw new Exception("[IMAGE SERVICE] Source file does not exist");
        }

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
	        $imagine = self::getImagineObject();

	    try {

	        $rs = $imagine->load(\Storage::get($source))
	                ->crop($point,$box)
                ->get(\Config::get('image.thumb_extension','png'),array('quality' => $quality));

            \Storage::put($destination,$rs,'public');

	    } catch (\Exception $e) {

	        \Log::error('[IMAGE SERVICE] Image crop Failed to crop image  [' . $e->getMessage() . ']');

	    }

	    return $destination;            
	}

	public static function resize($source, $destination, $width = 100, $height = null, $crop = false, $quality = 90)
	{
	    if(!\Storage::exists($source)){
            throw new Exception("[IMAGE SERVICE] Source file does not exist");
        }

	    $destinationFolder = dirname($destination);

	    if(!\Storage::exists($destinationFolder))
	        \Storage::makeDirectory($destinationFolder);

	    // Set the size
	    $size = new \Imagine\Image\Box($width, $height);

	    // Now the mode
	    $mode = $crop ? \Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND : \Imagine\Image\ImageInterface::THUMBNAIL_INSET;
	    
	    if(empty($imagine)){
            $imagine = self::getImagineObject();
        }

	    try {

	        $rs = $imagine->load(\Storage::get($source))
	            ->thumbnail($size, $mode)
                ->get(\Config::get('image.thumb_extension','png'),array('quality' => $quality));

            \Storage::put($destination,$rs,'public');

	    } catch (\Exception $e) {

	        \Log::error('[IMAGE SERVICE] Image resize Failed to crop image  [' . $e->getMessage() . ']');

	    }

	    return $destination;
	}

	public static function delete($fullPath)
	{
		return unlink($fullPath);
	}
}