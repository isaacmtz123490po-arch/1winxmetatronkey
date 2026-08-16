# Self-Signed Certificate Generator — Free, Browser-Based X.509 Certificate Tool

Generate **self-signed SSL/TLS certificates** instantly in your browser — no server, no installation, no data upload. Built with Rust + WebAssembly for fast, secure, fully client-side certificate generation.

**Live app:** https://mailvibi.github.io/selfsignedcert/

---

## What It Does

This tool lets you generate X.509 certificates in three modes:

| Mode | Use Case |
|------|----------|
| **Self-Signed Certificate** | Dev/test HTTPS, internal services |
| **Self-Signed CA Certificate** | Create your own root Certificate Authority |
| **CA-Signed Certificate** | Sign a leaf cert with your own CA |

All cryptographic operations run **entirely in your browser** using WebAssembly. Nothing is sent to any server.

---

## Features

- **Free and private** — no account, no upload, no tracking
- **Self-signed SSL certificate** generation in seconds
- **Custom Subject Alternative Names (SANs)** — add DNS names and IP addresses
- **Flexible expiry** — set expiry by number of days or a specific date (supports already-expired certs for testing)
- **PEM file download** — certificate, private key, and certificate chain
- **Custom file name prefix** for organized downloads
- **CA workflow** — upload your CA cert + key to sign leaf certificates
- **Works offline** — load the page once, no internet needed after that

---

## Output Files

| File | Contents |
|------|----------|
| `{prefix}_certificate.pem` | X.509 certificate (PEM format) |
| `{prefix}_privatekey.pem` | ECDSA private key, PKCS#8 (PEM format) |
| `{prefix}_chain.pem` | Leaf + CA chain (CA-Signed mode only) |

---

## How To Use

1. Open the app in your browser
2. Choose a mode: **Self-Signed**, **Self-Signed CA**, or **CA-Signed**
3. Enter a **Common Name** (e.g., `localhost`, `myapp.internal`, `*.example.com`)
4. Add **Subject Alternative Names** (DNS names or IP addresses)
5. Set the **expiry** (days from today or a specific date)
6. Click **Generate Certificate**
7. Download the `.pem` files

---

## Common Use Cases

- **Local HTTPS development** — generate a cert for `localhost` or `127.0.0.1`
- **Internal network services** — create certs for private hostnames
- **Docker / Kubernetes** — generate certificates for internal service mesh
- **Testing TLS/mTLS** — generate expired, wildcard, or multi-SAN certificates
- **Learning PKI** — experiment with certificate authorities and chains
- **OpenSSL alternative** — no need to remember complex `openssl req` commands

---

## Technical Details

- **Language:** Rust 2024 edition
- **Framework:** [Yew 0.22](https://yew.rs/) — React-like WASM framework
- **Build tool:** [Trunk](https://trunkrs.dev/)
- **Crypto:** NIST P-256 (secp256r1), ECDSA + SHA-256 — pure Rust, no OpenSSL
- **Key libraries:** `p256`, `ecdsa`, `x509-cert`, `pkcs8`, `der`
- **Output:** Static site, deployable to GitHub Pages or any CDN

### Build from Source

```bash
# Prerequisites
rustup target add wasm32-unknown-unknown
cargo install trunk

# Development server (http://localhost:8080)
trunk serve

# Production build → docs/
bash build.sh

# Lint / format / test
cargo clippy
cargo fmt
cargo test
```

---

## Security & Privacy

- **Zero server-side processing** — your private keys never leave your browser
- **No telemetry, no cookies, no analytics**
- **Pure Rust cryptography** — no dependency on OpenSSL or system libraries
- Randomness sourced from `window.crypto.getRandomValues()` (browser CSPRNG)

---

## License

See [LICENSE](LICENSE) file.
