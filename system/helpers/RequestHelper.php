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

}
?>
