// playwright-tests/tests/utils.js
import { expect } from '@playwright/test';

/**
 * Handles phpBB login flow for OIDC tests
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {Object} options - Login options
 * @param {string} options.username - Username for login
 * @param {string} options.password - Password for login
 * @param {string} options.redirectUri - Expected redirect URI after successful login
 * @returns {Promise<URL>} The URL after successful redirect
 */
export async function handleLogin(page, { username, password, redirectUri }) {
  // Assert that the login form is present
  await expect(page.locator('#username')).toBeVisible();
  
  // Fill in the username and password
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.waitForTimeout(1000);

  let url;
  const redirectRequest = page.waitForRequest((request) => {
    url = new URL(request.url());
    return request.url().startsWith(redirectUri) && request.method() === 'GET';
  });
  
  // Click the login button by name
  await page.locator('input[name="login"]').click();

  // Wait for the redirect request to be completed
  await redirectRequest;
  
  return url;
}

/**
 * Builds an authorization URL for OIDC flow
 * @param {Object} params - Authorization parameters
 * @param {string} params.baseUrl - Base URL of the OIDC provider
 * @param {string} params.clientId - Client ID
 * @param {string} params.redirectUri - Redirect URI
 * @param {string} params.scope - Space-separated scopes
 * @param {string} params.state - State parameter for CSRF protection
 * @param {string} params.nonce - Nonce parameter for OIDC
 * @returns {URL} The constructed authorization URL
 */
export function buildAuthUrl({ baseUrl, clientId, redirectUri, scope, state, nonce }) {
  const authUrl = new URL(`${baseUrl}/oauth2/v1/authorize`);
  authUrl.searchParams.set('response_type', 'code');
  authUrl.searchParams.set('client_id', clientId);
  authUrl.searchParams.set('redirect_uri', redirectUri);
  authUrl.searchParams.set('scope', scope);
  authUrl.searchParams.set('state', state);
  authUrl.searchParams.set('nonce', nonce);
  
  return authUrl;
}

/**
 * Exchanges an authorization code for tokens
 * @param {import('@playwright/test').APIRequestContext} request - Playwright request context
 * @param {Object} params - Token exchange parameters
 * @param {string} params.baseUrl - Base URL of the OIDC provider
 * @param {string} params.code - Authorization code
 * @param {string} params.redirectUri - Redirect URI
 * @param {string} params.clientId - Client ID
 * @param {string} params.clientSecret - Client secret
 * @returns {Promise<Object>} Token response data
 */
export async function exchangeCodeForTokens(request, { baseUrl, code, redirectUri, clientId, clientSecret }) {
  const tokenUrl = `${baseUrl}/oauth2/v1/token`;
  const tokenResponse = await request.post(tokenUrl, {
    form: {
      grant_type: 'authorization_code',
      code: code,
      redirect_uri: redirectUri,
      client_id: clientId,
      client_secret: clientSecret,
    }
  });

  if (!tokenResponse.ok()) {
    console.error(`Token exchange failed: ${tokenResponse.status()} ${tokenResponse.statusText()}`);
    console.error(await tokenResponse.text());
  }

  expect(tokenResponse.ok()).toBeTruthy();
  return await tokenResponse.json();
}