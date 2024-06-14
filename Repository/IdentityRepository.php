<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Repository;

use HK47196\OIDCProvider\Entity\User as UserEntity;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;
use phpbb\avatar\manager;
use phpbb\db\driver\driver_interface;
use phpbb\path_helper;
use phpbb\request\request;
use phpbb\user_loader;
use Symfony\Component\Yaml\Yaml;
use function function_exists;
use function is_string;
use function strlen;

class IdentityRepository implements IdentityProviderInterface
{
	private driver_interface $db;
	private user_loader $user_loader;
	private manager $avatar_manager;
	private string $php_ext;
	private path_helper $path_helper;
	private string $idGroupPrefix;
	private string $boardUrl;

	public function __construct(driver_interface $db,
	                            user_loader $user_loader,
	                            manager $avatar_manager,
	                            string $php_ext,
	                            path_helper $path_helper)
	{
		$config = self::loadConfig();
		$this->idGroupPrefix = $config['id_group_prefix'];
		$this->db = $db;
		$this->user_loader = $user_loader;
		$this->avatar_manager = $avatar_manager;
		$this->php_ext = $php_ext;
		$this->path_helper = $path_helper;
		$this->boardUrl = generate_board_url();
	}

	/**
	 * @param string|false $identifier
	 * @return UserEntity|null
	 */
	public function getUserEntityByIdentifier($identifier): ?UserEntity
	{
		if (!is_string($identifier)) {
			return null;
		}
		return $this->getUserEntityById($identifier);
	}

	/**
	 * @param int[] $group_ids
	 * @return string[]
	 */
	public function get_group_names(array $group_ids): array
	{
		//TODO: possibly cache this since groups are unlikely to change often
		$group_names = [];

		if (empty($group_ids)) {
			return $group_names;  // Return empty array if no group IDs provided
		}

		// Prepare a single query to fetch all group names efficiently
		$group_ids_str = implode(',', array_map('intval', $group_ids));  // Sanitize and format IDs
		$sql = 'SELECT group_id, group_name FROM ' . GROUPS_TABLE . ' WHERE group_id IN (' . $group_ids_str . ')';

		$result = $this->db->sql_query($sql);

		$idGroupLength = strlen($this->idGroupPrefix);
		while ($row = $this->db->sql_fetchrow($result)) {
			$group_name = $row['group_name'];
			if (!str_starts_with($group_name, $this->idGroupPrefix)) {
				continue;
			}
			// Remove the HQLINK_ prefix
			$group_names[] = substr($group_name, $idGroupLength);
		}

		$this->db->sql_freeresult($result);

		return $group_names;
	}

	/**
	 * @param int $user_id
	 * @return list<int>
	 */
	public function get_group_ids(int $user_id): array
	{
		if (!function_exists('group_memberships')) {
			include($this->path_helper->get_phpbb_root_path() . 'includes/functions_user.' . $this->php_ext);
		}
		$groups = group_memberships(false, $user_id);
		return array_column($groups, 'group_id');
	}


	private function getUserEntityById(string $id): ?UserEntity
	{
		if (!is_numeric($id) || $id <= 0) {
			return null;
		}

		$userId = (int)$id;
		$this->user_loader->load_users([$userId]);
		$user = $this->user_loader->get_user((int)$id);
		if ($user === false) {
			return null;
		}


		// Prepare data
		$data = [
			'sub' => (string)$userId,
			'preferred_username' => $user['username_clean'],
			'email' => htmlspecialchars($user['user_email'], ENT_QUOTES, 'UTF-8'),
		];


		// Handle avatar
		$avatarType = $user['user_avatar_type'];
		/** @var \phpbb\avatar\driver\driver_interface|null $avatarDriver */
		$avatarDriver = $this->avatar_manager->get_driver($avatarType);
		if ($avatarDriver) {
			$avatarRow = [
				'avatar' => $user['user_avatar'],
				'avatar_width' => $user['user_avatar_width'],
				'avatar_height' => $user['user_avatar_height']
			];
			$avatarData = $avatarDriver->get_data($avatarRow);
			$avatar = $avatarData['src'] ?? null;
			if (is_string($avatar)) {
				$pictureUrl = $this->path_helper->remove_web_root_path($avatar);
				$pictureUrl = htmlspecialchars("{$this->boardUrl}/$pictureUrl", ENT_QUOTES, 'UTF-8');
				$data['picture'] = $pictureUrl;
			}
		}

		// Profile URL
		$profileUrl = htmlspecialchars("{$this->boardUrl}/memberlist.$this->php_ext?mode=viewprofile&u=$userId",
			ENT_QUOTES,
			'UTF-8');
		$data['profile'] = $profileUrl;

		//TODO: get ignore list

		// Handle user groups
		$idGroupIds = $this->get_group_ids($userId);
		$idGroups = $this->get_group_names($idGroupIds);
		if (!empty($idGroups)) {
			$data['id_groups'] = array_map(static fn($group) => htmlspecialchars($group, ENT_QUOTES, 'UTF-8'), $idGroups);
		}

		$data['sid'] = $id;

		$userEntity = new UserEntity();
		$userEntity->setClaims($data);
		$userEntity->setIdentifier($userId);
		return $userEntity;
	}

	private static function loadConfig(): array
	{
		$confPath = __DIR__ . '/../config/identity.yml';
		return Yaml::parse(file_get_contents($confPath));
	}
}