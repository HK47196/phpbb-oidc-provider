<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

final class Client implements ClientEntityInterface
{
	use ClientTrait;
	use EntityTrait;

	private bool $allowPlainTextPkce = false;

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	/**
	 * @param string[] $redirectUri
	 */
	public function setRedirectUri(array $redirectUri): void
	{
		$this->redirectUri = $redirectUri;
	}

	public function getRedirectUri(): array|string
	{
		if (is_array($this->redirectUri) && count($this->redirectUri) === 1) {
			return $this->redirectUri[0];
		}
		return $this->redirectUri;
	}

	public function setConfidential(bool $isConfidential): void
	{
		$this->isConfidential = $isConfidential;
	}

	public function isPlainTextPkceAllowed(): bool
	{
		return $this->allowPlainTextPkce;
	}

	public function setAllowPlainTextPkce(bool $allowPlainTextPkce): void
	{
		$this->allowPlainTextPkce = $allowPlainTextPkce;
	}
}