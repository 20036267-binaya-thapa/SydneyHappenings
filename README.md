# SydneyHappenings

SydneyHappenings is a community and cultural events platform for Sydney, developed for
ICT726 Web Development Assignment 4.

The website allows residents to discover and register for events, while organisers can
publish and manage their own events. Administrators are provided with additional tools
to manage users, events and other platform data.

## Stack

- PHP 8.x
- MySQL / MariaDB via XAMPP
- PDO with prepared statements
- HTML5
- CSS3
- Vanilla JavaScript
- Git and GitHub
- No external PHP or front-end frameworks

The application runs locally at:

```text
http://localhost/eventManagement/
```

## Setup

1. Copy the project into the XAMPP `htdocs` folder.

   Example on macOS:

   ```text
   /Applications/XAMPP/xamppfiles/htdocs/eventManagement/
   ```

2. Start Apache and MySQL from the XAMPP control panel.

3. Create a database named:

   ```text
   eventhub
   ```

4. Import `schema.sql` into the `eventhub` database.

5. Import `seed.sql` to load sample users, categories, venues, events,
   registrations and reviews.

6. Confirm the database settings in `config.php`.

   The default local XAMPP configuration is:

   ```text
   Host: localhost
   Database: eventhub
   Username: root
   Password: empty
   ```

7. If the project folder name is changed, update `BASE_URL` in `config.php`.

8. Visit:

   ```text
   http://localhost/eventManagement/
   ```

## Test Credentials

The seeded accounts use the following test password:

```text
Password123
```

| Role      | Email                              | Password    |
|-----------|------------------------------------|-------------|
| Admin     | admin@sydneyhappenings.example     | Password123 |
| Organiser | marcus.webb@example.com            | Password123 |
| Organiser | priya.natarajan@example.com        | Password123 |
| Attendee  | jack.thompson@example.com          | Password123 |
| Attendee  | sofia.rossi@example.com            | Password123 |
| Attendee  | ben.nguyen@example.com             | Password123 |

New accounts created through `register.php` are created as attendees.

An administrator can change a user's role through the admin user management interface.

## Folder Structure

```text
/eventManagement
  /assets
    /css/style.css
    /js/main.js
    /uploads

  /includes
    db.php
    auth_guard.php
    validate.php
    functions.php
    header.php
    footer.php

  /account
    profile.php
    my-registrations.php
    change-password.php

  /organiser
    dashboard.php
    my-events.php
    event-form.php
    event-attendees.php
    event-delete.php

  /admin
    dashboard.php
    users.php
    user-edit.php
    events.php
    categories.php
    venues.php
    enquiries.php

  index.php
  events.php
  event.php
  login.php
  register.php
  logout.php
  register-for-event.php
  cancel-registration.php
  review-form.php
  review-delete.php
  contact.php
  about.php
  privacy.php
  404.php
  sitemap.php
  robots.txt
  schema.sql
  seed.sql
  config.php
```

## Database

SydneyHappenings uses eight main database tables:

- `users`
- `categories`
- `venues`
- `events`
- `registrations`
- `reviews`
- `wishlists`
- `enquiries`

These tables store account information, event details, venues, categories,
bookings, attendance, reviews, saved events and contact enquiries.

## Features

### Public Event Discovery

Visitors can:

- Browse published events
- Search for events by keyword
- Filter events by category, suburb, date and price
- View current and upcoming events
- View past events
- View detailed event information
- See event capacity and remaining availability
- View event reviews and ratings

### User Accounts

Users can:

- Register for an account
- Log in and log out
- Edit their profile
- Change their password
- Maintain an authenticated session

### Event Registration

Attendees can:

- Register for published events
- Select ticket quantity
- Receive a unique booking reference
- View current, upcoming and past bookings
- Cancel an eligible booking before the event starts
- View ticket and booking information

Registration is checked server-side to confirm that:

1. The user is logged in
2. The event exists
3. The event is published
4. The event has not started
5. The user does not already have an active booking
6. Enough capacity remains for the requested tickets

Database constraints provide additional protection against duplicate registrations.

### Wishlist

Logged-in users can save events to their wishlist for later viewing and remove them
when they are no longer interested.

### Attendance Management

Organisers can view attendees registered for their own events.

After an event starts, organisers can mark registrations as:

- Attended
- No-show

Attendance information is also used to determine review eligibility.

### Review System

Users who attended an event can submit a review after the event has finished.

The review system supports:

- One review per user per event
- Star ratings
- Written comments
- Editing an existing review
- Deleting an existing review
- Average event rating display
- Review count display

Users who cancelled their booking or were marked as no-show cannot review the event.

### Organiser Features

Organisers can:

- Create events
- Edit their own events
- Delete their own events
- Upload event images
- Set event capacity and price
- Publish, draft or cancel events
- View registrations
- Manage attendee attendance

Ownership checks prevent organisers from editing events belonging to another organiser.

### Administrator Features

Administrators can:

- View the admin dashboard
- Manage users
- Change user roles
- Activate or deactivate users
- Manage events
- Manage categories
- Manage venues
- View contact enquiries

## Validation

Forms use both browser-side HTML validation and server-side PHP validation.

Examples include:

- Required fields
- Email format validation
- Password requirements
- Duplicate email prevention
- Date and time validation
- Event capacity validation
- Price validation
- Ticket quantity validation
- Category and venue validation
- Image type and file-size validation

Validation errors are displayed clearly without unnecessarily clearing the user's input.

## Security

Security measures include:

- Password hashing
- PDO prepared statements
- Session-based authentication
- Role-based access control
- Organiser ownership checks
- CSRF protection on POST requests
- Server-side validation
- Output escaping using `htmlspecialchars()`
- Secure image type validation
- Randomised uploaded image filenames
- Session inactivity handling
- Database constraints for important relationships

## Accessibility

Accessibility considerations include:

- Semantic HTML5 landmarks
- Skip-to-content navigation
- Properly labelled form controls
- `aria-describedby` for validation messages
- `role="alert"` for important feedback
- Keyboard-accessible controls
- Visible focus states
- Alternative text for images
- Logical heading structure
- Accessible data tables using `<caption>` and `<th scope>`
- Responsive layouts for different screen sizes

## SEO

SEO features include:

- Unique page titles
- Meta descriptions
- Canonical URLs
- Event URLs using slugs
- Open Graph metadata
- Schema.org Event structured data using JSON-LD
- XML sitemap
- `robots.txt`
- Semantic HTML structure

## Event Timing

The website uses the `Australia/Sydney` timezone.

Events remain in the current/upcoming section while they are running and are moved
to the past event section after their end time.

Registrations cannot be created or cancelled after the event has started.

Reviews become available to eligible attendees after the event has finished.

## Known Limitations

- No payment gateway is integrated. Event prices are informational only.
- Registering for a paid event does not process a financial transaction.
- No email notifications are sent for bookings, reminders or password resets.
- The website is designed primarily as a locally hosted academic project using XAMPP.
- `BASE_URL` is configured manually and must be changed if the project directory name changes.
- `robots.txt` works correctly when deployed at the root of a domain, but localhost
  subfolder hosting may not reflect production crawler behaviour.

## Project Information

**Project:** SydneyHappenings  
**Unit:** ICT726 Web Development  
**Assessment:** Assignment 4  
**Institution:** King's Own Institute  
**Student:** Binaya Thapa, Rejina Thapa, Marjana Akter Swarnaly
**Student ID:** 20036267