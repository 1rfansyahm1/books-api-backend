<?php // src/Middleware/Cors.php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Cors implements MiddlewareInterface
{
    private array $allowed;

    public function __construct() {
        $list = (string)($_ENV['CORS_ALLOWED_ORIGINS'] ?? '');
        $this->allowed = array_filter(array_map('trim', explode(',', $list)));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface{
        if ($request->getMethod() === 'OPTIONS') {
            $response = new \Slim\Psr7\Response(204);
            // FIX: Pass BOTH $request and $response, in the correct order
            return $this->withCors($request, $response); 
        }

        // FIX: Ask the app to process the request and give us the response
        $response = $handler->handle($request);
        
        // FIX: Pass them in the right order (Request first, Response second)
        return $this->withCors($request, $response);
    }

    private function withCors($req, $res) {
        // $req acts on the Request, $res acts on the Response
        $origin = $req->getHeaderLine('Origin');
        $allow = '*'; 
        $creds = false;
        
        if ($this->allowed && in_array($origin, $this->allowed, true)) {
            $allow = $origin; 
            $creds = true;
        }
        
        $res = $res
            ->withHeader('Access-Control-Allow-Origin', $allow)
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Vary', 'Origin');
            
        if ($creds) {
            $res = $res->withHeader('Access-Control-Allow-Credentials', 'true');
        }
        
        return $res;
    }
}