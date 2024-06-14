<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Grants;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\IdTokenResponse as OpenIDConnectServerIdTokenResponse;
use phpbb\request\request;

class IdTokenResponse extends OpenIDConnectServerIdTokenResponse
{
	protected function getBuilder(AccessTokenEntityInterface $accessToken, UserEntityInterface $userEntity)
	{
		global $phpbb_container;
		/** @var request $request */
		$request = $phpbb_container->get('request');

		$builder = parent::getBuilder($accessToken, $userEntity);

		$code = $request->variable('code', '');
		$authCodePayload = json_decode($this->decrypt($code), true, 512, JSON_THROW_ON_ERROR);

		if (isset($authCodePayload['nonce'])) {
			$builder = $builder->withClaim('nonce', $authCodePayload['nonce']);
		}

		return $builder;
	}
}