# Contributing to Flexible Donations for Stripe

Thank you for considering contributing! This document covers how to report issues and submit pull requests.

## Reporting Issues

### Before opening an issue

1. Search the [existing issues](https://github.com/iamakram22/flexible-donations-for-stripe/issues) to see if your problem is already reported.
2. Check the troubleshooting section of the plugin's `readme.txt`.
3. Reproduce the issue with a minimal setup (default WordPress theme, no other plugins active) if possible.

### Bug reports

Use the [bug report template](https://github.com/iamakram22/flexible-donations-for-stripe/issues/new?template=bug_report.md). At minimum, include:

- WordPress version and PHP version
- Plugin version (from the plugin header or `readme.txt` Stable tag)
- Stripe mode: `live` or `test`
- Submission method setting: `AJAX` or `REST API`
- Steps to reproduce
- Expected vs. actual behavior
- Any error messages (check `wp-content/debug.log` with `WP_DEBUG` enabled)

### Feature requests

Use the [feature request template](https://github.com/iamakram22/flexible-donations-for-stripe/issues/new?template=feature_request.md). Describe the problem you are trying to solve, not just the feature.

### Security issues

**Do not open a public issue for security vulnerabilities.** Report them privately via GitHub's [Security Advisories](https://github.com/iamakram22/flexible-donations-for-stripe/security/advisories) — see `SECURITY.md`.

## Submitting Pull Requests

1. Fork the repository and create a branch from `main`:
   ```bash
   git checkout -b fix/my-fix main
   ```
2. Make one logical change per PR. Small, focused PRs get reviewed faster.
3. Follow the coding standards below.
4. Run the PHP syntax check on every file you touched:
   ```bash
   php -l path/to/file.php
   ```
   Or check all plugin files at once (requires PHP 7.4+):
   ```bash
   find . -path ./vendor -prune -o -name '*.php' -exec php -l {} +
   ```
   (On Windows PowerShell: `Get-ChildItem -Recurse -Filter *.php | Where-Object { $_.FullName -notmatch '\\vendor\\' } | ForEach-Object { php -l $_.FullName }`)
5. Test your change:
   - One-time donation and recurring donation (each interval used)
   - Both submission methods: `AJAX` and `REST API`
   - Both modes: live and test (test card `4242 4242 4242 4242`)
   - Webhook flow: `checkout.session.completed` marks the donation completed and sends the email
6. Open a pull request using the [PR template](https://github.com/iamakram22/flexible-donations-for-stripe/issues/new?template=pull_request_template.md) and complete the checklist.

## Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/).
- Use the `flexible-donations-for-stripe` text domain for all translatable strings.
- Sanitize all inputs (`sanitize_text_field`, `sanitize_email`, `floatval`, ...) and escape all outputs (`esc_html`, `esc_attr`, ...).
- Verify nonces on any authenticated request; the REST route uses nonce `fdfs_donation_nonce` via `permission_callback`.
- Do not hardcode organization names, emails, or phone numbers — read them from settings or `get_bloginfo()`.
- Add/update `readme.txt` (changelog section) and `readme.md` for any user-facing change.
- New settings need: a field callback, sanitization entry, and a handler-side default.

## Release Process

Releases are cut from `main` and published to WordPress.org. The `build-release.ps1` script (run from the plugin root) assembles the SVN package under `release/`:

```bash
powershell -File build-release.ps1
```

The ZIP inside `release/` is what gets uploaded to wordpress.org. The `vendor/` directory is intentionally gitignored (dependencies are installed via Composer) but is included in the release package.

## Commit Message Style

- Imperative mood: "Add statement descriptor setting", "Fix webhook signature check"
- One subject line, no tags required
- Reference issue numbers when applicable: "Fix #12: ..."
