<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Manager;

use HK47196\OIDCProvider\Model\AccessTokenInterface;

interface AccessTokenManagerInterface
{
    public function find(string $identifier): ?AccessTokenInterface;

    public function save(AccessTokenInterface $accessToken): void;

    public function clearExpired(): int;
}