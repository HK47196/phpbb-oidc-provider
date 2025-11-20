# Playwright Testing Guide for phpBB OIDC Provider Extension

This guide provides instructions and best practices for writing Playwright end-to-end tests for the phpBB OIDC Provider extension.

## 1. Prerequisites

*   **Playwright Setup:** Ensure Playwright is initialized in the `playwright-tests` directory and configured as per the initial setup (using `.env` for `BASE_URL`).
*   **Running phpBB Instance:** A local phpBB 3.3.x instance with the OIDC Provider extension enabled and accessible at the `BASE_URL` specified in `playwright-tests/.env` (e.g., `https://forums.rpghq.internal`).

## 2. Test Client Setup

For testing, you need to define a dedicated OIDC client within the extension's configuration.

1.  **Edit `config/clients.yml`:** Add a new client entry specifically for testing.
2.  **Example Test Client Configuration:**

    ```yaml
    clients:
      # ... other clients ...
      - name: 'Playwright Test Client'
        id: 'playwright_test_client'
        secret: 'very_secret_playwright_password' # Use a strong, unique secret
        redirect_uris:
          - 'https://localhost:9999/callback' # Example callback URL for test runner
          # Add other redirect URIs if needed by specific tests
        scopes:
          - 'openid'
          - 'email'
          - 'profile'
        grant_types:
          - 'authorization_code'
          # - 'refresh_token' # Enable if refresh token tests are needed
        active: true
        allow_plain_text_pkce: false # Recommended for security
    ```

    *   **Important:** Choose a secure `secret`. The `redirect_uris` should match where your test application or Playwright expects the callback.

## 3. Test User Setup

*   **(Placeholder):** Add guidance here on creating and managing dedicated phpBB user accounts for testing. Consider using unique usernames/emails to avoid conflicts. Decide if users should be pre-created or created dynamically by tests.

## 4. Core Test Flows

### 4.1. Authorization Code Flow

This is the primary flow for user authentication. Here's a conceptual Playwright implementation:

```javascript
// playwright-tests/tests/auth.spec.js
import { test, expect } from '@playwright/test';

test('should complete Authorization Code Flow', async ({ page }) => {
  const clientId = 'playwright_test_client';
  const redirectUri = 'https://localhost:9999/callback'; // Must match config/clients.yml
  const scope = 'openid email profile';
  const state = 'random_state_string'; // Generate a unique state
  const nonce = 'random_nonce_string'; // Generate a unique nonce for OIDC

  // 1. Construct Authorization URL
  const authUrl = new URL(`${process.env.BASE_URL}/oauth2/v1/authorize`);
  authUrl.searchParams.set('response_type', 'code');
  authUrl.searchParams.set('client_id', clientId);
  authUrl.searchParams.set('redirect_uri', redirectUri);
  authUrl.searchParams.set('scope', scope);
  authUrl.searchParams.set('state', state);
  authUrl.searchParams.set('nonce', nonce);
  // Add PKCE parameters if used

  // 2. Navigate to Authorization URL
  await page.goto(authUrl.toString());

  // 3. Handle phpBB Login
  // Check if already on the login page, otherwise the redirect should lead there
  await expect(page).toHaveURL(/.*\/ucp\.php\?mode=login.*/); // Adjust if login URL differs after redirect
  await page.locator('#username').fill('test_username'); // Use test user credentials
  await page.locator('#password').fill('test_password');
  await page.locator('[name="login"]').click();

  // 4. Handle Consent Screen (if applicable)
  // (Placeholder): Add steps to find and click the 'Approve'/'Authorize' button
  // Example: await page.locator('#allow_button').click();
  // await expect(page).toHaveURL(/.*callback.*/); // Expect redirect back to client

  // 5. Verify Redirect and Extract Code
  // Playwright might need a way to intercept the final redirect or have a simple
  // listener at the redirectUri to capture the code and state.
  // For example, if redirectUri hosts a simple page:
  await expect(page).toHaveURL(new RegExp(`^${redirectUri}`));
  const url = new URL(page.url());
  const code = url.searchParams.get('code');
  const returnedState = url.searchParams.get('state');

  expect(code).toBeTruthy();
  expect(returnedState).toBe(state);

  // 6. Exchange Code for Tokens (using API request context)
  const tokenUrl = `${process.env.BASE_URL}/oauth2/v1/token`;
  const tokenResponse = await page.request.post(tokenUrl, {
    form: {
      grant_type: 'authorization_code',
      code: code,
      redirect_uri: redirectUri,
      client_id: clientId,
      client_secret: 'very_secret_playwright_password', // Use the configured secret
      // Add PKCE code_verifier if used
    }
  });

  expect(tokenResponse.ok()).toBeTruthy();
  const tokenData = await tokenResponse.json();

  // 7. Validate Tokens
  expect(tokenData.access_token).toBeTruthy();
  expect(tokenData.token_type).toBe('Bearer');
  expect(tokenData.id_token).toBeTruthy(); // For OIDC
  // Add more specific token validation (e.g., decoding JWT, checking claims, nonce)

  // 8. Use Access Token (Example: Call UserInfo)
  const userInfoUrl = `${process.env.BASE_URL}/oauth2/v1/userinfo`;
  const userInfoResponse = await page.request.get(userInfoUrl, {
    headers: {
      'Authorization': `Bearer ${tokenData.access_token}`
    }
  });

  expect(userInfoResponse.ok()).toBeTruthy();
  const userInfoData = await userInfoResponse.json();
  expect(userInfoData.sub).toBeTruthy(); // 'sub' claim is required
  // Add checks for other expected claims based on scope (e.g., email)
});
```

## 5. Key Selectors and URLs

*   **Base URL:** `process.env.BASE_URL` (from `.env`)
*   **Authorization Endpoint:** `/oauth2/v1/authorize`
*   **Token Endpoint:** `/oauth2/v1/token`
*   **UserInfo Endpoint:** `/oauth2/v1/userinfo`
*   **Discovery Endpoint:** `/oauth2/v1/discovery`
*   **phpBB Login Path:** `/ucp.php?mode=login`
*   **Username Input:** `#username`
*   **Password Input:** `#password`
*   **Login Submit Button:** `[name="login"]`
*   **Consent Approve Button:** `(Placeholder: Add selector if applicable)`

## 6. Configuration Notes

*   Review `config/identity.yml` for issuer details and token lifetime settings.
*   Review `config/parameters.yml` for other relevant extension parameters.
*   Ensure the test client in `config/clients.yml` has the correct `redirect_uris` and enabled `grant_types`.

## 7. Error Handling

*   **(Placeholder):** Document common error scenarios (e.g., invalid credentials, invalid client, invalid scope, consent denied) and how to test for them. Include expected error codes, messages, or redirect parameters.