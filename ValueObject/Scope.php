<?php

declare(strict_types=1);

namespace HK47196\OIDCProvider\ValueObject;

/**
 * @psalm-immutable
 */
class Scope
{
	private string $scope;

	/**
	 * @psalm-mutation-free
	 */
	public function __construct(string $scope)
	{
		$this->scope = $scope;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function __toString(): string
	{
		return $this->scope;
	}
}
