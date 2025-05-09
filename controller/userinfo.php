<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\controller;

use HK47196\OIDCProvider\Manager\AccessTokenManagerInterface;
use HK47196\OIDCProvider\Repository\IdentityRepository;
use HK47196\OIDCProvider\ValueObject\Scope;
use Nyholm\Psr7Server\ServerRequestCreator;
use phpbb\config\config;
use phpbb\request\request;
use phpbb\user;
use Psr\Http\Message\ResponseFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\HttpFoundation\Response;

class userinfo
{
	protected user $user;
	protected request $request;
	protected AccessTokenManagerInterface $accessTokenManager;
	protected IdentityRepository $identityRepository;
	protected config $config;
	private ServerRequestCreator $creator;
	private HttpFoundationFactoryInterface $httpFoundationFactory;
	private ResponseFactoryInterface $responseFactory;

	public function __construct(
		user $user,
		request $request,
		AccessTokenManagerInterface $accessTokenManager,
		IdentityRepository $identityRepository,
		ServerRequestCreator $creator,
		HttpFoundationFactoryInterface $httpFoundationFactory,
		ResponseFactoryInterface $responseFactory,
		config $config
	) {
		$this->user = $user;
		$this->request = $request;
		$this->accessTokenManager = $accessTokenManager;
		$this->identityRepository = $identityRepository;
		$this->creator = $creator;
		$this->httpFoundationFactory = $httpFoundationFactory;
		$this->responseFactory = $responseFactory;
		$this->config = $config;
	}

	/**
	 * Create a JSON response with the given status code and data
	 *
	 * @param int $statusCode HTTP status code
	 * @param array $data Response data
	 * @return Response
	 */
	private function createJsonResponse(int $statusCode, array $data): Response
	{
		$response = $this->responseFactory->createResponse($statusCode);
		$response->getBody()->write(json_encode($data));
		$response = $response->withHeader('Content-Type', 'application/json');
		return $this->httpFoundationFactory->createResponse($response);
	}
	
	/**
	 * Create an error response with the given status code, error code, and description
	 *
	 * @param int $statusCode HTTP status code
	 * @param string $errorCode OAuth2 error code
	 * @param string $errorDescription Human-readable error description
	 * @return Response
	 */
	private function createErrorResponse(int $statusCode, string $errorCode, string $errorDescription): Response
	{
		return $this->createJsonResponse($statusCode, [
			'error' => $errorCode,
			'error_description' => $errorDescription
		]);
	}

	public function handle(): Response
	{
		// Get the Authorization header
		$authHeader = $this->request->server('HTTP_AUTHORIZATION', '');
		
		// Check if the Authorization header is present and has the Bearer format
		if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
			return $this->createErrorResponse(
				401,
				'invalid_token',
				'Missing or invalid Authorization header'
			);
		}
		
		// Extract the token
		$accessTokenString = $matches[1];
		
		// If the token looks like a JWT, extract the jti claim which contains the token ID
		if (preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+$/', $accessTokenString)) {
			try {
				// Decode the JWT payload (middle part) without verification
				$parts = explode('.', $accessTokenString);
				if (count($parts) === 3) {
					$payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
					if (isset($payload['jti'])) {
						$accessTokenString = $payload['jti'];
					}
				}
			} catch (\Exception $e) {
				// Continue with the original token
			}
		}
		
		// Find the access token in the database using the extracted identifier
		$accessToken = $this->accessTokenManager->find($accessTokenString);
		
		// Check if the token exists and is valid
		if ($accessToken === null) {
			return $this->createErrorResponse(
				401,
				'invalid_token',
				'Access token not found'
			);
		}
		
		// Check if the token is expired
		if ($accessToken->getExpiry() < new \DateTimeImmutable()) {
			return $this->createErrorResponse(
				401,
				'invalid_token',
				'Access token has expired'
			);
		}
		
		// Check if the token is revoked
		if ($accessToken->isRevoked()) {
			return $this->createErrorResponse(
				401,
				'invalid_token',
				'Access token has been revoked'
			);
		}
		
		// Get the user ID from the access token
		$userId = $accessToken->getUserIdentifier();
		if ($userId === null) {
			return $this->createErrorResponse(
				401,
				'invalid_token',
				'Access token is not associated with a user'
			);
		}
		
		// Get the scopes from the access token
		$tokenScopes = $accessToken->getScopes();
		$scopeStrings = array_map(fn(Scope $scope) => (string)$scope, $tokenScopes);
		
		// Check if the token has the 'openid' scope
		if (!in_array('openid', $scopeStrings, true)) {
			return $this->createErrorResponse(
				403,
				'insufficient_scope',
				'The access token does not have the required scope'
			);
		}
		
		// Get the user entity with all claims
		$userEntity = $this->identityRepository->getUserEntityByIdentifier($userId);
		if ($userEntity === null) {
			return $this->createErrorResponse( 
				401,
				'invalid_token',
				'User not found'
			);
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
		
		// Create a successful response with the user claims
		return $this->createJsonResponse(200, $response);
	}
}

