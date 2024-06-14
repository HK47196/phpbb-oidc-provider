<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\controller;

use phpbb\request\request;
use phpbb\user;

//TODO: NOT YET IMPLEMENTED. TODO TODO TODO

class userinfo
{
	protected $user;
	protected $request;

	public function __construct(user $user, request $request)
	{
		$this->user = $user;
		$this->request = $request;
	}

	public function handle()
	{
		if ($this->user->data['user_id'] == ANONYMOUS) {
			return new \phpbb\json_response(['error' => 'unauthorized'], 401);
		}

		$userInfo = [
			'sub' => $this->user->data['user_id'],
			'name' => $this->user->data['username'],
			'email' => $this->user->data['user_email'],
		];

		return new \phpbb\json_response($userInfo);
	}
}

