# Brass & Blade — Laravel version

This version uses only Laravel Blade, Tailwind CSS, and a small plain JavaScript file. It does not use React, TypeScript, Vinext, Inertia, Alpine, or Livewire.

## Main files

- `routes/web.php` — pages, form validation, booking storage, and order storage
- `resources/views/home.blade.php` — the complete website HTML
- `resources/css/app.css` — Tailwind theme and reusable styles
- `resources/js/app.js` — simple cart, product filters, and booking tabs

## Start the website

```bash
npm run build
php artisan serve
```

Then open `http://127.0.0.1:8000`.

## Run with Docker

From the `da-project` folder, run:

```bash
docker compose up --build -d
```

Then open `http://localhost:8000`.

Useful Docker commands:

```bash
docker compose logs -f web
docker compose down
```

Bookings and orders are stored in the `laravel_storage` Docker volume, so they remain available after restarting or rebuilding the container. Running `docker compose down` keeps this data; adding `--volumes` deletes it.

## Saved submissions

Laravel saves submitted data here:

- `storage/app/private/bookings.jsonl`
- `storage/app/private/orders.jsonl`

Each line is one booking or order in JSON format. This keeps the project easy to run without configuring MySQL. The routes can be switched to a database later.

## Run checks

```bash
php artisan test
```
