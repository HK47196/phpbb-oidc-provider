<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

final class AccessToken implements AccessTokenEntityInterface
{
	use AccessTokenTrait;
	use EntityTrait;
	use TokenEntityTrait;
}