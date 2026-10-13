# Security

Please report vulnerabilities privately to **azlanaziz@rayaz.org** instead of opening a public issue. Include steps to reproduce and the version (`App::VERSION`). We aim to reply within a few days and to fix confirmed issues quickly.

Hardening checklist for deployments: serve over HTTPS, set `TZL_TRUST_PROXY=1` only behind a proxy you control, change the generated admin password immediately, keep the `data/` folder outside any public web root and back it up.
