<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Logout LogViewer (Trick 401 để xoá cache Basic Auth của Trình duyệt)
Route::get('/logout-log', function () {
    return response('Đã đăng xuất LogViewer. Trình duyệt đã xóa bộ nhớ tạm.', 401, [
        'WWW-Authenticate' => 'Basic realm="LogViewer"'
    ]);
});
