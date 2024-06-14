<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\Grants;

use JsonException;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use League\OAuth2\Server\ResponseTypes\RedirectResponse;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Nyholm\Psr7\Response as Psr7Response;
use Symfony\Component\HttpFoundation\Request;

class OpenIdAuthCodeGrant extends AuthCodeGrant
{
	/**
	 * {@inheritdoc}
	 * @throws JsonException|OAuthServerException
	 */
	public function completeAuthorizationRequest(AuthorizationRequest $authorizationRequest): ResponseTypeInterface|RedirectResponse
	{
		/** @var RedirectResponse $response */
		$response = parent::completeAuthorizationRequest($authorizationRequest);
		$request = Request::createFromGlobals();

		if ($request->query->has('nonce')) {
			$httpResponse = $response->generateHttpResponse(new Psr7Response());

			$redirectUri = $httpResponse->getHeader('Location');
			$parsed = parse_url($redirectUri[0]);
			parse_str($parsed['query'], $query);
			$authCodePayload = json_decode($this->decrypt($query['code']), true, 512, JSON_THROW_ON_ERROR);
			$authCodePayload['nonce'] = $request->get('nonce');
			$query['code'] = $this->encrypt(json_encode($authCodePayload, JSON_THROW_ON_ERROR));
			$parsed['query'] = http_build_query($query);
			$response->setRedirectUri($this->unparse_url($parsed));
		}

		return $response;
	}

	/**
	 * Inverse of parse_url
	 *
	 * @param mixed $parsed_url
	 * @return string
	 */
	private function unparse_url($parsed_url)
	{
		$scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
		$pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass'] : '';
		$pass = ($user || $pass) ? "$pass@" : '';
		$path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
		$query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
		$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
		return "$scheme$user$pass$host$port$path$query$fragment";
	}
}