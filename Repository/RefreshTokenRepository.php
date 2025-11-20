<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Repository;

use HK47196\OIDCProvider\Core\Helper;
use HK47196\OIDCProvider\Manager\AccessTokenManagerInterface;
use HK47196\OIDCProvider\Manager\RefreshTokenManagerInterface;
use HK47196\OIDCProvider\Model\RefreshToken as RefreshTokenModel;
use HK47196\OIDCProvider\Entity\RefreshToken as RefreshTokenEntity;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;


final class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
	private RefreshTokenManagerInterface $refreshTokenManager;
	private AccessTokenManagerInterface $accessTokenManager;
	private Helper $helper;

	public function __construct(
		RefreshTokenManagerInterface $refreshTokenManager,
		AccessTokenManagerInterface  $accessTokenManager,
		Helper                       $helper
	)
	{
		$this->refreshTokenManager = $refreshTokenManager;
		$this->accessTokenManager = $accessTokenManager;
		$this->helper = $helper;
	}

	public function getNewRefreshToken(): RefreshTokenEntityInterface
	{
		return new RefreshTokenEntity();
	}

	public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
	{
		$refreshToken = $this->refreshTokenManager->find($refreshTokenEntity->getIdentifier());

		if ($refreshToken !== null) {
			throw UniqueTokenIdentifierConstraintViolationException::create();
		}

		$refreshToken = $this->buildRefreshTokenModel($refreshTokenEntity);

		$this->refreshTokenManager->save($refreshToken);
	}

	/**
	 * @param string $tokenId
	 * @return void
	 */
	public function revokeRefreshToken($tokenId): void
	{
		$refreshToken = $this->refreshTokenManager->find($tokenId);

		if ($refreshToken === null) {
			return;
		}

		$refreshToken->revoke();

		$this->refreshTokenManager->save($refreshToken);
	}

	/**
	 * @param string $tokenId
	 * @return bool
	 */
	public function isRefreshTokenRevoked($tokenId): bool
	{
		$refreshToken = $this->refreshTokenManager->find($tokenId);

		if ($refreshToken === null) {
			return true;
		}
		if ($refreshToken->isRevoked()) {
			return true;
		}

		//Check if user is banned and revoke the refresh token
		$userIdentifier = $refreshToken->getAccessToken()?->getUserIdentifier();
		if (is_numeric($userIdentifier) && $this->helper->isUserBanned((int)$userIdentifier)) {
			$refreshToken->revoke();
			$this->refreshTokenManager->save($refreshToken);
			return true;
		}

		return false;
	}

	private function buildRefreshTokenModel(RefreshTokenEntityInterface $refreshTokenEntity): RefreshTokenModel
	{
		$accessTokenIdentifier = $refreshTokenEntity->getAccessToken()->getIdentifier();
		$accessToken = $this->accessTokenManager->find($accessTokenIdentifier);

		if ($accessToken === null) {
			throw new \RuntimeException("Access token '{$accessTokenIdentifier}' not found when building refresh token");
		}

		return new RefreshTokenModel(
			$refreshTokenEntity->getIdentifier(),
			$refreshTokenEntity->getExpiryDateTime(),
			$accessToken
		);
	}
}