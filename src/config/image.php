<?php

return array(
    'library'     => 'gd',
    'upload_dir'  => 'uploads',
    'assets_upload_path' => 'storage/app/uploads',
    'quality'     => 85,
    'thumb_extension'=>'jpg',
    'dimensions'  => [
        ['50','50',true, 85, 'thumbnail'],
        ['240','180',false, 85, 'small'],
        ['640','480',false, 85, 'medium'],
        ['800','600',false, 85, 'large']
    ]
);