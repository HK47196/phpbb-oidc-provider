<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Core;

use phpbb\db\driver\driver_interface;

class Helper
{
	protected driver_interface $db;

	public function __construct(driver_interface $db)
	{
		$this->db = $db;
	}

	public function isUserBanned(int $userId): bool
	{
		$query = $this->db->sql_build_query('SELECT', [
			'SELECT' => 'ban_userid, ban_end, ban_exclude',
			'FROM' => [BANLIST_TABLE => 'b'],
			'WHERE' => 'ban_userid = ' . $userId
		]);
		$stmt = $this->db->sql_query($query);

		$result = [];
		while ($row = $this->db->sql_fetchrow($stmt)) {
			$result[] = $row;
		}
		$this->db->sql_freeresult($stmt);

		// Iterate through the results and check ban conditions
		foreach ($result as $row) {
			if ($row['ban_exclude'] === 0 && ($row['ban_end'] === 0 || $row['ban_end'] > time())) {
				return true; // User is banned
			}
		}

		return false; // User is not banned
	}
}