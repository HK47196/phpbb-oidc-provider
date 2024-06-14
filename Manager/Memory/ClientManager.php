<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Manager\Memory;

use HK47196\OIDCProvider\Manager\ClientManagerInterface;
use HK47196\OIDCProvider\Model\Client;
use HK47196\OIDCProvider\Model\ClientInterface;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class ClientManager implements ClientManagerInterface
{
	/** @var list<ClientInterface> */
	private array $clients;

	public function __construct()
	{
		$this->clients = self::load();
	}

	public function save(ClientInterface $client): void
	{
		throw new RuntimeException('Not implemented');
	}

	public function remove(ClientInterface $client): void
	{
		throw new RuntimeException('Not implemented');
	}

	public function find(string $identifier): ?ClientInterface
	{
		foreach ($this->clients as $client) {
			if ($client->getIdentifier() === $identifier) {
				return $client;
			}
		}
		return null;
	}

	/**
	 * @return list<ClientInterface>
	 */
	public function list(): array
	{
		return $this->clients;
	}

	/**
	 * @return list<ClientInterface>
	 */
	private static function load(): array
	{
		$confPath = __DIR__ . '/../../config/clients.yml';
		$conf = Yaml::parse(file_get_contents($confPath));
		$config = $conf['clients'];
		$clients = [];
		foreach ($config as $client) {
			$id = $client['id'];
			$name = $client['name'];
			$secret = $client['secret'];
			$redirectUris = $client['redirect_uris'];
			$grants = $client['grant_types'];
			$scopes = $client['scopes'];
			$active = $client['active'];
			$allowPlainTextPkce = $client['allow_plain_text_pkce'];
			$backChannelLogoutUrl = $client['backchannel_logout_url'] ?? null;

			$clients[] = new Client($id,
				$name,
				$secret,
				$redirectUris,
				$grants,
				$scopes,
				$active,
				$allowPlainTextPkce,
				$backChannelLogoutUrl);
		}
		return $clients;
	}
}