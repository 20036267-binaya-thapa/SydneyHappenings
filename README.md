# SydneyHappenings

A community and cultural events platform for Sydney, built for ICT726 Web Development,
Assignment 4. Independent organisers publish events, residents browse and register for
a place, and administrators manage the platform.

## Stack

- PHP 8.x, procedural with light OOP, no frameworks
- MySQL / MariaDB via XAMPP, accessed through PDO with prepared statements
- Hand-written HTML5, CSS3, and vanilla JavaScript - no external front-end libraries
- Runs at `http://localhost/eventManagement/`

## Setup

1. Copy the project into your XAMPP `htdocs` folder (this repository already assumes
   the folder name `eventManagement` - see `BASE_URL` in `config.php` if you rename it).
2. Start Apache and MySQL from the XAMPP control panel.
3. Create a database named `eventhub` (via phpMyAdmin, or `CREATE DATABASE eventhub;`
   in the MySQL command line).
4. Import `schema.sql` into that database to create the six tables.
5. Import `seed.sql` to load sample categories, venues, users and events.
6. Confirm `config.php` matches your MySQL credentials. The defaults
   (`root` user, no password) match a standard XAMPP install, so no changes should be
   needed. If you ever need to move the credentials elsewhere, copy `config.example.php`
   to `config.php` and fill in the real values there - `config.php` is the one file that
   should never be shared or committed, since it holds real credentials.
7. Visit `http://localhost/eventManagement/` in your browser.

## Test credentials

Every seeded account uses the same password so they are easy to test with:

| Role      | Email                              | Password      |
|-----------|-------------------------------------|---------------|
| Admin     | admin@sydneyhappenings.example      | Password123   |
| Organiser | marcus.webb@example.com             | Password123   |
| Organiser | priya.natarajan@example.com         | Password123   |
| Attendee  | jack.thompson@example.com           | Password123   |
| Attendee  | sofia.rossi@example.com             | Password123   |
| Attendee  | ben.nguyen@example.com              | Password123   |

New accounts created through `register.php` are always attendees. To make someone an
organiser, log in as the admin and change their role on `/admin/user-edit.php`.

## Folder structure

```
/eventManagement
  /assets
    /css/style.css        hand-written, mobile-first stylesheet
    /js/main.js            nav toggle and delete-confirmation script
    /uploads                organiser-uploaded event images, renamed on upload
  /includes
    db.php                 shared PDO connection
    auth_guard.php         session handling, login/role/ownership guards, flash messages
    validate.php           server-side validation rules, CSRF helpers, e() escaping
    functions.php          slugs, formatting, database lookups, image upload handling
    header.php / footer.php  shared page chrome, nav, SEO tags
  /account                 profile, registrations, password change (any logged-in user)
  /organiser               event CRUD and attendee management (organiser/admin)
  /admin                   users, events, categories, venues, enquiries (admin only)
  index.php, events.php, event.php   public browsing and event detail
  login.php, register.php, logout.php
  register-for-event.php, cancel-registration.php
  contact.php, about.php, privacy.php, 404.php
  sitemap.php, robots.txt
  schema.sql, seed.sql
  config.php, config.example.php
```

## Features

- Public browsing: home page, filterable/paginated event listing, event detail pages
  with live capacity counts.
- Accounts: registration, login, logout, profile editing, password change.
- Attendee flow: register and cancel for events, with a six-step server-side check
  (login, event exists, published, not started, no duplicate, capacity available) and
  a database unique constraint as a final safety net against duplicate registrations.
- Organiser flow: create/edit/delete own events, view attendee lists (names and
  registration dates only), mark attendance as attended or no-show.
- Admin flow: manage every user (including activating/deactivating accounts, with an
  admin unable to touch their own account status), manage every event, category and
  venue CRUD with soft delete for records still in use, and view contact enquiries.
- Contact form with a honeypot field against spam bots.
- Accessibility: semantic landmarks, skip link, labelled form fields with
  `aria-describedby` error text, `role="alert"` summaries and flash messages, visible
  focus outlines, and `<caption>`/`<th scope>` on data tables.
- SEO: per-page titles and meta descriptions, canonical links, Open Graph tags and
  `Event` schema.org JSON-LD on event pages, an XML sitemap, and `robots.txt`.
- Security: hashed passwords, PDO prepared statements throughout, `htmlspecialchars()`
  escaping on every echoed value, per-session CSRF tokens on every POST form, a
  30-minute session inactivity timeout, and validated/renamed file uploads.

## Known limitations

- No payment processing - all "prices" are informational only, and registering for a
  paid event does not collect any payment.
- No email notifications of any kind (confirmation emails, password reset by email,
  or reminders) - this was intentionally out of scope.
- No image assets are bundled with the project; event listings without an uploaded
  image show a plain CSS placeholder instead of a photo.
- `robots.txt` and the `Disallow` rules within it assume the site is hosted at its own
  domain root; since crawlers only ever look for `robots.txt` at the true root of a
  domain, it will not be honoured while the site lives in a subfolder like
  `/eventManagement/` on a shared host.
- `BASE_URL` in `config.php` is a plain constant, not auto-detected, so moving the
  project to a different folder name requires updating it by hand.
