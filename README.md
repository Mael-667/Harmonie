# Harmonie

A self-hosted, open-source chat platform with text channels, voice channels and customizable themes — without a freemium model getting in the way.

> *"De la discorde naît l'Harmonie"*

## Why

Most online chat apps lock basic features behind paywalls. Harmonie is the opposite take: spin it up on your own server, invite your friends, and that's it. Servers, channels, voice rooms, custom themes — all included, none gated.

## Stack

PHP 8.4 / Symfony 8 on the server side, Twig for rendering, MySQL via Doctrine, a ReactPHP WebSocket server for the real-time layer. Everything runs through Docker Compose.

## Run it

```bash
git clone https://github.com/Mael-667/Harmonie.git
cd Harmonie
docker compose up --build
```

That's the whole setup — Composer install, database creation and migrations all run on first boot.

| Service | URL |
|---|---|
| App | http://localhost:8000 |
| WebSocket | ws://localhost:443 |
| phpMyAdmin | http://localhost:8080 |

## How it's organized

Standard Symfony layout. The interesting bits:

- `src/Entity/` — the five core models: `User`, `Server`, `Channel`, `Message`, `Theme`
- `src/Controller/` — HTTP routes and IPC endpoints called by the WebSocket server
- `websocket/` — standalone ReactPHP process handling live messages and voice signaling
- `templates/` — Twig views, dark by default, restyled per-user via the theme system

## Theming

Themes are stored as JSON CSS rules tied to a user. Anyone can publish their own to the gallery, others pick from it, and a usage counter tracks the popular ones. The default look is carbon black (`#161616`), crimson highlights (`#A81848`) and off-white text (`#F0DEB8`).

## Status

Active work-in-progress. Built as the capstone project for the French *Développeur Web et Web Mobile* qualification — so it's also a public record of how I went about the design, the data model and the real-time bits.

Specs and design files live in `extra/` if you want the longer story.

## Author

Me 🐐
