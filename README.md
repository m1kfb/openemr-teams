# Provider Teams

OpenEMR module for care teams. Assign patients and staff to a team, then record each day's calls with a time in and a time out.

A team day has four calls, matching the paper staff log: Morning, Midday, Afternoon, and Evening. The staff on duty are chosen once for that date and shown on every row. A call can be recorded, left blank, or marked not required. Times may pass midnight (`24:10`).

Visits stay in this module. They are not created as OpenEMR encounters.

## Install

Requires OpenEMR 7+ (tested on 8.5.0) and PHP 8.1 or newer. Older OpenEMR 7.x releases are supported on a best-effort basis.

1. Copy this directory to `interface/modules/custom_modules/oe-module-provider-teams` on the OpenEMR server. The folder name is part of the menu URL.
2. Log in as an administrator and open **Modules → Manage Modules**.
3. On the **Unregistered** tab, register **Provider Teams**.
4. Click **Install** (this runs `table.sql`), then **Enable**.

Enabling the module adds a **Provider Teams** menu and grants the new access controls to the Administrators role. Other roles can be given **View**, **Record**, or **Administer** under Administration → ACL → Provider Teams.

- **View** opens the dashboard, print view, and CSV export.
- **Record** saves time in, time out, calls marked not required, and the day's staff. It includes view.
- **Administer** creates teams, adds staff, and assigns patients. It includes record and view.

## Use

**Teams.** Create a team such as Team 5. Search OpenEMR users and add the care staff. Any active user can be added, including staff who are not marked as clinical providers. Search existing patients and assign them. A patient can be on one active team. The order number is the round order on the dashboard. Unassigning a patient keeps visits already recorded.

**Visit dashboard.** Choose the team and a date. Tick the staff on duty, enter time in and time out for each call, or mark a call not required. **Audit a date range** shows up to 31 days read-only, with the same columns as the paper log, a print header for CQC and Care Certificate records, and a CSV download. Calls marked not required still export in CSV as `XXXXXX` / `XXX`.

## Develop

```bash
composer install
composer test
```

The tests cover time parsing, missed calls, duration, and matrix assembly. They do not need a running OpenEMR site.
