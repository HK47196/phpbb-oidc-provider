<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\migrations;

use phpbb\db\migration\migration;

class expired_token_cron_migration extends migration
{
	public static function depends_on(): array
	{
		return ['HK47196\OIDCProvider\migrations\v1'];
	}

	public function effectively_installed(): bool
	{
		return isset($this->config['oidcprovider_last_expired_cron']);
	}

	public function update_data(): array
	{
		return [
			['config.add', ['oidcprovider_last_expired_cron', 0, true]],
		];
	}
}