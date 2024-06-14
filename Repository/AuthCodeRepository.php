<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Repository;

use HK47196\OIDCProvider\Converter\ScopeConverterInterface;
use HK47196\OIDCProvider\Core\Helper;
use HK47196\OIDCProvider\Entity\AuthCode;
use HK47196\OIDCProvider\Manager\AuthorizationCodeManagerInterface;
use HK47196\OIDCProvider\Manager\ClientManagerInterface;
use HK47196\OIDCProvider\Model\AuthorizationCode;
use InvalidArgumentException;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;


final class AuthCodeRepository implements AuthCodeRepositoryInterface
{
	private AuthorizationCodeManagerInterface $authorizationCodeManager;
	private ClientManagerInterface $clientManager;
	private ScopeConverterInterface $scopeConverter;
	private Helper $helper;

	public function __construct(
		AuthorizationCodeManagerInterface $authorizationCodeManager,
		ClientManagerInterface            $clientManager,
		ScopeConverterInterface           $scopeConverter,
		Helper $helper
	)
	{
		$this->authorizationCodeManager = $authorizationCodeManager;
		$this->clientManager = $clientManager;
		$this->scopeConverter = $scopeConverter;
		$this->helper = $helper;
	}

	public function getNewAuthCode(): AuthCode
	{
		return new AuthCode();
	}

	/**
	 * @throws UniqueTokenIdentifierConstraintViolationException
	 */
	public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
	{
		$authorizationCode = $this->authorizationCodeManager->find($authCodeEntity->getIdentifier());

		if ($authorizationCode !== null) {
			throw UniqueTokenIdentifierConstraintViolationException::create();
		}

		$authorizationCode = $this->buildAuthorizationCode($authCodeEntity);

		$this->authorizationCodeManager->save($authorizationCode);
	}

	/**
	 * @param string $codeId
	 * @return void
	 */
	public function revokeAuthCode($codeId): void
	{
		$authorizationCode = $this->authorizationCodeManager->find($codeId);

		if ($authorizationCode === null) {
			return;
		}

		$authorizationCode->revoke();

		$this->authorizationCodeManager->save($authorizationCode);
	}

	/**
	 * @param string $codeId
	 * @return bool
	 */
	public function isAuthCodeRevoked($codeId): bool
	{
		$authorizationCode = $this->authorizationCodeManager->find($codeId);

		if ($authorizationCode === null) {
			return true;
		}
		if ($authorizationCode->isRevoked()) {
			return true;
		}

		//Check if user is banned and revoke the auth code
		$userIdentifier = $authorizationCode->getUserIdentifier();
		if (is_numeric($userIdentifier) && $this->helper->isUserBanned((int)$userIdentifier)) {
			$authorizationCode->revoke();
			$this->authorizationCodeManager->save($authorizationCode);
			return true;
		}

		return false;
	}

	private function buildAuthorizationCode(AuthCodeEntityInterface $authCodeEntity): AuthorizationCode
	{
		$client = $this->clientManager->find($authCodeEntity->getClient()->getIdentifier());

		$userIdentifier = $authCodeEntity->getUserIdentifier();
		if ($client === null) {
			throw new InvalidArgumentException('Client not found');
		}

		return new AuthorizationCode(
			$authCodeEntity->getIdentifier(),
			$authCodeEntity->getExpiryDateTime(),
			$client,
			$userIdentifier,
			$this->scopeConverter->toDomainArray(array_values($authCodeEntity->getScopes()))
		);
	}
}

