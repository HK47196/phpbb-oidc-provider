<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Model;

use HK47196\OIDCProvider\ValueObject\Grant;
use HK47196\OIDCProvider\ValueObject\RedirectUri;
use HK47196\OIDCProvider\ValueObject\Scope;

abstract class AbstractClient implements ClientInterface
{
	private string $name;
	protected string $identifier;
	private ?string $secret;
	/** @var list<RedirectUri> */
	private array $redirectUris;
	/** @var list<Grant> */
	private array $grants;
	/** @var list<Scope> */
	private array $scopes;
	private bool $active;
	private bool $allowPlainTextPkce;
	private ?string $backChannelLogoutUrl;

	/**
	 * @psalm-mutation-free
	 *
	 * @param list<RedirectUri> $redirectUris
	 * @param list<Grant> $grants
	 * @param list<Scope> $scopes
	 */
	public function __construct(string $name, string $identifier, ?string $secret, array $redirectUris = [], array $grants = [], array $scopes = [], bool $active = true, bool $allowPlainTextPkce = false, ?string $backChannelLogoutUrl = null)
	{
		$this->name = $name;
		$this->identifier = $identifier;
		$this->secret = $secret;
		$this->redirectUris = $redirectUris;
		$this->grants = $grants;
		$this->scopes = $scopes;
		$this->active = $active;
		$this->allowPlainTextPkce = $allowPlainTextPkce;
		$this->backChannelLogoutUrl = $backChannelLogoutUrl;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): ClientInterface
	{
		$this->name = $name;

		return $this;
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
	public function getSecret(): ?string
	{
		return $this->secret;
	}

	/**
	 * @return list<RedirectUri>
	 *
	 * @psalm-mutation-free
	 */
	public function getRedirectUris(): array
	{
		return $this->redirectUris;
	}

	public function setRedirectUris(RedirectUri ...$redirectUris): ClientInterface
	{
		/** @var list<RedirectUri> $redirectUris */
		$this->redirectUris = $redirectUris;

		return $this;
	}

	public function getGrants(): array
	{
		return $this->grants;
	}

	public function setGrants(Grant ...$grants): ClientInterface
	{
		/** @var list<Grant> $grants */
		$this->grants = $grants;

		return $this;
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

	public function setScopes(Scope ...$scopes): ClientInterface
	{
		/** @var list<Scope> $scopes */
		$this->scopes = $scopes;

		return $this;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function isActive(): bool
	{
		return $this->active;
	}

	public function setActive(bool $active): ClientInterface
	{
		$this->active = $active;

		return $this;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function isConfidential(): bool
	{
		return null !== $this->secret && '' !== $this->secret;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function isPlainTextPkceAllowed(): bool
	{
		return $this->allowPlainTextPkce;
	}

	public function setAllowPlainTextPkce(bool $allowPlainTextPkce): ClientInterface
	{
		$this->allowPlainTextPkce = $allowPlainTextPkce;

		return $this;
	}

	public function backChannelLogoutUrl(): ?string
	{
		return $this->backChannelLogoutUrl;
	}
}