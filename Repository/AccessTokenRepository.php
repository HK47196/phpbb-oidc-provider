<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Repository;

use HK47196\OIDCProvider\Converter\ScopeConverterInterface;
use HK47196\OIDCProvider\Core\Helper;
use HK47196\OIDCProvider\Manager\AccessTokenManagerInterface;
use HK47196\OIDCProvider\Manager\ClientManagerInterface;
use HK47196\OIDCProvider\Model\AbstractClient;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use HK47196\OIDCProvider\Entity\AccessToken as AccessTokenEntity;
use HK47196\OIDCProvider\Model\AccessToken as AccessTokenModel;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
	private AccessTokenManagerInterface $accessTokenManager;
	private ClientManagerInterface $clientManager;
	private ScopeConverterInterface $scopeConverter;
	private Helper $helper;

	public function __construct(AccessTokenManagerInterface $accessTokenManager,
	                            ClientManagerInterface      $clientManager,
	                            ScopeConverterInterface     $scopeConverter,
	                            Helper                      $helper)
	{
		$this->accessTokenManager = $accessTokenManager;
		$this->clientManager = $clientManager;
		$this->scopeConverter = $scopeConverter;
		$this->helper = $helper;
	}

	/**
	 * @param ClientEntityInterface $clientEntity
	 * @param ScopeEntityInterface[] $scopes
	 * @param mixed $userIdentifier
	 * @return AccessTokenEntityInterface
	 */
	public function getNewToken(ClientEntityInterface $clientEntity,
	                            array                 $scopes,
	                                                  $userIdentifier = null): AccessTokenEntityInterface
	{
		$accessToken = new AccessTokenEntity();
		$accessToken->setClient($clientEntity);
		//TODO
		$accessToken->setUserIdentifier($userIdentifier);

		foreach ($scopes as $scope) {
			$accessToken->addScope($scope);
		}

		return $accessToken;
	}

	public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
	{
		$accessToken = $this->accessTokenManager->find($accessTokenEntity->getIdentifier());

		if ($accessToken !== null) {
			throw UniqueTokenIdentifierConstraintViolationException::create();
		}

		$accessToken = $this->buildAccessTokenModel($accessTokenEntity);

		$this->accessTokenManager->save($accessToken);
	}


	/**
	 * @param string $tokenId
	 * @return void
	 */
	public function revokeAccessToken($tokenId): void
	{
		$accessToken = $this->accessTokenManager->find($tokenId);
		if ($accessToken === null) {
			return;
		}

		$accessToken->revoke();
		$this->accessTokenManager->save($accessToken);
	}


	/**
	 * @param string $tokenId
	 * @return bool
	 */
	public function isAccessTokenRevoked($tokenId): bool
	{
		$accessToken = $this->accessTokenManager->find($tokenId);

		if ($accessToken === null) {
			return true;
		}
		if ($accessToken->isRevoked()) {
			return true;
		}

		//Check if user is banned and revoke the access token
		$userIdentifier = $accessToken->getUserIdentifier();
		if (is_numeric($userIdentifier) && $this->helper->isUserBanned((int)$userIdentifier)) {
			$accessToken->revoke();
			$this->accessTokenManager->save($accessToken);
			return true;
		}

		return false;
	}

	private function buildAccessTokenModel(AccessTokenEntityInterface $accessTokenEntity): AccessTokenModel
	{
		/** @var AbstractClient $client */
		$client = $this->clientManager->find($accessTokenEntity->getClient()->getIdentifier());

		$userIdentifier = $accessTokenEntity->getUserIdentifier();

		return new AccessTokenModel(
			$accessTokenEntity->getIdentifier(),
			$accessTokenEntity->getExpiryDateTime(),
			$client,
			$userIdentifier,
			$this->scopeConverter->toDomainArray(array_values($accessTokenEntity->getScopes())),
			//TODO?? It doesn't seem to provide this to us
			false
		);
	}
}