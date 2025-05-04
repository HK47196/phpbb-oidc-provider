<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\controller;

use HK47196\OIDCProvider\Manager\AccessTokenManagerInterface;
use HK47196\OIDCProvider\Repository\IdentityRepository;
use HK47196\OIDCProvider\ValueObject\Scope;
use phpbb\request\request;
use phpbb\user;
use Symfony\Component\HttpFoundation\Response;

class userinfo
{
	protected user $user;
	protected request $request;
	protected AccessTokenManagerInterface $accessTokenManager;
	protected IdentityRepository $identityRepository;

	public function __construct(
		user $user,
		request $request,
		AccessTokenManagerInterface $accessTokenManager,
		IdentityRepository $identityRepository
	) {
		$this->user = $user;
		$this->request = $request;
		$this->accessTokenManager = $accessTokenManager;
		$this->identityRepository = $identityRepository;
	}

	public function handle(): Response
	{
		// Get the Authorization header
		$authHeader = $this->request->server('HTTP_AUTHORIZATION', '');
		
		// Check if the Authorization header is present and has the Bearer format
		if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
			return new \phpbb\json_response([
				'error' => 'invalid_token',
				'error_description' => 'Missing or invalid Authorization header'
			], 401);
		}
		
		// Extract the token
		$accessTokenString = $matches[1];
		
		// Find the access token in the database
		$accessToken = $this->accessTokenManager->find($accessTokenString);
		
		// Check if the token exists and is valid
		if ($accessToken === null) {
			return new \phpbb\json_response([
				'error' => 'invalid_token',
				'error_description' => 'Access token not found'
			], 401);
		}
		
		// Check if the token is expired
		if ($accessToken->getExpiry() < new \DateTimeImmutable()) {
			return new \phpbb\json_response([
				'error' => 'invalid_token',
				'error_description' => 'Access token has expired'
			], 401);
		}
		
		// Check if the token is revoked
		if ($accessToken->isRevoked()) {
			return new \phpbb\json_response([
				'error' => 'invalid_token',
				'error_description' => 'Access token has been revoked'
			], 401);
		}
		
		// Get the user ID from the access token
		$userId = $accessToken->getUserIdentifier();
		if ($userId === null) {
			return new \phpbb\json_response([
				'error' => 'invalid_token',
				'error_description' => 'Access token is not associated with a user'
			], 401);
		}
		
		// Get the scopes from the access token
		$tokenScopes = $accessToken->getScopes();
		$scopeStrings = array_map(fn(Scope $scope) => (string)$scope, $tokenScopes);
		
		// Check if the token has the 'openid' scope
		if (!in_array('openid', $scopeStrings, true)) {
			return new \phpbb\json_response([
				'error' => 'insufficient_scope',
				'error_description' => 'The access token does not have the required scope'
			], 403);
		}
		
		// Get the user entity with all claims
		$userEntity = $this->identityRepository->getUserEntityByIdentifier($userId);
		if ($userEntity === null) {
			return new \phpbb\json_response([
				'error' => 'invalid_token',
				'error_description' => 'User not found'
			], 401);
		}
		
		// Get all user claims
		$allClaims = $userEntity->getClaims();
		
		// Initialize the response with the subject claim (required)
		$response = ['sub' => $allClaims['sub']];
		
		// Add claims based on scopes
		if (in_array('profile', $scopeStrings, true)) {
			// Add profile claims
			if (isset($allClaims['preferred_username'])) {
				$response['preferred_username'] = $allClaims['preferred_username'];
			}
			if (isset($allClaims['profile'])) {
				$response['profile'] = $allClaims['profile'];
			}
			if (isset($allClaims['picture'])) {
				$response['picture'] = $allClaims['picture'];
			}
			if (isset($allClaims['id_groups'])) {
				$response['id_groups'] = $allClaims['id_groups'];
			}
		}
		
		if (in_array('email', $scopeStrings, true) && isset($allClaims['email'])) {
			// Add email claim
			$response['email'] = $allClaims['email'];
		}
		
		return new \phpbb\json_response($response);
	}
}

