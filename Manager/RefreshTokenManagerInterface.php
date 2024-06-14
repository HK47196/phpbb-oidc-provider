<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Manager;

use HK47196\OIDCProvider\Model\RefreshTokenInterface;

interface RefreshTokenManagerInterface
{
	public function find(string $identifier): ?RefreshTokenInterface;

	public function save(RefreshTokenInterface $refreshToken): void;

	public function clearExpired(): int;
}