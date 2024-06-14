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

	public function __construct(OAuth2Service                  $oauth2Service,
	                            user                           $user,
	                            request                        $request,
	                            ClientManagerInterface         $clientManager,
	                            ServerRequestCreator           $creator,
	                            HttpMessageFactoryInterface    $httpMessageFactory,
	                            HttpFoundationFactoryInterface $httpFoundationFactory,
	                            ResponseFactoryInterface       $responseFactory)
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
	}

	public function handle(): Response
	{
		//TODO: perf??
		$this->request->enable_super_globals();
		$serverRequest = $this->creator->fromGlobals();
		$serverResponse = $this->responseFactory->createResponse();
		$authServer = $this->oauth2Service->getAuthorizationServer();
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
			// Check if the user is logged into phpBB
			if ($userId === ANONYMOUS) {
				// Redirect to phpBB login page with proper redirection handling
				$redirectUrl = append_sid("{$this->request->server('REQUEST_URI')}");
				login_box($redirectUrl);
				// After login, phpBB should redirect back to the original request
				// The login_box function will handle the redirection internally
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
			} else {
				$response = $this->responseFactory->createResponse(500, 'An error occurred');
			}
		}
		return $this->httpFoundationFactory->createResponse($response);
	}
}
