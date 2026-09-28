<?php
declare(strict_types=1);

namespace system\helpers;

class RequestHelper {

    /**
     * Tries to determine the real client IP.
     * Checks the CF-Connecting-IP header first (for deployments behind Cloudflare),
     * then X-Forwarded-For, and finally falls back to the raw REMOTE_ADDR.
     */
    public function getClientIp(\Psr\Http\Message\ServerRequestInterface $request): string {
        $ip = trim($request->getHeaderLine('CF-Connecting-IP'));

        if ($ip === '') {
            $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
            if ($forwardedFor !== '') {
                $ip = trim(explode(',', $forwardedFor)[0]);
            }
        }

        if ($ip === '') {
            $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');
        }

        return $ip !== '' ? $ip : 'unknown';
    }

    /**
     * Tries to determine the real request scheme (http/https).
     * Behind Cloudflare the edge-to-origin connection can be plain http, so
     * X-Forwarded-Proto isn't reliable there. The visitor's actual scheme is in
     * Cloudflare's CF-Visitor header (JSON: {"scheme":"https"}), which is checked
     * first, then X-Forwarded-Proto, and finally the raw URI.
     */
    public function getScheme(\Psr\Http\Message\ServerRequestInterface $request): string {
        $cfVisitor = trim($request->getHeaderLine('CF-Visitor'));
        if ($cfVisitor !== '') {
            $decoded = json_decode($cfVisitor, true);
            if (!empty($decoded['scheme'])) {
                return $decoded['scheme'];
            }
        }

        $forwardedProto = trim($request->getHeaderLine('X-Forwarded-Proto'));
        if ($forwardedProto !== '') {
            return $forwardedProto;
        }

        return $request->getUri()->getScheme();
    }

    /**
     * The full public URL of the current request (with the scheme correction above).
     */
    public function getCurrentUrl(\Psr\Http\Message\ServerRequestInterface $request): string {
        $uri = $request->getUri();
        $query = $uri->getQuery();

        return $this->getScheme($request).'://'.$uri->getAuthority().$uri->getPath().($query !== '' ? '?'.$query : '');
    }

    /**
     * Scheme + host of the current request, without path - e.g. for building
     * absolute asset URLs (og:image, RSS links).
     */
    public function getBaseUrl(\Psr\Http\Message\ServerRequestInterface $request): string {
        return $this->getScheme($request).'://'.$request->getUri()->getAuthority();
    }

}
?>
