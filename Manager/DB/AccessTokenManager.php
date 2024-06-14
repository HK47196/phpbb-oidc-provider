<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Manager\DB;

use DateTimeImmutable;
use Exception;
use HK47196\OIDCProvider\Manager\AccessTokenManagerInterface;
use HK47196\OIDCProvider\Manager\ClientManagerInterface;
use HK47196\OIDCProvider\Model\AccessToken;
use HK47196\OIDCProvider\Model\AccessTokenInterface;
use HK47196\OIDCProvider\ValueObject\Scope;
use JsonException;
use phpbb\db\driver\driver_interface;
use RuntimeException;
use Throwable;


final class AccessTokenManager implements AccessTokenManagerInterface
{
	private driver_interface $db;
	private ClientManagerInterface $clientManager;
	private string $table;
	private bool $persistAccessToken;

	public function __construct(driver_interface $db,
	                            ClientManagerInterface $clientManager,
	                            string $table,
	                            bool $persistAccessToken)
	{
		$this->db = $db;
		$this->persistAccessToken = $persistAccessToken;
		$this->clientManager = $clientManager;
		$this->table = $table;
	}

	public function find(string $identifier): ?AccessTokenInterface
	{
		if (!$this->persistAccessToken) {
			return null;
		}

		$sql_arr = [
			'SELECT' => 'at.access_token, at.user_id, at.client_id, at.expires_at, at.scopes, at.revoked',
			'FROM' => [$this->table => 'at'],
			'WHERE' => 'at.access_token = \'' . $this->db->sql_escape($identifier) . '\'',
		];
		$sql = $this->db->sql_build_query('SELECT', $sql_arr);
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);

		try {
			if (!$row) {
				return null;
			}
			return $this->deserializeAccessToken(...$row);
		} catch (Throwable $e) {
			error_log('Failed to deserialize access token from database: ' . $e->getMessage());
			return null;
		} finally {
			$this->db->sql_freeresult($result);
		}
	}

	/**
	 * @throws JsonException
	 * @throws Exception
	 */
	private function deserializeAccessToken(string $access_token,
	                                        string $expires_at,
	                                        string $client_id,
	                                        string $user_id,
	                                        string $scopes,
	                                        string $revoked): AccessTokenInterface
	{
		$cl = $this->clientManager->find($client_id);

		if ($cl === null) {
			throw new RuntimeException("Client not found for access token. $client_id");
		}
		/** @var list<string> $sc */
		$sc = json_decode($scopes, true, 512, JSON_THROW_ON_ERROR);
		$sc = array_map(static fn(string $scope) => new Scope($scope), $sc);
		$expiry = DateTimeImmutable::createFromFormat('U', $expires_at);
		return new AccessToken(
			$access_token,
			$expiry,
			$cl,
			$user_id,
			$sc,
			(bool)$revoked
		);
	}

	public function save(AccessTokenInterface $accessToken): void
	{
		if (!$this->persistAccessToken) {
			return;
		}

		$scopeStrings = array_map(static fn(Scope $scope) => (string)$scope, $accessToken->getScopes());
		try {
			$scopes = json_encode($scopeStrings, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			error_log('Failed to serialize scopes to JSON: ' . $e->getMessage());
			return;
		}
		$data = [
			'access_token' => $accessToken->getIdentifier(),
			'client_id' => $accessToken->getClient()->getIdentifier(),
			'expires_at' => $accessToken->getExpiry()->format('U'),
			'user_id' => $accessToken->getUserIdentifier(),
			'scopes' => $scopes,
			'revoked' => $accessToken->isRevoked() ? 1 : 0,
		];


		$sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT',
				$data) . ' ON DUPLICATE KEY UPDATE ' . $this->db->sql_build_array('UPDATE', $data);
		$result = $this->db->sql_query($sql);
		$this->db->sql_freeresult($result);
	}

	public function clearExpired(): int
	{
		if (!$this->persistAccessToken) {
			return 0;
		}

		$expiresAt = (new DateTimeImmutable())->format('U');
		$sql_cmd = 'DELETE FROM ' . $this->table . ' WHERE expires_at < ' . $this->db->sql_escape($expiresAt);
		$this->db->sql_query($sql_cmd);

		/** @var int|false $affectedRows */
		$affectedRows = $this->db->sql_affectedrows();
		if ($affectedRows === false) {
			return 0;
		}
		return $affectedRows;
	}
}
