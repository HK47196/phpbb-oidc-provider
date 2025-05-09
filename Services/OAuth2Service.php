<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Services;

use HK47196\OIDCProvider\Grants\IdTokenResponse;
use HK47196\OIDCProvider\Grants\OpenIdAuthCodeGrant;
use HK47196\OIDCProvider\Repository\IdentityRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\CryptKey;
use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\Entities\ClaimSetEntity;

class OAuth2Service
{
	protected AuthorizationServer $authorizationServer;

	/**
	 * @throws \Exception
	 */
	public function __construct(
		ClientRepositoryInterface       $clientRepository,
		AccessTokenRepositoryInterface  $accessTokenRepository,
		ScopeRepositoryInterface        $scopeRepository,
		AuthCodeRepositoryInterface     $authCodeRepository,
		RefreshTokenRepositoryInterface $refreshTokenRepository,
		IdentityRepository              $identityRepository
	)
	{
		$privateKey = new CryptKey('/run/secrets/oauth_private_key', null, false);
		$encryptionKey = base64_decode(getenv('OAUTH_ENC_KEY'));

		$openIdClaimSet = new ClaimSetEntity('openid', ['sid']);
		$responseType = new IdTokenResponse($identityRepository, new ClaimExtractor([$openIdClaimSet]));

		$this->authorizationServer = new AuthorizationServer(
			$clientRepository,
			$accessTokenRepository,
			$scopeRepository,
			$privateKey,
			$encryptionKey,
			$responseType
		);

		// Enable the Auth Code grant on the server
		$grant = new OpenIdAuthCodeGrant(
			$authCodeRepository,
			$refreshTokenRepository,
			new \DateInterval('PT10M')
		);
		$this->authorizationServer->enableGrantType(
			$grant,
			new \DateInterval('PT10M') // Access tokens will expire after 10 minutes
		);

		// Enable the Refresh Token grant on the server
		$refreshGrant = new RefreshTokenGrant($refreshTokenRepository);
		$this->authorizationServer->enableGrantType(
			$refreshGrant,
			new \DateInterval('P1M') // Refresh tokens will expire after 1 month
		);
	}

	public function getAuthorizationServer(): AuthorizationServer
	{
		return $this->authorizationServer;
	}
}
