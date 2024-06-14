<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\cron;

use HK47196\OIDCProvider\Manager\AccessTokenManagerInterface;
use HK47196\OIDCProvider\Manager\AuthorizationCodeManagerInterface;
use HK47196\OIDCProvider\Manager\RefreshTokenManagerInterface;
use phpbb\config\config;
use phpbb\cron\task\base;

class cleanup_expired_tokens_task extends base
{
	private AccessTokenManagerInterface $accessTokenManager;
	private AuthorizationCodeManagerInterface $authCodeManager;
	private RefreshTokenManagerInterface $refreshTokenManager;
	private config $config;

	public function __construct(AccessTokenManagerInterface       $accessTokenManager,
	                            AuthorizationCodeManagerInterface $authCodeManager,
	                            RefreshTokenManagerInterface      $refreshTokenManager,
	                            config                            $config)
	{
		$this->accessTokenManager = $accessTokenManager;
		$this->authCodeManager = $authCodeManager;
		$this->refreshTokenManager = $refreshTokenManager;
		$this->config = $config;
	}

	public function run()
	{
		$this->accessTokenManager->clearExpired();
		$this->authCodeManager->clearExpired();
		$this->refreshTokenManager->clearExpired();
		$this->config->set('oidcprovider_last_expired_cron', time(), false);
	}

	public function should_run(): bool
	{
		return $this->config['oidcprovider_last_expired_cron'] < strtotime('30 minutes ago');
	}
}