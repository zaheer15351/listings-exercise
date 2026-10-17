# Street Listings

A small property-listings application: a Laravel 13 API with a Vue 3 front end. It lists properties for sale, organised by branch, with filtering and a detail view.

This repository is the starting point for a technical exercise. **Your task is described in [`TASK.md`](TASK.md).** This README covers what's here and how to run it.

## Requirements

- PHP 8.4
- Composer
- Node 20+ and npm
- SQLite (bundled with PHP — no separate database server needed)

## Getting started

```bash
# 1. Install dependencies
#    composer install also creates .env from .env.example (if missing),
#    creates database/database.sqlite, and sets APP_KEY when it's blank.
composer install
npm install

# 2. Database — migrate and seed
php artisan migrate --seed

# 3. Run — in two terminals, or use the combined dev script
php artisan serve      # API + app at http://127.0.0.1:8000
npm run dev            # Vite dev server for the front end
```

Then open <http://127.0.0.1:8000>.

## Running the checks

```bash
php artisan test              # PHPUnit feature/unit tests
vendor/bin/pint --test        # Code style (Laravel Pint)
vendor/bin/phpstan analyse    # Static analysis (Larastan, level 5)
npm run build                 # Compiles the Vue pages - no PHP test does
```

All four run in CI (`.github/workflows/ci.yml`) on push and pull request, and all pass on a fresh clone. If PHPStan crashes on memory locally (Herd's CLI PHP defaults to 128M), add `--memory-limit=1G`.

## How it's laid out

```
app/
  Actions/               SendAlerts (emails alerts, used by instant and digest)
  Console/Commands/      PublishListing, SendAlertDigests
  Enums/                 PropertyType, ListingStatus, AlertFrequency
  Events/                ListingWentLive
  Listeners/             CreateAlertsForListing
  Http/
    Controllers/         ListingController, SavedSearchController, AlertController
    Requests/            ListingIndexRequest (filter validation), StoreSavedSearchRequest
    Resources/           ListingResource, BranchResource, SavedSearchResource, AlertResource
    Middleware/          ActAsDemoUser (auth stub — see below)
                         HandleInertiaRequests (shared props)
  Models/                Branch, Listing, SavedSearch, Alert
  Notifications/         AlertNotification (the alert email)
database/
  factories/             Branch, Listing (with states), SavedSearch, Alert
  migrations/            branches, listings, saved_searches, alerts
  seeders/               DatabaseSeeder
resources/js/
  pages/                 Listings/Index, Listings/Show, SavedSearches/Index, Alerts/Index
  components/            AppLayout, ListingCard, ListingFilters, Pagination, SaveSearchPanel
  app.js                 Inertia entry point
routes/                  web.php, console.php (digest schedule)
tests/Feature/           one file per behaviour; see the alert and saved-search tests
tests/Unit/              SavedSearchSummaryTest (pure formatting, no database)
```

### The domain

- **Branch** — a name and a region.
- **Listing** — address, price, bedrooms, bathrooms, `property_type`, `status` (`draft` / `live` / `under_offer` / `sold`), a branch, and a `listed_at` date.

### The stack

Laravel + **Inertia** + Vue 3 + Tailwind — the same shape as our internal apps. There is no separate JSON API: controllers return `Inertia::render(...)` with props, and API Resources (`ListingResource`, `BranchResource`) define the shape those props take.

| Method | Path                 | Page              | Notes                                                      |
| ------ | -------------------- | ----------------- | ---------------------------------------------------------- |
| GET    | `/`                  | `Listings/Index`  | Live listings, paginated. Filters: `property_type`, `min_price`, `max_price`, `min_bedrooms`, `region`, `per_page`. |
| GET    | `/listings/{listing}`| `Listings/Show`   | A single live listing. Non-live listings 404.              |
| GET    | `/saved-searches`    | `SavedSearches/Index` | The user's saved searches.                             |
| POST   | `/saved-searches`    | -                 | Save a search (from the listings page).                    |
| DELETE | `/saved-searches/{id}` | -               | Delete one of the user's searches.                         |
| GET    | `/alerts`            | `Alerts/Index`    | The user's alerts, newest first.                           |

Filters live in the query string, so a search is shareable, bookmarkable and survives the back button. `pages/Listings/Index.vue` seeds its form from the `filters` prop and re-issues a `router.get` on submit.

### Authentication

Auth is **stubbed**. A real deployment would authenticate requests properly; to keep this exercise focused on the feature rather than on auth plumbing, every request is resolved as the seeded demo user (`demo@street.example`) via `App\Http\Middleware\ActAsDemoUser`, and shared to the front end as the `auth.user` prop. Build any user-scoped work against `$request->user()` / `auth()->user()` as you normally would — it will return the demo user.

Note there is no user until the database is seeded, so run `php artisan migrate --seed` before you start.

## Saved-search alerts

A buyer saves the search they are looking at and gets an email when a new listing matching it goes live. Mail goes to the log (`MAIL_MAILER=log`), so emails appear in `storage/logs/laravel.log`. Decisions and trade-offs are in [`NOTES.md`](NOTES.md).

### Try it

1. Open <http://127.0.0.1:8000>, filter (e.g. an area and a max price), choose **Email me: As soon as it is listed** and **Save this search**.
2. List the drafts and put a matching one live - this stands in for the admin screen that would publish a listing:
   ```bash
   php artisan listings:publish        # lists drafts with price, beds, type and region
   php artisan listings:publish <id>   # puts that draft live and raises alerts
   ```
3. Open **Alerts** to see it, and `storage/logs/laravel.log` for the email.
4. Searches saved as **Daily digest** wait for the digest, scheduled daily. Nothing runs the scheduler here, so send it by hand:
   ```bash
   php artisan alerts:send-digests
   ```

Links in emails use `APP_URL`, so set it to the address you serve the app on.

---

Your task: **[`TASK.md`](TASK.md)**.
