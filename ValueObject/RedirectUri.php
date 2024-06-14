<?php

declare(strict_types=1);

namespace HK47196\OIDCProvider\ValueObject;

/**
 * @psalm-immutable
 */
class RedirectUri
{
	private string $redirectUri;

	/**
	 * @psalm-mutation-free
	 */
	public function __construct(string $redirectUri)
	{
		if (!filter_var($redirectUri, \FILTER_VALIDATE_URL)) {
			throw new \RuntimeException(sprintf('The \'%s\' string is not a valid URI.', $redirectUri));
		}

		$this->redirectUri = $redirectUri;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function __toString(): string
	{
		return $this->redirectUri;
	}
}
