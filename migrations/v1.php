<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\migrations;

use phpbb\db\migration\migration;

class v1 extends migration
{
	//TODO: need to figure out how to inject these
	private string $access_token_table;
	private string $refresh_token_table;
	private string $auth_code_table;

	public function effectively_installed()
	{
		return isset($this->config['oidcprovider_installed']) && $this->config['oidcprovider_installed'];
	}

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v33x\v3310'];
	}

	public function update_schema()
	{
		global $phpbb_container;
		return [
			'add_tables' => [
				'oauth2_access_token' => [
					'COLUMNS' => [
						'access_token' => ['VCHAR:255', ''],
						'user_id' => ['UINT', 0],
						'client_id' => ['VCHAR:255', ''],
						'expires_at' => ['TIMESTAMP', 0],
						'scopes' => ['MTEXT', ''],
						'revoked' => ['BOOL', 0],
					],
					'PRIMARY_KEY' => 'access_token',
				],
				'oauth2_refresh_token' => [
					'COLUMNS' => [
						'refresh_token' => ['VCHAR:255', ''],
						'access_token' => ['VCHAR:255', ''],
						'expires_at' => ['TIMESTAMP', 0],
						'revoked' => ['BOOL', 0],
					],
					'PRIMARY_KEY' => 'refresh_token',
				],
				'oauth2_authorization_code' => [
					'COLUMNS' => [
						'auth_code' => ['VCHAR:255', ''],
						'user_id' => ['UINT', 0],
						'client_id' => ['VCHAR:255', ''],
						'expires_at' => ['TIMESTAMP', 0],
						'scopes' => ['MTEXT', ''],
						'revoked' => ['BOOL', 0],
					],
					'PRIMARY_KEY' => 'auth_code',
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				'oauth2_access_token',
				'oauth2_refresh_token',
				'oauth2_authorization_code',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['oidcprovider_installed', 1]],
		];
	}
}
