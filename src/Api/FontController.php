<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Certificates\Fonts;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/fonts/{name} — one of the certificate's bundled woff2
 * fonts, by an allow-listed name. Served by PHP (with no file extension in
 * the address) so it works whether or not the forum republishes extension
 * assets, and cached for a year: the address carries the renderer's version.
 */
class FontController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $file = Fonts::webFile((string) Arr::get($request->getAttributes(), 'routeParameters.name'));
        if (! $file || ! is_file($file)) {
            return new EmptyResponse(404);
        }

        return new Response(new Stream($file, 'rb'), 200, [
            'Content-Type' => 'font/woff2',
            'Content-Length' => (string) filesize($file),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
