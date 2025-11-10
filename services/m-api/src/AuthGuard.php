<?php

declare(strict_types=1);

namespace LSE\Services\MApi;

use LSE\Services\MApi\Exceptions\ForbiddenException;
use LSE\Services\MApi\Exceptions\UnauthorizedException;
use RuntimeException;

use function preg_match;

class AuthGuard
{
    private UserAuthService $authService;

    public function __construct(UserAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @return array{user_id:int,email:string,api_key_id:int}
     */
    public function requireUser(?string $authorizationHeader): array
    {
        if ($authorizationHeader === null || $authorizationHeader === '') {
            throw new UnauthorizedException('Missing Authorization header.');
        }

        if (preg_match('/^Bearer\s+(\S+)$/i', $authorizationHeader, $matches) !== 1) {
            throw new UnauthorizedException('Authorization header is malformed.');
        }

        $apiKey = $matches[1];

        try {
            return $this->authService->authenticateApiKey($apiKey);
        } catch (RuntimeException $e) {
            throw new ForbiddenException('Invalid API key.', 0, $e);
        }
    }
}
