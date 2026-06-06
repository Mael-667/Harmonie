# Harmonie

A self-hosted, open-source chat platform with text channels, voice channels and customizable themes. Without a freemium model getting in the way.

> *"De la discorde naît l'Harmonie"*

## Why

Most online chat apps lock basic features behind paywalls. Harmonie is the opposite take: spin it up on your own server, invite your friends, and that's it. Servers, channels, invite links, voice rooms, custom themes... All included, none gated.

## Stack

PHP 8.4 / Symfony 8 on the server side, MySQL via Doctrine, and a ReactPHP WebSocket server for the real-time layer.

The front end is **hybrid**: Twig renders the static, server-side pages (home, login, register, settings), while the dynamic chat page (`/app`) is a **React 19** single-page app bundled by **Vite**. Symfony serves a thin Twig shell with a root container; React mounts into it and talks to the back end over HTTP (Symfony controllers) and over WebSocket for everything real-time (send / edit / delete). The whole thing runs through Docker Compose.

## Run it

```bash
git clone https://github.com/Mael-667/Harmonie.git
cd Harmonie
npm install && npx vite build      # bundle the React chat page → public/build/islands.js
docker compose up --build
```

On the Docker side, Composer install, database creation and migrations all run on first boot. The React bundle is built separately with Vite — `public/build/` is gitignored, so (re)build it after any change under `src/React/`, or run `npx vite build --watch` while developing.

| Service | URL |
|---|---|
| App | http://localhost:80 |
| WebSocket | ws://localhost:443 |
| phpMyAdmin | http://localhost:8080 |

## How it's organized

Standard Symfony layout. The interesting bits:

- `src/Entity/` — the six core models: `User`, `Server`, `Channel`, `Message`, `ServerInvitation`, `Theme`
- `src/Controller/` — HTTP routes and IPC endpoints called by the WebSocket server
- `src/Service/` — pure business logic, e.g. `PermissionManager` for per-server roles
- `src/React/` — the React components of the `/app` chat page (compiled by Vite to `public/build/`)
- `websocket/` — standalone ReactPHP process handling live messages and voice signaling
- `templates/` — Twig views, dark by default; `/app` is a thin shell that hosts the React app

## Theming

Themes are stored as JSON CSS rules tied to a user. Anyone can publish their own to the gallery, others pick from it, and a usage counter tracks the popular ones. The default look is carbon black (`#161616`), crimson highlights (`#A81848`) and off-white text (`#F0DEB8`).

## AI Usage Disclosure

I used AI only for commits and quick debugging/questions. As it is a school project, I wrote every line of code myself, for better or worse ☝️ 

## Status

Active work-in-progress. Built as the capstone project for the French *Développeur Web et Web Mobile* qualification, so it's also a public record of how I went about the design, the data model and the real-time bits.

Specs and design files live in `extra/` if you want the longer story.

## Author

Me 🐐
