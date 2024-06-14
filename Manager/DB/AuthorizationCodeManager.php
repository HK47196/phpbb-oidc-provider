<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Manager\DB;

use DateTimeImmutable;
use Exception;
use HK47196\OIDCProvider\Manager\AuthorizationCodeManagerInterface;
use HK47196\OIDCProvider\Manager\ClientManagerInterface;
use HK47196\OIDCProvider\Model\AuthorizationCode;
use HK47196\OIDCProvider\Model\AuthorizationCodeInterface;
use HK47196\OIDCProvider\ValueObject\Scope;
use JsonException;
use phpbb\db\driver\driver_interface;
use RuntimeException;
use Throwable;

final class AuthorizationCodeManager implements AuthorizationCodeManagerInterface
{
	private driver_interface $db;
	private ClientManagerInterface $clientManager;
	private string $table;

	public function __construct(driver_interface $db, ClientManagerInterface $clientManager, string $table)
	{
		$this->db = $db;
		$this->clientManager = $clientManager;
		$this->table = $table;
	}

	public function find(string $identifier): ?AuthorizationCodeInterface
	{
		$sql_arr = [
			'SELECT' => 'ac.auth_code, ac.user_id, ac.client_id, ac.expires_at, ac.scopes, ac.revoked',
			'FROM' => [$this->table => 'ac'],
			'WHERE' => 'ac.auth_code = \'' . $this->db->sql_escape($identifier) . '\'',
		];
		$sql = $this->db->sql_build_query('SELECT', $sql_arr);
		$result = $this->db->sql_query($sql);
		/** @var array{auth_code: string, expires_at: string, client_id: string, user_id: string, scopes: string, revoked: string}|false $row */
		$row = $this->db->sql_fetchrow($result);
		try {
			if (!$row) {
				return null;
			}
			return $this->deserializeAuthorizationCode(...$row);
		} catch (Throwable $e) {
			error_log('Failed to deserialize authorization code from database: ' . $e->getMessage());
			return null;
		} finally {
			$this->db->sql_freeresult($result);
		}
	}

	/**
	 * @throws JsonException
	 * @throws Exception
	 */
	private function deserializeAuthorizationCode(string $auth_code,
	                                              string $expires_at,
	                                              string $client_id,
	                                              string $user_id,
	                                              string $scopes,
	                                              string $revoked): AuthorizationCodeInterface
	{
		$cl = $this->clientManager->find($client_id);

		if ($cl === null) {
			throw new RuntimeException("Client not found for authorization code. $client_id");
		}

		/** @var list<string> $sc */
		$sc = json_decode($scopes, true, 512, JSON_THROW_ON_ERROR);
		$sc = array_map(static fn(string $scope) => new Scope($scope), $sc);
		$expiry = DateTimeImmutable::createFromFormat('U', $expires_at);
		return new AuthorizationCode($auth_code, $expiry, $cl, $user_id, $sc, (bool)$revoked);
	}

	public function save(AuthorizationCodeInterface $authCode): void
	{
		$scopeStrings = array_map(static fn(Scope $scope) => (string)$scope, $authCode->getScopes());
		try {
			$scopes = json_encode($scopeStrings, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			error_log("Failed to serialize scopes to JSON: {$e->getMessage()}");
			return;
		}
		$data = [
			'auth_code' => $authCode->getIdentifier(),
			'client_id' => $authCode->getClient()->getIdentifier(),
			'expires_at' => $authCode->getExpiryDateTime()->format('U'),
			'user_id' => $authCode->getUserIdentifier(),
			'scopes' => $scopes,
			'revoked' => $authCode->isRevoked() ? 1 : 0,
		];

		$sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT',
				$data) . ' ON DUPLICATE KEY UPDATE ' . $this->db->sql_build_array('UPDATE', $data);
		$this->db->sql_query($sql);
	}

	public function clearExpired(): int
	{
		$expiresAt = (new DateTimeImmutable())->format('U');
		$sql_cmd = 'DELETE FROM ' . $this->table . ' WHERE expires_at < ' . $this->db->sql_escape($expiresAt);
		$this->db->sql_query($sql_cmd);

		/** @var int|false $affected */
		$affected = $this->db->sql_affectedrows();
		if ($affected === false) {
			return 0;
		}
		return $affected;
	}
}