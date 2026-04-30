# Deploying the Optic Read-O-Meter landing page

This `docs/` folder is the source for the landing page served at
`https://arunbrahma.com/optic-read-o-meter/`.

It works because the user site at `arunbrahma.com` (the
`iamarunbrahma.github.io` repo with the apex CNAME) automatically extends
its custom domain to every project repo on the same account that has
GitHub Pages enabled. So this repo doesn't need its own CNAME and doesn't
need a separate deploy workflow. Pages handles everything.

## One-time setup

1. Push this repo to GitHub.
2. In the repo: **Settings → Pages → Build and deployment**.
3. Source: **Deploy from a branch**.
4. Branch: `main`, folder: `/docs`.
5. Save. GitHub builds the site and reports the URL on the same page.

After the first build, every push to `main` that touches `docs/`
re-publishes automatically. No Action workflow needed.

## Verify

```bash
curl -I https://arunbrahma.com/optic-read-o-meter/
```

`HTTP/2 200` means the WordPress.org reviewer's check will pass.

## Local preview (optional)

```bash
cd docs
bundle install
bundle exec jekyll serve
# http://localhost:4000/
```
