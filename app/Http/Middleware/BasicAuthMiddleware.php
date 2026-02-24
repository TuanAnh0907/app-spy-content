<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BasicAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Trick để Logout Basic Auth: Nếu truyền param ?logout=1
        if ($request->has('logout')) {
            return response('Đã đăng xuất', 401, ['WWW-Authenticate' => 'Basic realm="LogViewer"']);
        }

        $username = env('LOG_VIEWER_USER', 'admin');
        $password = env('LOG_VIEWER_PASS', 'admin123');

        if ($request->getUser() !== $username || $request->getPassword() !== $password) {
            $headers = ['WWW-Authenticate' => 'Basic realm="LogViewer"'];
            return response('Unauthorized LogViewer Access', 401, $headers);
        }

        return $next($request);
    }
}
