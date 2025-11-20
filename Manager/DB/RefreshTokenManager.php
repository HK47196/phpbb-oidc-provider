<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Manager\DB;

use DateTimeImmutable;
use Exception;
use HK47196\OIDCProvider\Manager\AccessTokenManagerInterface;
use HK47196\OIDCProvider\Manager\RefreshTokenManagerInterface;
use HK47196\OIDCProvider\Model\RefreshToken;
use HK47196\OIDCProvider\Model\RefreshTokenInterface;
use phpbb\db\driver\driver_interface;
use Throwable;

final class RefreshTokenManager implements RefreshTokenManagerInterface
{
	private driver_interface $db;
	private AccessTokenManagerInterface $accessTokenManager;
	private string $table;

	public function __construct(driver_interface $db, AccessTokenManagerInterface $accessTokenManager, string $table)
	{
		$this->db = $db;
		$this->accessTokenManager = $accessTokenManager;
		$this->table = $table;
	}

	public function find(string $identifier): ?RefreshTokenInterface
	{
		$sql_arr = [
			'SELECT' => 'rt.refresh_token, rt.access_token, rt.expires_at, rt.revoked',
			'FROM' => [$this->table => 'rt'],
			'WHERE' => 'rt.refresh_token = \'' . $this->db->sql_escape($identifier) . '\'',
		];
		$sql = $this->db->sql_build_query('SELECT', $sql_arr);
		$result = $this->db->sql_query($sql);

		try {
			/** @var array{refresh_token: string, access_token: string, expires_at: string, revoked: string}|false $row */
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
	private function deserializeRefreshToken(string $refresh_token,
	                                         string $access_token,
	                                         string $expires_at,
	                                         string $revoked): RefreshTokenInterface
	{
		$at = $this->accessTokenManager->find($access_token);
		if ($at === null) {
			throw new Exception("Access token '{$access_token}' not found for refresh token '{$refresh_token}'");
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
		$data = [
			'refresh_token' => $refreshToken->getIdentifier(),
			'access_token' => $refreshToken->getAccessToken()?->getIdentifier(),
			'expires_at' => $refreshToken->getExpiry()->format('U'),
			'revoked' => $refreshToken->isRevoked() ? 1 : 0,
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