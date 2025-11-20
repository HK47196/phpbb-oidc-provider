<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\controller;

use Exception;
use HK47196\OIDCProvider\Manager\ClientManagerInterface;
use HK47196\OIDCProvider\Services\OAuth2Service;
use HK47196\OIDCProvider\Entity\User as UserEntity;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use phpbb\config\config;
use phpbb\request\request;
use phpbb\user;
use Psr\Http\Message\ResponseFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Response;

class authorization
{
	protected OAuth2Service $oauth2Service;
	protected user $user;
	protected request $request;
	private ClientManagerInterface $clientManager;
	private ServerRequestCreator $creator;
	protected HttpMessageFactoryInterface $httpMessageFactory;
	private HttpFoundationFactoryInterface $httpFoundationFactory;
	private ResponseFactoryInterface $responseFactory;
	protected config $config;

	public function __construct(OAuth2Service                  $oauth2Service,
	                            user                           $user,
	                            request                        $request,
	                            ClientManagerInterface         $clientManager,
	                            ServerRequestCreator           $creator,
	                            HttpMessageFactoryInterface    $httpMessageFactory,
	                            HttpFoundationFactoryInterface $httpFoundationFactory,
	                            ResponseFactoryInterface       $responseFactory,
	                            config                         $config)
	{
		$this->oauth2Service = $oauth2Service;
		$this->user = $user;
		$this->request = $request;

		$this->clientManager = $clientManager;

		$this->httpMessageFactory = $httpMessageFactory;
		$this->httpFoundationFactory = $httpFoundationFactory;
		/** @var Psr17Factory $responseFactory */
		$this->responseFactory = $responseFactory;
		$this->creator = $creator;
		$this->config = $config;
	}

	public function handle(): Response
	{
		$this->request->enable_super_globals();
		$serverRequest = $this->creator->fromGlobals();
		$serverResponse = $this->responseFactory->createResponse();
		$authServer = $this->oauth2Service->getAuthorizationServer();

		// Early validation: check for required parameters before processing
		$queryParams = $serverRequest->getQueryParams();

		// Extract state parameter early for error handling (RFC 6749 Section 4.1.2.1)
		// State must be preserved in error responses if it was in the request
		$stateParameter = $queryParams['state'] ?? null;

		if (empty($queryParams['redirect_uri'])) {
			// Per OAuth 2.0 spec (RFC 6749 Section 4.1.2.1), if redirect_uri is invalid or missing,
			// the authorization server MUST NOT redirect and should inform the resource owner
			$errorResponse = $this->responseFactory->createResponse(400);
			$errorResponse->getBody()->write(
				'<html><head><title>OAuth 2.0 Error</title></head><body>' .
				'<h1>Invalid Authorization Request</h1>' .
				'<p><strong>Error:</strong> invalid_request</p>' .
				'<p><strong>Description:</strong> The redirect_uri parameter is missing or invalid.</p>' .
				'</body></html>'
			);
			return $this->httpFoundationFactory->createResponse($errorResponse);
		}

		try {
			// Validate the authorization request
			$authRequest = $authServer->validateAuthorizationRequest($serverRequest);
			if ($authRequest->getCodeChallengeMethod() === 'plain') {
				$client = $this->clientManager->find($authRequest->getClient()->getIdentifier());
				if ($client === null) {
					error_log("Client not found: {$authRequest->getClient()->getIdentifier()}");
					throw OAuthServerException::invalidClient($serverRequest);
				}
				if (!$client->isPlainTextPkceAllowed()) {
					throw OAuthServerException::invalidRequest('code_challenge_method',
						'Plain code challenge method is not allowed for this client');
				}
			}


			$userId = (int)$this->user->data['user_id'];
			if ($userId === ANONYMOUS) {
				$redirectUrl = append_sid("{$this->request->server('REQUEST_URI')}");
				login_box($redirectUrl);
				return new Response('phpBB Login', 302, ['Location' => $redirectUrl]);
			}

			// Set the user on the AuthorizationRequest
			$userEntity = new UserEntity();
			$userEntity->setIdentifier((string)$userId);

			$authRequest->setUser($userEntity);
			$authRequest->setAuthorizationApproved(true);

			// Complete the authorization request
			$response = $authServer->completeAuthorizationRequest($authRequest, $serverResponse);
		} catch (Exception $e) {
			if ($e instanceof OAuthServerException) {
				$response = $e->generateHttpResponse($serverResponse);

				// Per RFC 6749 Section 4.1.2.1, if state was provided in request,
				// it MUST be included in error redirects
				if ($stateParameter !== null && $response->getStatusCode() === 302) {
					$location = $response->getHeader('Location')[0] ?? null;
					if ($location !== null) {
						// Parse the location URL and add state parameter
						$separator = strpos($location, '?') !== false ? '&' : '?';
						$location .= $separator . 'state=' . urlencode($stateParameter);
						$response = $response->withHeader('Location', $location);
					}
				}
			} else {
				$response = $this->responseFactory->createResponse(500, 'An error occurred');
			}
		}
		return $this->httpFoundationFactory->createResponse($response);
	}
}
