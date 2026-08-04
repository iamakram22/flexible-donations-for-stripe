## Summary

<!-- Describe the change and why it's needed. Reference any related issues, e.g. "Fixes #12". -->

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Refactor / cleanup
- [ ] Documentation
- [ ] Release / packaging

## Checklist

- [ ] `php -l` passes on all changed PHP files
- [ ] Tested one-time donation flow
- [ ] Tested recurring donation flow (at least monthly)
- [ ] Tested in both live and test mode (if payment logic changed)
- [ ] Tested both submission methods: AJAX and REST API (if submission logic changed)
- [ ] Webhook flow verified: `checkout.session.completed` marks donation completed and sends email (if webhook logic changed)
- [ ] All user-visible strings use the `flexible-donations-for-stripe` text domain
- [ ] Inputs sanitized, outputs escaped, nonces verified
- [ ] No hardcoded organization names/emails/phone numbers — uses settings or `get_bloginfo()`
- [ ] `readme.txt` changelog and/or `readme.md` updated for user-facing changes
- [ ] No secrets or API keys included

## Screenshots / test evidence

<!-- If applicable. -->
