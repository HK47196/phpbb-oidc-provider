import { test, expect } from '@playwright/test';
import { handleLogin, buildAuthUrl, exchangeCodeForTokens } from './utils';

test('should obtain and use a refresh token', async ({ page, request }) => {
  const clientId = 'playwright_test_client';
  const clientSecret = 'very_secret_playwright_password';
  const redirectUri = 'https://localhost:9999/callback';
  // Request 'offline_access' to ensure we get a refresh token
  const scope = 'openid email profile offline_access';
  const state = `state_${Date.now()}`;
  const nonce = `nonce_${Date.now()}`;

  // 1. Start Authorization Flow
  const authUrl = buildAuthUrl({
    baseUrl: process.env.BASE_URL,
    clientId,
    redirectUri,
    scope,
    state,
    nonce
  });

  await page.goto(authUrl.toString());

  // 2. Login
  const url = await handleLogin(page, {
    username: process.env.PHPBB_USERNAME,
    password: process.env.PHPBB_PASSWORD,
    redirectUri
  });

  const code = url.searchParams.get('code');
  expect(code).toBeTruthy();

  // 3. Exchange Code for Tokens
  const tokenData = await exchangeCodeForTokens(request, {
    baseUrl: process.env.BASE_URL,
    code,
    redirectUri,
    clientId,
    clientSecret
  });

  // Verify we got a refresh token
  expect(tokenData.refresh_token).toBeTruthy();
  const firstAccessToken = tokenData.access_token;
  console.log('Initial Access Token:', firstAccessToken);
  console.log('Refresh Token:', tokenData.refresh_token);

  // 4. Use Refresh Token to get a NEW Access Token
  // This exercises the metadata saving and retrieval logic introduced in the commit
  const tokenUrl = `${process.env.BASE_URL}/oauth2/v1/token`;
  const refreshResponse = await request.post(tokenUrl, {
    form: {
      grant_type: 'refresh_token',
      refresh_token: tokenData.refresh_token,
      client_id: clientId,
      client_secret: clientSecret,
      scope: 'openid email profile' // Optional, but good practice to verify scope handling
    }
  });

  expect(refreshResponse.ok()).toBeTruthy();
  const refreshedData = await refreshResponse.json();

  expect(refreshedData.access_token).toBeTruthy();
  expect(refreshedData.access_token).not.toBe(firstAccessToken); // Should be a new token
  console.log('Refreshed Access Token:', refreshedData.access_token);

  // 5. Verify the NEW Access Token works
  const userInfoUrl = `${process.env.BASE_URL}/oauth2/v1/userinfo`;
  const userInfoResponse = await request.get(userInfoUrl, {
    headers: {
      'Authorization': `Bearer ${refreshedData.access_token}`
    }
  });

  expect(userInfoResponse.ok()).toBeTruthy();
  const userInfoData = await userInfoResponse.json();
  expect(userInfoData.sub).toBeTruthy();
});
