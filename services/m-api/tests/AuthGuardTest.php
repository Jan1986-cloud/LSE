<?php

declare(strict_types=1);

namespace LSE\Services\MApi\Tests;

use LSE\Services\MApi\AuthGuard;
use LSE\Services\MApi\Exceptions\ForbiddenException;
use LSE\Services\MApi\Exceptions\UnauthorizedException;
use LSE\Services\MApi\UserAuthService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AuthGuardTest extends TestCase
{
    /** @var UserAuthService&MockObject */
    private UserAuthService $authService;

    protected function setUp(): void
    {
        /** @var UserAuthService&MockObject $mock */
        $mock = $this->createMock(UserAuthService::class);
        $this->authService = $mock;
    }

    public function testMissingAuthorizationHeaderThrowsUnauthorized(): void
    {
        $guard = new AuthGuard($this->authService);

        $this->expectException(UnauthorizedException::class);
        $guard->requireUser(null);
    }

    public function testMalformedAuthorizationHeaderThrowsUnauthorized(): void
    {
        $guard = new AuthGuard($this->authService);

        $this->expectException(UnauthorizedException::class);
        $guard->requireUser('Token abc');
    }

    public function testInvalidApiKeyThrowsForbidden(): void
    {
        $this->authService
            ->expects(self::once())
            ->method('authenticateApiKey')
            ->with('invalid-key')
            ->willThrowException(new \RuntimeException('Invalid key'));

        $guard = new AuthGuard($this->authService);

        $this->expectException(ForbiddenException::class);
        $guard->requireUser('Bearer invalid-key');
    }

    public function testValidApiKeyReturnsContext(): void
    {
        $expected = ['user_id' => 123, 'email' => 'user@example.com', 'api_key_id' => 456];
        $this->authService
            ->expects(self::once())
            ->method('authenticateApiKey')
            ->with('valid-key')
            ->willReturn($expected);

        $guard = new AuthGuard($this->authService);
        $result = $guard->requireUser('Bearer valid-key');

        self::assertSame($expected, $result);
    }
}
