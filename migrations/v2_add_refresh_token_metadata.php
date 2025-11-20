<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\migrations;

use phpbb\db\migration\migration;

class v2_add_refresh_token_metadata extends migration
{
	public static function depends_on()
	{
		return ['\HK47196\OIDCProvider\migrations\v1'];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				'oauth2_refresh_token' => [
					'user_id' => ['UINT', 0],
					'client_id' => ['VCHAR:255', ''],
					'scopes' => ['MTEXT', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				'oauth2_refresh_token' => [
					'user_id',
					'client_id',
					'scopes',
				],
			],
		];
	}
}
