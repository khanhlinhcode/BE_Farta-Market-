# Staging APP_KEY and Admin MFA Rotation

Use this runbook only during an approved staging maintenance window. Never paste
the current or replacement key, MFA secret, recovery codes, cookies, or database
values into terminal output, CI logs, tickets, or chat.

## Preconditions

1. Confirm the target is staging, not production.
2. Take and verify a restorable database backup.
3. Confirm at least one administrator can generate a current TOTP code.
4. Generate the replacement key directly in the approved secret manager. Do not
   generate it in a command whose output is recorded.
5. Keep the current key available only in the secret manager for the temporary
   `APP_PREVIOUS_KEYS` transition.

## Rotation

1. Set the replacement as `APP_KEY` and the current key as the only temporary
   entry in `APP_PREVIOUS_KEYS` in the staging runtime configuration.
2. Restart the API and worker using the normal controlled deployment procedure.
3. Check `/up` and confirm the application can read existing encrypted MFA data.
4. In the API container, validate decryption without writing:

   ```bash
   php artisan security:reencrypt-user-secrets --dry-run
   ```

5. If and only if the dry run succeeds, re-encrypt the stored MFA fields with the
   replacement key:

   ```bash
   php artisan security:reencrypt-user-secrets
   ```

6. Log in with password and a fresh authenticator code. Do not reuse a TOTP from
   the same 30-second window.
7. Regenerate recovery codes through the authenticated Admin MFA endpoint and
   replace the protected out-of-repository recovery file. Existing recovery-code
   hashes use the previous application key and cannot be migrated safely.
8. Invalidate existing database sessions after the successful MFA verification.
   Report only the number of deleted sessions, never their payloads or IDs.
9. Run the Admin login, MFA, logout, and protected-route smoke tests.
10. Remove `APP_PREVIOUS_KEYS` only after encrypted MFA fields, other encrypted
    application data, and the rollback plan have been verified.

## Failure handling

- If the dry run or re-encryption fails, stop. Keep `APP_PREVIOUS_KEYS` attached,
  preserve the backup, and do not purge sessions or recovery material.
- If TOTP fails after re-encryption, roll back the runtime configuration through
  the secret manager. Do not clear MFA fields as a first response.
- Never disable MFA or create a bypass account to complete the rotation.

## Required evidence

- Backup identifier and completion status.
- Deployment revision, health result, and command exit codes.
- Count of MFA records checked and re-encrypted.
- Confirmation that a fresh TOTP worked, recovery codes were regenerated, and
  old sessions were invalidated.
- Confirmation that no secret value appeared in logs or the change report.
