// playwright-tests/tests/discovery.spec.js
import { test, expect } from '@playwright/test';

test('discovery endpoint should return valid OpenID configuration', async ({ request }) => {
  const discoveryUrl = `${process.env.BASE_URL}/oauth2/v1/discovery`;
  const discoveryResponse = await request.get(discoveryUrl);
  
  expect(discoveryResponse.ok()).toBeTruthy();
  const discoveryData = await discoveryResponse.json();
  
  // Check required OIDC discovery fields
  expect(discoveryData.issuer).toBeTruthy();
  expect(discoveryData.authorization_endpoint).toBeTruthy();
  expect(discoveryData.token_endpoint).toBeTruthy();
  expect(discoveryData.userinfo_endpoint).toBeTruthy();
  expect(discoveryData.jwks_uri).toBeTruthy();
  expect(discoveryData.scopes_supported).toBeTruthy();
  expect(discoveryData.response_types_supported).toContain('code');
  expect(discoveryData.subject_types_supported).toBeTruthy();
  expect(discoveryData.id_token_signing_alg_values_supported).toBeTruthy();
  
  // Verify the URLs match expected patterns
  expect(discoveryData.authorization_endpoint).toContain('/oauth2/v1/authorize');
  expect(discoveryData.token_endpoint).toContain('/oauth2/v1/token');
  expect(discoveryData.userinfo_endpoint).toContain('/oauth2/v1/userinfo');
  
  expect(discoveryData.grant_types_supported).toContain('authorization_code');
  
  // This field might be added by a reverse proxy in production
  if (discoveryData.token_endpoint_auth_methods_supported) {
    expect(Array.isArray(discoveryData.token_endpoint_auth_methods_supported)).toBe(true);
  }
});

test('jwks endpoint should return valid JWK set', async ({ request }) => {
  const discoveryUrl = `${process.env.BASE_URL}/oauth2/v1/discovery`;
  const discoveryResponse = await request.get(discoveryUrl);
  expect(discoveryResponse.ok()).toBeTruthy();
  
  const discoveryData = await discoveryResponse.json();
  const jwksUri = discoveryData.jwks_uri;
  expect(jwksUri).toBeTruthy();
  
  const jwksResponse = await request.get(jwksUri);
  expect(jwksResponse.ok()).toBeTruthy();
  
  const jwksData = await jwksResponse.json();
  
  // Check for required JWK Set structure
  expect(jwksData.keys).toBeTruthy();
  expect(Array.isArray(jwksData.keys)).toBe(true);
  expect(jwksData.keys.length).toBeGreaterThan(0);
  
  const key = jwksData.keys[0];
  expect(key.kty).toBeTruthy();
  expect(key.kid).toBeTruthy();
  expect(key.alg).toBeTruthy();
});

test('should handle scope validation in authorization request', async ({ page }) => {
  const clientId = 'playwright_test_client';
  const redirectUri = 'https://localhost:9999/callback';
  const invalidScope = 'openid invalid_scope';
  const state = `state_${Date.now()}`;
  
  const authUrl = new URL(`${process.env.BASE_URL}/oauth2/v1/authorize`);
  authUrl.searchParams.set('response_type', 'code');
  authUrl.searchParams.set('client_id', clientId);
  authUrl.searchParams.set('redirect_uri', redirectUri);
  authUrl.searchParams.set('scope', invalidScope);
  authUrl.searchParams.set('state', state);
  
  // Intercept the redirect to the callback URI to prevent connection error
  await page.route((url) => url.href.startsWith(redirectUri), (route) => {
    route.fulfill({ status: 200, body: 'Redirect captured' });
  });

  await page.goto(authUrl.toString());
  
  // We expect either:
  // 1. A redirect with error=invalid_scope
  // 2. An error page from the OIDC provider
  
  const url = page.url();
  if (url.startsWith(redirectUri)) {
    const parsedUrl = new URL(url);
    const error = parsedUrl.searchParams.get('error');
    const errorDescription = parsedUrl.searchParams.get('error_description');
    const returnedState = parsedUrl.searchParams.get('state');
    
    expect(error).toBe('invalid_scope');
    expect(errorDescription).toBeTruthy();
    expect(returnedState).toBe(state);
  } else {
    await expect(page.locator('body')).toContainText(/invalid scope|scope/i);
  }
});

test('should handle missing parameters in authorization request', async ({ page }) => {
  const clientId = 'playwright_test_client';
  // Missing redirect_uri parameter which is required
  
  const authUrl = new URL(`${process.env.BASE_URL}/oauth2/v1/authorize`);
  authUrl.searchParams.set('response_type', 'code');
  authUrl.searchParams.set('client_id', clientId);
  // No redirect_uri parameter
  authUrl.searchParams.set('scope', 'openid');
  
  await page.goto(authUrl.toString());
  
  await expect(page.locator('body')).toContainText(/error|invalid|missing|redirect/i);
});