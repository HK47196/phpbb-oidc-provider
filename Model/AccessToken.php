<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Model;


use HK47196\OIDCProvider\ValueObject\Scope;

class AccessToken implements AccessTokenInterface
{
	private string $identifier;
	private \DateTimeInterface $expiry;
	private ?string $userIdentifier;
	private ClientInterface $client;

	/**
	 * @var list<Scope>
	 */
	private array $scopes;
	private bool $revoked;

	/**
	 * @param bool $revoked
	 * @param list<Scope> $scopes
	 *
	 * @psalm-mutation-free
	 */
	public function __construct(
		string             $identifier,
		\DateTimeInterface $expiry,
		ClientInterface    $client,
		?string            $userIdentifier,
		array              $scopes,
		bool               $revoked
	)
	{
		$this->identifier = $identifier;
		$this->expiry = $expiry;
		$this->client = $client;
		$this->userIdentifier = $userIdentifier;
		$this->scopes = $scopes;
		$this->revoked = $revoked;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function __toString(): string
	{
		return $this->getIdentifier();
	}

	/**
	 * @psalm-mutation-free
	 */
	public function getIdentifier(): string
	{
		return $this->identifier;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function getExpiry(): \DateTimeInterface
	{
		return $this->expiry;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function getUserIdentifier(): ?string
	{
		return $this->userIdentifier;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function getClient(): ClientInterface
	{
		return $this->client;
	}

	/**
	 * @return list<Scope>
	 *
	 * @psalm-mutation-free
	 */
	public function getScopes(): array
	{
		return $this->scopes;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function isRevoked(): bool
	{
		return $this->revoked;
	}

	public function revoke(): AccessTokenInterface
	{
		$this->revoked = true;

		return $this;
	}
}