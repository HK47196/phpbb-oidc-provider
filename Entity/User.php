<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Entity;

use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\Entities\ClaimSetInterface;

final class User implements UserEntityInterface, ClaimSetInterface
{
	use EntityTrait;

	protected array $claims = [];

	public function setClaims(array $claims): void
	{
		$this->claims = $claims;
	}

	public function getClaims(): array
	{
		return $this->claims;
	}
}