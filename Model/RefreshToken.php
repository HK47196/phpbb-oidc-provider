<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Model;


class RefreshToken implements RefreshTokenInterface
{
	private string $identifier;
	private \DateTimeInterface $expiry;
	private ?AccessTokenInterface $accessToken;
	private bool $revoked;

	/**
	 * @psalm-mutation-free
	 */
	public function __construct(string $identifier, \DateTimeInterface $expiry, ?AccessTokenInterface $accessToken = null, bool $revoked = false)
	{
		$this->identifier = $identifier;
		$this->expiry = $expiry;
		$this->accessToken = $accessToken;
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
	public function getAccessToken(): ?AccessTokenInterface
	{
		return $this->accessToken;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function isRevoked(): bool
	{
		return $this->revoked;
	}

	public function revoke(): RefreshTokenInterface
	{
		$this->revoked = true;

		return $this;
	}
}