# ltw02g09

## Features

**All users:**
- [x] Register a new account.
- [x] Log in and out.
- [x] Edit their profile, including name, username, password, and profile photo.

**Members:**
- [x] Browse the schedule of available fitness classes, filtering by type, trainer, day, or time.
- [x] Enroll in and cancel enrollment from upcoming classes, subject to capacity limits.
- [x] View trainer profiles, including their specializations and the classes they teach.
- [x] Check the current availability of equipment in the main training area.
- [x] Leave ratings and reviews for classes they have attended.

**Trainers:**
- [x] Manage their public profile, including bio, specializations, and certifications.
- [x] View the roster of members enrolled in their classes.
- [x] Track and manage their assigned class schedule.

**Admins:**
- [x] Manage members and trainers (create, update, and deactivate accounts).
- [x] Manage the class catalog (create, edit, and remove classes) and assign trainers to them.
- [x] Manage equipment in the main training area (add, update availability status, and remove items).
- [x] Elevate a user to admin status.
- [x] Oversee and ensure the smooth operation of the entire system.

**Extra:**
- [x] Personal Training Bookings — members can browse trainer availability and book one-on-one personal training sessions through an interactive calendar.
- [x] Trainer Analytics Dashboard — trainers can view pending bookings, enrolled members per schedule slot, and manage their PT availability.
- [x] Admin Analytics Dashboard — admins can view gym-wide metrics such as most popular classes, recent sign-ups, and equipment usage at a glance.
- [x] REST API — public JSON endpoints for class schedules, equipment availability, and trainer listings, usable by third-party apps.
- [x] Real-time Equipment Updates — the equipment page supports manual refresh and optional auto-refresh every 30 seconds via Ajax.

## Running

```bash
sqlite3 database/database.db < database/database.sql
php -S localhost:9000
```

Then open [http://localhost:9000](http://localhost:9000) in your browser.

## Credentials

| Role    | Username     | Password |
|---------|-------------|----------|
| Admin   | admin        | p4s5w0rd |
| Member  | member       | 1234     |
| Trainer | joao.silva   | 1234     |
| Trainer | ana.santos   | 1234     |
| Trainer | miguel.costa | 1234     |
