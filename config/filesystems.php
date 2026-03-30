<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default'      => env('FILESYSTEM_DISK', 'local'),

    // Disk dùng để lưu nội dung chương (chapter content files)
    'chapter_disk' => env('CHAPTER_DISK', 'chapters'),

    // Disk dùng để lưu ảnh cover
    'cover_disk'   => env('COVER_DISK', 'covers'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'serve'  => true,
            'throw'  => false,
            'report' => false,
        ],

        'public'   => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw'      => false,
            'report'     => false,
        ],

        // Disk riêng để lưu nội dung chương
        'chapters' => [
            'driver' => 'local',
            'root'   => env('CHAPTER_STORAGE_PATH', storage_path('app/chapters')),
            'throw'  => false,
            'report' => false,
        ],

        // Disk S3 chuyên dụng để lưu nội dung chương (tuỳ chọn thay cho 'chapters' local)
        'chapters_s3' => [
            'driver'                  => 's3',
            // Nếu CHAPTER_S3_* không có, fallback về AWS_* hiện có
            'key'                     => env('CHAPTER_S3_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret'                  => env('CHAPTER_S3_SECRET', env('AWS_SECRET_ACCESS_KEY')),
            'region'                  => env('CHAPTER_S3_REGION', env('AWS_DEFAULT_REGION')),
            'bucket'                  => env('CHAPTER_S3_BUCKET', env('AWS_BUCKET')),
            'url'                     => env('CHAPTER_S3_URL', env('AWS_URL')),
            'endpoint'                => env('CHAPTER_S3_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('CHAPTER_S3_PATH_STYLE', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'visibility'              => env('CHAPTER_S3_VISIBILITY', 'private'),
            'throw'                   => false,
            'report'                  => false,
        ],

        // Disk riêng để lưu ảnh cover
        'covers' => [
            'driver' => 'local',
            'root'   => env('COVER_STORAGE_PATH', storage_path('app/covers')),
            'throw'  => false,
            'report' => false,
        ],

        // Disk S3 chuyên dụng để lưu ảnh cover (tuỳ chọn thay cho 'covers' local)
        'covers_s3' => [
            'driver'                  => 's3',
            'key'                     => env('COVER_S3_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret'                  => env('COVER_S3_SECRET', env('AWS_SECRET_ACCESS_KEY')),
            'region'                  => env('COVER_S3_REGION', env('AWS_DEFAULT_REGION')),
            'bucket'                  => env('COVER_S3_BUCKET', env('AWS_BUCKET')),
            'url'                     => env('COVER_S3_URL', env('AWS_URL')),
            'endpoint'                => env('COVER_S3_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('COVER_S3_PATH_STYLE', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'visibility'              => env('COVER_S3_VISIBILITY', 'public'),
            'throw'                   => false,
            'report'                  => false,
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION'),
            'bucket'                  => env('AWS_BUCKET'),
            'url'                     => env('AWS_URL'),
            'endpoint'                => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
            'report'                  => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
