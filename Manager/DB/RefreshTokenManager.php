<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Manager\DB;

use DateTimeImmutable;
use Exception;
use HK47196\OIDCProvider\Manager\AccessTokenManagerInterface;
use HK47196\OIDCProvider\Manager\ClientManagerInterface;
use HK47196\OIDCProvider\Manager\RefreshTokenManagerInterface;
use HK47196\OIDCProvider\Model\AccessToken;
use HK47196\OIDCProvider\Model\RefreshToken;
use HK47196\OIDCProvider\Model\RefreshTokenInterface;
use HK47196\OIDCProvider\ValueObject\Scope;
use JsonException;
use phpbb\db\driver\driver_interface;
use Throwable;

final class RefreshTokenManager implements RefreshTokenManagerInterface
{
	private driver_interface $db;
	private AccessTokenManagerInterface $accessTokenManager;
	private ClientManagerInterface $clientManager;
	private string $table;

	public function __construct(
		driver_interface $db,
		AccessTokenManagerInterface $accessTokenManager,
		ClientManagerInterface $clientManager,
		string $table
	)
	{
		$this->db = $db;
		$this->accessTokenManager = $accessTokenManager;
		$this->clientManager = $clientManager;
		$this->table = $table;
	}

	public function find(string $identifier): ?RefreshTokenInterface
	{
		$sql_arr = [
			'SELECT' => 'rt.refresh_token, rt.access_token, rt.expires_at, rt.revoked, rt.user_id, rt.client_id, rt.scopes',
			'FROM' => [$this->table => 'rt'],
			'WHERE' => 'rt.refresh_token = \'' . $this->db->sql_escape($identifier) . '\'',
		];
		$sql = $this->db->sql_build_query('SELECT', $sql_arr);
		$result = $this->db->sql_query($sql);

		try {
			/** @var array{refresh_token: string, access_token: string, expires_at: string, revoked: string, user_id: string, client_id: string, scopes: string}|false $row */
			$row = $this->db->sql_fetchrow($result);

			if (!$row) {
				return null;
			}
			return $this->deserializeRefreshToken(...$row);
		} catch (Throwable $e) {
			error_log('Failed to deserialize refresh token from database: ' . $e->getMessage());
			return null;
		} finally {
			$this->db->sql_freeresult($result);
		}
	}

	/**
	 * @throws Exception
	 */
	private function deserializeRefreshToken(
		string $refresh_token,
		string $access_token,
		string $expires_at,
		string $revoked,
		string $user_id = '0',
		string $client_id = '',
		string $scopes = ''
	): RefreshTokenInterface
	{
		$at = null;
		if ($client_id !== '' && $scopes !== '') {
			$client = $this->clientManager->find($client_id);
			if ($client !== null) {
				try {
					/** @var list<string> $sc */
					$sc = json_decode($scopes, true, 512, JSON_THROW_ON_ERROR);
					$sc = array_map(static fn(string $scope) => new Scope($scope), $sc);
					
					// We don't know the original expiry of the access token, but for a refresh token
					// the access token object is mostly a container for client/user/scopes.
					// We can reuse the refresh token expiry or current time.
					// Let's use a safe default.
					$at = new AccessToken(
						$access_token,
						new DateTimeImmutable(), // Expiry doesn't matter much here as it's not validated for refresh
						$client,
						$user_id,
						$sc,
						false // Assume not revoked if we are reconstructing it for refresh
					);
				} catch (Throwable $e) {
					error_log("Failed to reconstruct access token from refresh token metadata: " . $e->getMessage());
				}
			}
		}

		if ($at === null) {
			$at = $this->accessTokenManager->find($access_token);
			if ($at === null) {
				throw new Exception("Access token '{$access_token}' not found for refresh token '{$refresh_token}'");
			}
		}

		$expiry = DateTimeImmutable::createFromFormat('U', $expires_at);
		if ($expiry === false) {
			throw new Exception("Failed to parse expires_at timestamp: {$expires_at}");
		}
		return new RefreshToken(
			$refresh_token,
			$expiry,
			$at,
			(bool)$revoked
		);
	}

	public function save(RefreshTokenInterface $refreshToken): void
	{
		$accessToken = $refreshToken->getAccessToken();
		$scopes = '[]';
		$clientId = '';
		$userId = 0;

		if ($accessToken !== null) {
			$scopeStrings = array_map(static fn(Scope $scope) => (string)$scope, $accessToken->getScopes());
			try {
				$scopes = json_encode($scopeStrings, JSON_THROW_ON_ERROR);
			} catch (JsonException $e) {
				error_log('Failed to serialize scopes to JSON: ' . $e->getMessage());
			}
			$clientId = $accessToken->getClient()->getIdentifier();
			$userId = (int)$accessToken->getUserIdentifier();
		}

		$data = [
			'refresh_token' => $refreshToken->getIdentifier(),
			'access_token' => $accessToken?->getIdentifier(),
			'expires_at' => $refreshToken->getExpiry()->format('U'),
			'revoked' => $refreshToken->isRevoked() ? 1 : 0,
			'client_id' => $clientId,
			'user_id' => $userId,
			'scopes' => $scopes,
		];

		$sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT',
				$data) . ' ON DUPLICATE KEY UPDATE ' . $this->db->sql_build_array('UPDATE', $data);
		$result = $this->db->sql_query($sql);
		$this->db->sql_freeresult($result);
	}

	public function clearExpired(): int
	{
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