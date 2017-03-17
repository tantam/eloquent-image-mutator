<?php

return array(
    'library'     => 'gd',
    'upload_dir'  => 'public/uploads',
    'assets_upload_path' => 'public/uploads',
    'quality'     => 85,
    'thumb_extension'=>'jpg',
    'dimensions'  => [
        // width, height, crop?, quality, name
        ['640','480',true, 85, 'thumb'],
        ['640','480',false, 85, 'medium'],

    ]
);
