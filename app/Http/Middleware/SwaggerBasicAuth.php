<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SwaggerBasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $USERNAME = env('SWAGGER_USER', 'admin');
        $PASSWORD = env('SWAGGER_PASSWORD', 'secret');


        if (
            !isset($_SERVER['PHP_AUTH_USER']) ||
            $_SERVER['PHP_AUTH_USER'] !== $USERNAME ||
            $_SERVER['PHP_AUTH_PW'] !== $PASSWORD
        ) {
            header('WWW-Authenticate: Basic realm="Swagger API Docs"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Unauthorized';
            exit;
        }

        return $next($request);
    }
}