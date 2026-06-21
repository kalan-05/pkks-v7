# AGENTS.md

## Проект

Проект: `pkks-v7` / «Правовая контора К. Сопрачева»

Project type: static HTML/CSS/JS site.
No Node package.
No build step.
Primary entrypoint: index.html.

## Базовые правила

- One task = one contour.
- Start from clean tree.
- Stop on unexpected dirty state.
- Do not mix unrelated blocks in one commit.
- Visual changes require owner visual acceptance before commit.
- Always use CSS cache-busting when changing linked CSS.
- Do not add dependencies or package.json without owner approval.
- Do not deploy or touch hosting without written permission.

## Разрешённые общие зоны

- `index.html`
- `css/*.css`

## Ограниченные зоны

- `js/**`
- `img/**`
- `font/**`
- `README.md`
- `package.json`
- `package-lock.json`
- `pnpm-lock.yaml`
- `yarn.lock`
- production/deploy/hosting

## Проверки

- `git diff --check`
- mojibake scan
- changed-files allowlist
- browser visual smoke for visual tasks
- responsive viewports
- horizontal overflow check

## Viewports

- `2560`
- `1920`
- `1440`
- `1200`
- `992`
- `768`
- `480`
- `390`
- `360`
- `320`
