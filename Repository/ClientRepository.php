<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Repository;

use HK47196\OIDCProvider\Entity\Client as ClientEntity;
use HK47196\OIDCProvider\Manager\ClientManagerInterface;
use HK47196\OIDCProvider\Model\ClientInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use function in_array;

class ClientRepository implements ClientRepositoryInterface
{
	private ClientManagerInterface $clientManager;

	public function __construct(ClientManagerInterface $clientManager)
	{
		$this->clientManager = $clientManager;
	}

	/**
	 * @param string $clientIdentifier
	 */
	public function getClientEntity($clientIdentifier): ?ClientEntityInterface
	{
		$client = $this->clientManager->find($clientIdentifier);
		if ($client === null) {
			return null;
		}
		return $this->buildClientEntity($client);
	}

	private function buildClientEntity(ClientInterface $client): ClientEntity
	{
		$clientEntity = new ClientEntity();
		$clientEntity->setIdentifier($client->getIdentifier());
		$clientEntity->setName($client->getName());
		$clientEntity->setRedirectUri(array_map('\strval', $client->getRedirectUris()));
		$clientEntity->setConfidential($client->isConfidential());
		$clientEntity->setAllowPlainTextPkce($client->isPlainTextPkceAllowed());

		return $clientEntity;
	}

	/**
	 * @param string $clientIdentifier
	 * @param string|null $clientSecret
	 * @param string|null $grantType
	 * @return bool
	 */
	public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
	{
		$client = $this->clientManager->find($clientIdentifier);

		if ($client === null) {
			return false;
		}

		if (!$client->isActive()) {
			return false;
		}

		if (!$this->isGrantSupported($client, $grantType)) {
			return false;
		}

		if ($client->isConfidential() && !hash_equals((string)$client->getSecret(), (string)$clientSecret)) {
			return false;
		}

		return true;
	}

	private function isGrantSupported(ClientInterface $client, ?string $grant): bool
	{
		if ($grant === null) {
			return true;
		}

		$grants = $client->getGrants();

		if (empty($grants)) {
			return true;
		}

		return in_array($grant, $client->getGrants(), true);
	}
}

