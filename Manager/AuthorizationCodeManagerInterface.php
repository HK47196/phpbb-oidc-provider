<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Manager;

use HK47196\OIDCProvider\Model\AuthorizationCodeInterface;

interface AuthorizationCodeManagerInterface
{
	public function find(string $identifier): ?AuthorizationCodeInterface;

	public function save(AuthorizationCodeInterface $authCode): void;

	public function clearExpired(): int;
}