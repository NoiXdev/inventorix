# Security Policy

## Supported Versions

Only the latest minor release receives security fixes. Older versions are
unsupported — please upgrade before reporting issues against them.

| Version | Supported |
| ------- | --------- |
| latest  | yes       |
| older   | no        |

## Reporting a Vulnerability

**Please do not open a public GitHub issue for security vulnerabilities.**

Report privately via one of:

- GitHub's [private vulnerability reporting](https://github.com/NoiXdev/inventorix/security/advisories/new) (preferred).
- Email: `security@noix.dev`

Include:

- A description of the issue and its potential impact.
- Reproduction steps or proof-of-concept.
- The affected version (commit SHA or release tag).
- Your name/handle for credit (optional).

## Response Timeline

- Acknowledgement of your report within **3 business days**.
- Initial assessment and severity rating within **7 business days**.
- Fix and coordinated disclosure timeline communicated after triage.

We follow coordinated disclosure: please give us reasonable time to ship a fix
before publishing details.

## Scope

In scope:

- The Inventorix application code in this repository.
- Default Docker image published from this repository.

Out of scope:

- Third-party dependencies (please report upstream, then notify us if a patched
  version is available).
- Self-hosted deployments that have been modified or misconfigured.
- Findings that require physical access, social engineering, or compromised
  developer machines.
