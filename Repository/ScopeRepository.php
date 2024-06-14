<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Repository;

use HK47196\OIDCProvider\Converter\ScopeConverterInterface;
use HK47196\OIDCProvider\Manager\ClientManagerInterface;
use HK47196\OIDCProvider\Manager\ScopeManagerInterface;
use HK47196\OIDCProvider\Model\AbstractClient;
use HK47196\OIDCProvider\ValueObject\Scope;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

final class ScopeRepository implements ScopeRepositoryInterface
{
	private ScopeManagerInterface $scopeManager;
	private ClientManagerInterface $clientManager;
	private ScopeConverterInterface $scopeConverter;

	public function __construct(
		ScopeManagerInterface   $scopeManager,
		ClientManagerInterface  $clientManager,
		ScopeConverterInterface $scopeConverter
	)
	{
		$this->scopeManager = $scopeManager;
		$this->clientManager = $clientManager;
		$this->scopeConverter = $scopeConverter;
	}

	public function getScopeEntityByIdentifier($identifier): ?ScopeEntityInterface
	{
		$scope = $this->scopeManager->find($identifier);

		if ($scope === null) {
			return null;
		}

		return $this->scopeConverter->toLeague($scope);
	}

	/**
	 * @param array $scopes
	 * @param string $grantType
	 * @param ClientEntityInterface $clientEntity
	 * @param string|null $userIdentifier
	 * @param string|null $authCodeId
	 * @return ScopeEntityInterface[]
	 * @throws OAuthServerException
	 */
	public function finalizeScopes(
		array                 $scopes,
		                      $grantType,
		ClientEntityInterface $clientEntity,
		                      $userIdentifier = null,
		//TODO: why isn't this used?
		string                $authCodeId = null
	): array
	{
		/** @var AbstractClient|null $client */
		$client = $this->clientManager->find($clientEntity->getIdentifier());
		if ($client === null) {
			throw new OAuthServerException('Client not found', 0, 'invalid_client');
		}

		$scopes = $this->setupScopes($client, $this->scopeConverter->toDomainArray(array_values($scopes)));

		return $this->scopeConverter->toLeagueArray($scopes);
	}

	/**
	 * @param list<Scope> $requestedScopes
	 *
	 * @return list<Scope>
	 * @throws OAuthServerException
	 */
	private function setupScopes(AbstractClient $client, array $requestedScopes): array
	{
		$clientScopes = $client->getScopes();

		if (empty($clientScopes)) {
			return $requestedScopes;
		}

		if (empty($requestedScopes)) {
			return $clientScopes;
		}

		$finalizedScopes = [];
		$clientScopesAsStrings = array_map('\strval', $clientScopes);

		foreach ($requestedScopes as $requestedScope) {
			$requestedScopeAsString = (string)$requestedScope;
			if (!\in_array($requestedScopeAsString, $clientScopesAsStrings, true)) {
				throw OAuthServerException::invalidScope($requestedScopeAsString);
			}

			$finalizedScopes[] = $requestedScope;
		}

		return $finalizedScopes;
	}
}

