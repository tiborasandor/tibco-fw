<?php
declare(strict_types=1);

namespace system\helpers;

class RequestHelper {

    /** Client IP: CF-Connecting-IP, X-Forwarded-For, then REMOTE_ADDR. */
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

    /** Request scheme: CF-Visitor, X-Forwarded-Proto, then the URI. */
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

    /** Full public URL of the current request. */
    public function getCurrentUrl(\Psr\Http\Message\ServerRequestInterface $request): string {
        $uri = $request->getUri();
        $query = $uri->getQuery();

        return $this->getScheme($request).'://'.$uri->getAuthority().$uri->getPath().($query !== '' ? '?'.$query : '');
    }

    /** Scheme + host of the current request, without path. */
    public function getBaseUrl(\Psr\Http\Message\ServerRequestInterface $request): string {
        return $this->getScheme($request).'://'.$request->getUri()->getAuthority();
    }

}
?>
