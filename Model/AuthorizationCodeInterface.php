<?php
declare(strict_types=1);


namespace HK47196\OIDCProvider\Model;

use HK47196\OIDCProvider\ValueObject\Scope;

interface AuthorizationCodeInterface
{
	public function __toString(): string;

	public function getIdentifier(): string;

	public function getExpiryDateTime(): \DateTimeInterface;

	public function getUserIdentifier(): ?string;

	public function getClient(): ClientInterface;

	/**
	 * @return list<Scope>
	 */
	public function getScopes(): array;

	public function isRevoked(): bool;

	public function revoke(): self;
}