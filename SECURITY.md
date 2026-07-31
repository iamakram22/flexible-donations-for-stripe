# Security Policy

## Reporting a Vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

Please report vulnerabilities privately using GitHub's Security Advisory feature:

- [Report a vulnerability](https://github.com/iamakram22/stripe-custom-donations/security/advisories/new)

Include:

- The affected version(s)
- A description of the vulnerability and its impact
- Steps to reproduce (without exposing real data or keys)
- Whether you have a proposed fix

## What to never share

- Stripe API keys (`sk_*`, `pk_*`) or webhook secrets (`whsec_*`) — treat these as secrets
- Live donor email addresses or payment data
- Database credentials

## Supported Versions

Security fixes are backported to the latest stable release published on WordPress.org. Subscribe to the plugin page to be notified of releases.
