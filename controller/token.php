<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\controller;

use Exception;
use HK47196\OIDCProvider\Services\OAuth2Service;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7Server\ServerRequestCreator;
use phpbb\request\request;
use Psr\Http\Message\ResponseFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\HttpFoundation\Response;

class token
{
	protected OAuth2Service $oauth2Service;
	protected request $request;

	private ServerRequestCreator $creator;
	private HttpFoundationFactoryInterface $httpFoundationFactory;
	private ResponseFactoryInterface $responseFactory;

	public function __construct(OAuth2Service                           $oauth2Service,
	                            request                                 $request,
	                            ServerRequestCreator $creator,
	                            HttpFoundationFactoryInterface          $httpFoundationFactory,
	                            ResponseFactoryInterface                $responseFactory)
	{
		$this->oauth2Service = $oauth2Service;
		$this->request = $request;
		$this->creator = $creator;
		$this->httpFoundationFactory = $httpFoundationFactory;
		$this->responseFactory = $responseFactory;
	}

	public function handle(): Response
	{
		//TODO: perf??
		$this->request->enable_super_globals();

		$serverRequest = $this->creator->fromGlobals();
		$serverResponse = $this->responseFactory->createResponse();

		$server = $this->oauth2Service->getAuthorizationServer();
		try {
			$response = $server->respondToAccessTokenRequest($serverRequest, $serverResponse);
		} catch (OAuthServerException $e) {
			error_log('OAuthServerException: ' . $e->getMessage());
			$response = $e->generateHttpResponse($serverResponse);
		} catch (Exception $e) {
			error_log(__FILE__ . ':' . __LINE__ . ': ' . 'Exception: ' . $e->getMessage());
			$response = $serverResponse->withStatus(500);
		}

		return $this->httpFoundationFactory->createResponse($response);
	}
}

