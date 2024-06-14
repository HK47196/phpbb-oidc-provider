<?php

declare(strict_types=1);

namespace HK47196\OIDCProvider\ValueObject;

/**
 * @psalm-immutable
 */
class Grant
{
	private string $grant;

	/**
	 * @psalm-mutation-free
	 */
	public function __construct(string $grant)
	{
		$this->grant = $grant;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function __toString(): string
	{
		return $this->grant;
	}
}