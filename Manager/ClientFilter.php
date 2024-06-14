<?php

declare(strict_types=1);

namespace HK47196\OIDCProvider\Manager;

use HK47196\OIDCProvider\ValueObject\Grant;
use HK47196\OIDCProvider\ValueObject\RedirectUri;
use HK47196\OIDCProvider\ValueObject\Scope;

final class ClientFilter
{
	/**
	 * @var list<Grant>
	 */
	private array $grants = [];

	/**
	 * @var list<RedirectUri>
	 */
	private array $redirectUris = [];

	/**
	 * @var list<Scope>
	 */
	private array $scopes = [];

	/**
	 * @psalm-pure
	 */
	public static function create(): self
	{
		return new ClientFilter();
	}

	public function addGrantCriteria(Grant ...$grants): self
	{
		foreach ($grants as $grant) {
			$this->grants[] = $grant;
		}

		return $this;
	}

	public function addRedirectUriCriteria(RedirectUri ...$redirectUris): self
	{
		foreach ($redirectUris as $redirectUri) {
			$this->redirectUris[] = $redirectUri;
		}

		return $this;
	}

	public function addScopeCriteria(Scope ...$scopes): self
	{
		foreach ($scopes as $scope) {
			$this->scopes[] = $scope;
		}

		return $this;
	}

	/**
	 * @return list<Grant>
	 *
	 * @psalm-mutation-free
	 */
	public function getGrants(): array
	{
		return $this->grants;
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
	public function hasFilters(): bool
	{
		return
			!empty($this->grants)
			|| !empty($this->redirectUris)
			|| !empty($this->scopes);
	}
}
