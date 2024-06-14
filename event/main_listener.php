<?php
declare(strict_types=1);

namespace hk47196\oidcprovider\event;

use DateTimeImmutable;
use Exception;
use HK47196\OIDCProvider\Manager\ClientManagerInterface;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use phpbb\db\driver\driver_interface;
use phpbb\request\request;
use phpbb\user_loader;
use stdClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	private user_loader $user_loader;
	private driver_interface $db;
	private string $access_token_table;
	private string $refresh_token_table;
	private string $auth_code_table;
	private ClientManagerInterface $clientManager;
	private request $request;

	public function __construct(user_loader            $user_loader,
	                            driver_interface       $db,
	                            string                 $access_token_table,
	                            string                 $refresh_token_table,
	                            string                 $auth_code_table,
	                            ClientManagerInterface $clientManager,
	                            request                $request)
	{
		$this->user_loader = $user_loader;
		$this->db = $db;
		$this->access_token_table = $access_token_table;
		$this->refresh_token_table = $refresh_token_table;
		$this->auth_code_table = $auth_code_table;
		$this->clientManager = $clientManager;
		$this->request = $request;
	}

	public static function getSubscribedEvents(): array
	{
		return [
			'core.session_kill_after' => 'on_session_kill',
			'core.mcp_ban_before' => 'on_mcp_ban_before',
			'core.acp_ban_before' => 'on_acp_ban_before',
		];
	}


	private function revoke_user_tokens($userId): void
	{
		// Revoke access tokens
		$sql = 'UPDATE ' . $this->access_token_table . '
            SET revoked = 1
            WHERE user_id = ' . (int)$userId;
		$this->db->sql_query($sql);

		// Revoke authorization codes
		$sql = 'UPDATE ' . $this->auth_code_table . '
            SET revoked = 1
            WHERE user_id = ' . (int)$userId;
		$this->db->sql_query($sql);

		// Revoke refresh tokens
		// First, find all access tokens for the user
		$sql = 'SELECT access_token
            FROM ' . $this->access_token_table . '
            WHERE user_id = ' . (int)$userId;
		$result = $this->db->sql_query($sql);

		$accessTokens = [];
		while ($row = $this->db->sql_fetchrow($result)) {
			$accessTokens[] = $row['access_token'];
		}
		$this->db->sql_freeresult($result);

		if (!empty($accessTokens)) {
			// Then, revoke all refresh tokens associated with these access tokens
			$inQuery = $this->db->sql_in_set('access_token', $accessTokens);
			$sql = 'UPDATE ' . $this->refresh_token_table . '
                SET revoked = 1
                WHERE ' . $inQuery;
			$this->db->sql_query($sql);
		}
	}


	private function sendLogoutToken($logoutToken, $logoutEndpoint): array
	{
		$ch = curl_init($logoutEndpoint);

		// Prepare the payload as application/x-www-form-urlencoded
		$payload = http_build_query(['logout_token' => $logoutToken]);

		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/x-www-form-urlencoded'
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);
		$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($response === false) {
			$error = curl_error($ch);
			$errorCode = curl_errno($ch);
			curl_close($ch);
			return [
				'success' => false,
				'error' => $error,
				'errorCode' => $errorCode
			];
		}

		curl_close($ch);

		return [
			'success' => $httpStatusCode === 200,
			'statusCode' => $httpStatusCode,
			'response' => $response
		];
	}


	private function backchannel_logout($user_id): void
	{
		if ($user_id === ANONYMOUS) {
			return;
		}
		$this->revoke_user_tokens($user_id);
		$privateKeyPath = '/run/secrets/oauth_private_key';
		$privateKey = InMemory::file($privateKeyPath);

		$now = new DateTimeImmutable();


		$claimsFormatter = ChainedFormatter::withUnixTimestampDates();

		// Add required id_token claims


		$clients = $this->clientManager->list();
		$issuer = 'https://' . $this->request->server('HTTP_HOST', '');
		foreach ($clients as $client) {
			try {
				$logOutUrl = $client->backChannelLogoutUrl();
				if ($logOutUrl === null) {
					continue;
				}

				$audience = $client->getIdentifier();
				$builder = new Builder(new JoseEncoder(), $claimsFormatter);
				$builder = $builder
					->permittedFor($client->getIdentifier())
					->issuedBy($issuer)
					->permittedFor($audience)
					->identifiedBy(bin2hex(random_bytes(16)), true)
					->issuedAt($now)
					->relatedTo((string)$user_id)
					->withClaim('events', ['http://schemas.openid.net/event/backchannel-logout' => new stdClass()])
					->withClaim('sid', (string)$user_id);

				$token = $builder->getToken(new Sha256(), $privateKey);
				$tokenString = $token->toString();

				$result = $this->sendLogoutToken($tokenString, $logOutUrl);
				if (!$result['success']) {
					error_log('Failed to send backchannel logout request to ' . $logOutUrl . ': HTTP status ' . $result['statusCode'] . ', response: ' . $result['response']);
				}
			} catch (Exception $e) {
				error_log('Failed to send backchannel logout request to ' . $logOutUrl . ': ' . $e->getMessage());
			}
		}
	}

	public function on_session_kill($event): void
	{
//		$session_id = $event['session_id'];
		$user_id = $event['user_id'];
		if (!is_numeric($user_id) || $user_id === ANONYMOUS) {
			return;
		}
		$this->backchannel_logout($user_id);
	}

	private function acp_mcp_ban_event($event): void
	{
		// Bail out if the ban is an exclusion from a ban
		if ($event['ban_exclude']) {
			return;
		}

		$mode = $event['mode'];
		// We only support user bans for now
		if ($mode !== 'user') {
			return;
		}

		/** @var string|list<string> $ban */
		$userAry = $event['ban'];
		if (is_string($userAry)) {
			$userAry = [$userAry];
		}
		foreach ($userAry as $username) {
			$user = $this->user_loader->load_user_by_username($username);
			if ($user !== ANONYMOUS) {
				$this->backchannel_logout($user);
			}
		}
	}

	public function on_mcp_ban_before($event): void
	{
		$this->acp_mcp_ban_event($event);
	}

	public function on_acp_ban_before($event): void
	{
		$this->acp_mcp_ban_event($event);
	}
}