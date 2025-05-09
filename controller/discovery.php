<?php

declare(strict_types=1);

namespace HK47196\OIDCProvider\controller;

use HK47196\OIDCProvider\Manager\ScopeManagerInterface;
use HK47196\OIDCProvider\ValueObject\Scope;
use phpbb\config\config;
use phpbb\request\request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

class discovery
{
    protected request $request;
    protected ScopeManagerInterface $scopeManager;
    protected config $config;
    protected array $identityConfig;

    public function __construct(
        request $request,
        ScopeManagerInterface $scopeManager,
        config $config
    ) {
        $this->request = $request;
        $this->scopeManager = $scopeManager;
        $this->config = $config;
        
        // Load identity configuration directly from the YAML file
        $identityYmlPath = __DIR__ . '/../config/identity.yml';
        if (!file_exists($identityYmlPath)) {
            throw new RuntimeException('Identity configuration file not found at: ' . $identityYmlPath);
        }
        
        $this->identityConfig = Yaml::parse(file_get_contents($identityYmlPath));
    }

    public function handle(): Response
    {
        $issuer = $this->identityConfig['issuer'] ?? '';
        
        // Require issuer configuration
        if (empty($issuer)) {
            throw new RuntimeException('The issuer is not configured in config/identity.yml. Please set the "issuer" parameter with a valid URL.');
        }
        
        $endpoints = $this->identityConfig['endpoints'] ?? [];
        
        // Build complete endpoint URLs
        $authorizationEndpoint = $this->buildEndpointUrl($issuer, $endpoints['authorization'] ?? '/oauth2/v1/authorize');
        $tokenEndpoint = $this->buildEndpointUrl($issuer, $endpoints['token'] ?? '/oauth2/v1/token');
        $userinfoEndpoint = $this->buildEndpointUrl($issuer, $endpoints['userinfo'] ?? '/oauth2/v1/userinfo');
        
        // For JWKS URI, we need to determine if it's relative to the server root or a full URL
        $jwksUri = $endpoints['jwks'] ?? '/.well-known/jwks.json';
        if (strpos($jwksUri, 'http') === 0) {
            // It's already a full URL
            $jwksEndpoint = $jwksUri;
        } else if (strpos($jwksUri, '/') === 0) {
            // It's relative to the server root
            $rootUrl = $this->extractRootUrl($issuer);
            $jwksEndpoint = $rootUrl . $jwksUri;
        } else {
            // It's relative to the issuer
            $jwksEndpoint = rtrim($issuer, '/') . '/' . $jwksUri;
        }
        
        // Optional endpoints
        $serviceDocumentation = $this->buildEndpointUrl($issuer, $endpoints['service_documentation'] ?? '/docs/oidc');
        $opPolicyUri = $this->buildEndpointUrl($issuer, $endpoints['op_policy_uri'] ?? '/privacy');
        $opTosUri = $this->buildEndpointUrl($issuer, $endpoints['op_tos_uri'] ?? '/terms');

        $discoveryDocument = [
            'issuer' => $issuer,
            'authorization_endpoint' => $authorizationEndpoint,
            'token_endpoint' => $tokenEndpoint,
            'userinfo_endpoint' => $userinfoEndpoint,
            'jwks_uri' => $jwksEndpoint,

            'scopes_supported' => $this->getSupportedScopes(),

            'response_types_supported' => [
                'code',
                'id_token',
                'token id_token'
            ],

            'grant_types_supported' => [
                'authorization_code',
                'implicit',
                'refresh_token'
            ],

            'subject_types_supported' => ['public'],

            'id_token_signing_alg_values_supported' => ['RS256'],

            'claims_supported' => [
                'sub',
                'iss',
                'auth_time',
                'acr',
                'name',
                'preferred_username',
                'email',
                'picture',
                'profile',
                'id_groups'
            ],

            'service_documentation' => $serviceDocumentation,
            'ui_locales_supported' => ['en'],
            'op_policy_uri' => $opPolicyUri,
            'op_tos_uri' => $opTosUri,
        ];

        $jsonContent = json_encode($discoveryDocument);
        $response = new Response($jsonContent);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
    
    /**
     * Build a complete endpoint URL from the issuer and endpoint path
     */
    private function buildEndpointUrl(string $issuer, string $endpoint): string
    {
        if (strpos($endpoint, 'http') === 0) {
            // Endpoint is already a full URL
            return $endpoint;
        }
        
        // Ensure endpoint starts with '/' and issuer doesn't end with '/'
        $normalizedEndpoint = strpos($endpoint, '/') === 0 ? $endpoint : '/' . $endpoint;
        $normalizedIssuer = rtrim($issuer, '/');
        
        return $normalizedIssuer . $normalizedEndpoint;
    }
    
    /**
     * Extract the root URL (scheme + host + port) from the full issuer URL
     */
    private function extractRootUrl(string $issuer): string
    {
        $parsedUrl = parse_url($issuer);
        if ($parsedUrl === false || empty($parsedUrl['scheme']) || empty($parsedUrl['host'])) {
            throw new RuntimeException('Invalid issuer URL: ' . $issuer);
        }
        
        $rootUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        if (!empty($parsedUrl['port'])) {
            $rootUrl .= ':' . $parsedUrl['port'];
        }
        
        return $rootUrl;
    }

    /**
     * Get the list of supported scopes as strings
     */
    private function getSupportedScopes(): array
    {
        $scopes = $this->scopeManager->findAll();
        return array_map(function (Scope $scope) {
            return (string)$scope;
        }, $scopes);
    }
}
