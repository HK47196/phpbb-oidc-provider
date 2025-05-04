<?php
declare(strict_types=1);

namespace HK47196\OIDCProvider\controller;

use HK47196\OIDCProvider\Manager\ScopeManagerInterface;
use HK47196\OIDCProvider\ValueObject\Scope;
use phpbb\config\config;
use phpbb\request\request;
use phpbb\symfony_request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class discovery
{
    protected request $request;
    protected RouterInterface $router;
    protected ScopeManagerInterface $scopeManager;
    protected config $config;

    public function __construct(
        request $request,
        RouterInterface $router,
        ScopeManagerInterface $scopeManager,
        config $config
    ) {
        $this->request = $request;
        $this->router = $router;
        $this->scopeManager = $scopeManager;
        $this->config = $config;
    }

    public function handle(): Response
    {
        // Get the base URL for the issuer
        $baseUrl = $this->getBaseUrl();
        
        // Build the discovery document
        $discoveryDocument = [
            // Required fields
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl . '/oauth2/v1/authorize',
            'token_endpoint' => $baseUrl . '/oauth2/v1/token',
            'userinfo_endpoint' => $baseUrl . '/oauth2/v1/userinfo',
            'jwks_uri' => $baseUrl . '/jwks.json',
            
            // Supported scopes
            'scopes_supported' => $this->getSupportedScopes(),
            
            // Supported response types
            'response_types_supported' => [
                'code',
                'id_token',
                'token id_token'
            ],
            
            // Supported grant types
            'grant_types_supported' => [
                'authorization_code',
                'implicit',
                'refresh_token'
            ],
            
            // Subject types supported
            'subject_types_supported' => ['public'],
            
            // ID Token signing algorithms
            'id_token_signing_alg_values_supported' => ['RS256'],
            
            // Additional fields
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
            
            // OIDC service documentation
            'service_documentation' => $baseUrl . '/docs/oidc',
            
            // UI locales supported
            'ui_locales_supported' => ['en'],
            
            // OP policy URL
            'op_policy_uri' => $baseUrl . '/privacy',
            
            // OP terms of service
            'op_tos_uri' => $baseUrl . '/terms',
        ];
        
        // Set the Content-Type header to application/json
        $response = new \phpbb\json_response($discoveryDocument);
        
        return $response;
    }
    
    /**
     * Get the base URL for the issuer
     * 
     * @return string The base URL
     */
    private function getBaseUrl(): string
    {
        // Get the server name and protocol
        $serverName = $this->request->server('SERVER_NAME', '');
        $https = $this->request->server('HTTPS', '');
        $protocol = (!empty($https) && $https !== 'off') ? 'https' : 'http';
        
        // Get the server port
        $port = (int)$this->request->server('SERVER_PORT', 80);
        
        // Only include port in URL if it's non-standard
        $portString = '';
        if (($protocol === 'http' && $port !== 80) || ($protocol === 'https' && $port !== 443)) {
            $portString = ':' . $port;
        }
        
        // Get the script path
        $scriptPath = $this->request->server('SCRIPT_NAME', '');
        $scriptDir = dirname($scriptPath);
        $scriptDir = ($scriptDir === '/') ? '' : $scriptDir;
        
        // Build the base URL
        return $protocol . '://' . $serverName . $portString . $scriptDir;
    }
    
    /**
     * Get the list of supported scopes
     * 
     * @return array The list of supported scopes
     */
    private function getSupportedScopes(): array
    {
        // Get all scopes from the scope manager
        $scopes = $this->scopeManager->findAll();
        
        // Convert scope objects to strings
        return array_map(function (Scope $scope) {
            return (string)$scope;
        }, $scopes);
    }
}