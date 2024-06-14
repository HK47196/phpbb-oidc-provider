<?php

declare(strict_types=1);

namespace HK47196\OIDCProvider\Model;

use HK47196\OIDCProvider\ValueObject\Grant;
use HK47196\OIDCProvider\ValueObject\RedirectUri;
use HK47196\OIDCProvider\ValueObject\Scope;

interface ClientInterface
{
	public function getName(): string;
	public function getIdentifier(): string;

	public function getSecret(): ?string;

	/**
	 * @return list<RedirectUri>
	 *
	 * @psalm-mutation-free
	 */
	public function getRedirectUris(): array;

	public function setRedirectUris(RedirectUri ...$redirectUris): self;

	/**
	 * @return list<Grant>
	 *
	 * @psalm-mutation-free
	 */
	public function getGrants(): array;

	public function setGrants(Grant ...$grants): self;

	/**
	 * @return list<Scope>
	 *
	 * @psalm-mutation-free
	 */
	public function getScopes(): array;

	public function setScopes(Scope ...$scopes): self;

	public function isActive(): bool;

	public function setActive(bool $active): self;

	public function isConfidential(): bool;

	public function isPlainTextPkceAllowed(): bool;

	public function setAllowPlainTextPkce(bool $allowPlainTextPkce): self;

	public function backChannelLogoutUrl(): ?string;
}