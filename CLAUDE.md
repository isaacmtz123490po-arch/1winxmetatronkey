# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build Commands

This project uses [Trunk](https://trunkrs.dev/) to bundle Rust/WASM for the browser.

```bash
# Development server with hot-reload
trunk serve

# Production build (outputs to docs/ for GitHub Pages)
bash build.sh
# equivalent to:
trunk build --release --dist docs --public-url "/selfsignedcert/"

# Lint
cargo clippy

# Format
cargo fmt

# Run tests (unit tests in Rust source)
cargo test
```

Trunk requires the `wasm32-unknown-unknown` target:
```bash
rustup target add wasm32-unknown-unknown
cargo install trunk
```

## Architecture

**Stack:** Rust 2024 edition + [Yew 0.22](https://yew.rs/) (client-side rendering via WebAssembly)

Yew is a React-like component framework for Rust. Components are functions returning `Html`, state is managed with hooks (`use_state`, `use_effect`, etc.), and event callbacks use Yew's `Callback` type.

**Entry point:** `src/main.rs` renders the root `App` component via `yew::Renderer::<App>::new().render()`. `index.html` is the HTML shell that Trunk injects the WASM bundle into.

**Output:** `docs/` — Trunk writes the production build here; this directory is served by GitHub Pages at `https://mailvibi.github.io/selfsignedcert/`.
