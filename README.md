This was created for internal usage and is a WIP, it is not ready to be used by others.

## Environment Variables

### OAUTH_ENC_KEY

The `OAUTH_ENC_KEY` environment variable is required for cryptographic operations within the OAuth2/OpenID Connect server. It is used for:

- Encrypting and decrypting authorization codes
- Encrypting and decrypting refresh tokens
- Other sensitive data that needs to be securely stored or transmitted

**Format**: The value should be a base64-encoded 32-byte (256-bit) random key.

**Example of generating a suitable key**:
```bash
# Generate a random 32-byte key and encode it as base64
php -r "echo base64_encode(random_bytes(32));"
```

**Important**: The application automatically base64-decodes this value before using it for cryptographic operations. Always provide the key in base64-encoded format.

If you encounter a "Ciphertext is too short" error, it typically indicates an issue with this encryption key, such as:
- The key is missing or empty
- The key is not properly base64-encoded
- The decoded key is not the expected length (32 bytes)
