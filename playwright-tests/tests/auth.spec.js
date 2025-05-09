// playwright-tests/tests/auth.spec.js
import { test, expect } from '@playwright/test';
import { handleLogin, buildAuthUrl, exchangeCodeForTokens } from './utils';
import crypto from 'crypto';

test('should complete Authorization Code Flow', async ({ page }) => {
  const clientId = 'playwright_test_client';
  const redirectUri = 'https://localhost:9999/callback'; // Must match config/clients.yml
  const scope = 'openid email profile';
  const state = 'random_state_string'; // Generate a unique state
  const nonce = 'random_nonce_string'; // Generate a unique nonce for OIDC
  const clientSecret = 'very_secret_playwright_password';
  const username = `${process.env.PHPBB_USERNAME}`;
  const password = `${process.env.PHPBB_PASSWORD}`;

  // 1. Build Authorization URL
  const authUrl = buildAuthUrl({
    baseUrl: process.env.BASE_URL,
    clientId,
    redirectUri,
    scope,
    state,
    nonce
  });
  
  // 2. Navigate to Authorization URL
  await page.goto(authUrl.toString());

  // 3. Handle phpBB Login
  const url = await handleLogin(page, { 
    username, 
    password, 
    redirectUri 
  });
  
  // 4. Validate the authorization response
  const code = url.searchParams.get('code');
  const returnedState = url.searchParams.get('state');

  expect(code).toBeTruthy();
  expect(returnedState).toBe(state);

  // 5. Exchange Code for Tokens
  const tokenData = await exchangeCodeForTokens(page.request, {
    baseUrl: process.env.BASE_URL,
    code,
    redirectUri,
    clientId,
    clientSecret
  });

  // 6. Validate Tokens
  expect(tokenData.access_token).toBeTruthy();
  expect(tokenData.token_type).toBe('Bearer');
  expect(tokenData.id_token).toBeTruthy(); // For OIDC
  console.log('Access Token:', tokenData.access_token);

  // 7. Use Access Token (Example: Call UserInfo)
  const userInfoUrl = `${process.env.BASE_URL}/oauth2/v1/userinfo`;
  const userInfoResponse = await page.request.get(userInfoUrl, {
    headers: {
      'Authorization': `Bearer ${tokenData.access_token}`
    }
  });

  if (!userInfoResponse.ok()) {
    console.error('UserInfo request failed:', await userInfoResponse.text());
  }
  expect(userInfoResponse.ok()).toBeTruthy();
  const userInfoData = await userInfoResponse.json();
  expect(userInfoData.sub).toBeTruthy(); // 'sub' claim is required
});

test('should complete Authorization Code Flow with PKCE', async ({ page }) => {
  const clientId = 'playwright_test_client';
  const redirectUri = 'https://localhost:9999/callback';
  const scope = 'openid email profile';
  const state = `state_${Date.now()}`;
  const nonce = `nonce_${Date.now()}`;
  
  // Generate PKCE code verifier and challenge
  const codeVerifier = crypto.randomBytes(32).toString('base64url');
  const codeChallenge = crypto
    .createHash('sha256')
    .update(codeVerifier)
    .digest('base64url');
  
  // 1. Build Authorization URL with PKCE parameters
  const authUrl = buildAuthUrl({
    baseUrl: process.env.BASE_URL,
    clientId,
    redirectUri,
    scope,
    state,
    nonce
  });
  
  // Add PKCE parameters
  authUrl.searchParams.set('code_challenge', codeChallenge);
  authUrl.searchParams.set('code_challenge_method', 'S256');
  
  // 2. Navigate to Authorization URL
  await page.goto(authUrl.toString());

  // 3. Handle phpBB Login
  const url = await handleLogin(page, { 
    username: process.env.PHPBB_USERNAME, 
    password: process.env.PHPBB_PASSWORD, 
    redirectUri 
  });
  
  // 4. Validate the authorization response
  const code = url.searchParams.get('code');
  const returnedState = url.searchParams.get('state');

  expect(code).toBeTruthy();
  expect(returnedState).toBe(state);

  // 5. Exchange Code for Tokens with PKCE verifier
  const tokenUrl = `${process.env.BASE_URL}/oauth2/v1/token`;
  const tokenResponse = await page.request.post(tokenUrl, {
    form: {
      grant_type: 'authorization_code',
      code: code,
      redirect_uri: redirectUri,
      client_id: clientId,
      client_secret: 'very_secret_playwright_password', // Add client secret for authentication
      code_verifier: codeVerifier
    }
  });

  expect(tokenResponse.ok()).toBeTruthy();
  const tokenData = await tokenResponse.json();

  // 6. Validate Tokens
  expect(tokenData.access_token).toBeTruthy();
  expect(tokenData.token_type).toBe('Bearer');
  expect(tokenData.id_token).toBeTruthy();
});

test('should handle invalid client credentials error', async ({ page }) => {
  const clientId = 'playwright_test_client';
  const invalidClientSecret = 'invalid_secret';
  const redirectUri = 'https://localhost:9999/callback';
  const scope = 'openid email profile';
  const state = `state_${Date.now()}`;
  const nonce = `nonce_${Date.now()}`;

  // 1. Build Authorization URL
  const authUrl = buildAuthUrl({
    baseUrl: process.env.BASE_URL,
    clientId,
    redirectUri,
    scope,
    state,
    nonce
  });
  
  // 2. Navigate to Authorization URL
  await page.goto(authUrl.toString());

  // 3. Handle phpBB Login
  const url = await handleLogin(page, { 
    username: process.env.PHPBB_USERNAME, 
    password: process.env.PHPBB_PASSWORD, 
    redirectUri 
  });
  
  // 4. Get the code from the redirect URL
  const code = url.searchParams.get('code');
  expect(code).toBeTruthy();

  // 5. Try to exchange code with invalid client secret
  const tokenUrl = `${process.env.BASE_URL}/oauth2/v1/token`;
  const tokenResponse = await page.request.post(tokenUrl, {
    form: {
      grant_type: 'authorization_code',
      code: code,
      redirect_uri: redirectUri,
      client_id: clientId,
      client_secret: invalidClientSecret,
    }
  });

  // 6. Expect an error response
  expect(tokenResponse.ok()).toBeFalsy();
  expect(tokenResponse.status()).toBe(401);
  
  const errorData = await tokenResponse.json();
  expect(errorData.error).toBe('invalid_client');
});


test('should verify ID token and its claims', async ({ page, request }) => {
  const clientId = 'playwright_test_client';
  const clientSecret = 'very_secret_playwright_password';
  const redirectUri = 'https://localhost:9999/callback';
  const scope = 'openid email profile';
  const state = `state_${Date.now()}`;
  const nonce = `nonce_${Date.now()}`;

  // 1. Get tokens via authorization code flow
  const authUrl = buildAuthUrl({
    baseUrl: process.env.BASE_URL,
    clientId,
    redirectUri,
    scope,
    state,
    nonce
  });
  
  await page.goto(authUrl.toString());
  const url = await handleLogin(page, { 
    username: process.env.PHPBB_USERNAME, 
    password: process.env.PHPBB_PASSWORD, 
    redirectUri 
  });
  
  const code = url.searchParams.get('code');
  const tokenData = await exchangeCodeForTokens(request, {
    baseUrl: process.env.BASE_URL,
    code,
    redirectUri,
    clientId,
    clientSecret
  });
  
  expect(tokenData.id_token).toBeTruthy();
  
  // 2. Get JWKS URI from discovery endpoint
  const discoveryUrl = `${process.env.BASE_URL}/oauth2/v1/discovery`;
  const discoveryResponse = await request.get(discoveryUrl);
  expect(discoveryResponse.ok()).toBeTruthy();
  
  const discoveryData = await discoveryResponse.json();
  expect(discoveryData.jwks_uri).toBeTruthy();
  
  // 3. Decode and verify the ID token
  // Note: Actual JWT verification would require a JWT library
  // This is a simplified test that just checks the structure
  const idToken = tokenData.id_token;
  const parts = idToken.split('.');
  expect(parts.length).toBe(3); // Header, payload, signature
  
  // Decode the payload (base64url decode)
  const payload = JSON.parse(Buffer.from(parts[1], 'base64url').toString());
  
  // 4. Validate required claims
  expect(payload.iss).toBeTruthy(); // Issuer
  expect(payload.sub).toBeTruthy(); // Subject
  expect(payload.aud).toBe(clientId); // Audience
  expect(payload.exp).toBeGreaterThan(Date.now() / 1000); // Expiration time
  expect(payload.iat).toBeLessThanOrEqual(Date.now() / 1000); // Issued at
  expect(payload.nonce).toBe(nonce); // Nonce should match our request
  
  // Check additional claims based on scope
  if (scope.includes('email')) {
    expect(payload.email).toBeTruthy();
  }
  
  if (scope.includes('profile')) {
    // phpBB username or other profile data
    expect(payload.preferred_username).toBeTruthy();
  }
});