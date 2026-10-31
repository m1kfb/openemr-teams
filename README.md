# Provider Teams

OpenEMR module for care teams. Assign patients and staff to a team, then record each day's calls with a time in and a time out.

A team day has four calls, matching the paper staff log: Morning, Midday, Afternoon, and Evening. The staff on duty are chosen once for that date and shown on every row. A call can be recorded, left blank, or marked missed. Times may pass midnight (`24:10`).

Visits stay in this module. They are not created as OpenEMR encounters.

## Install

Requires OpenEMR 7+ (tested on 8.5.0) and PHP 8.1 or newer. Older OpenEMR 7.x releases are supported on a best-effort basis.

1. Copy this directory to `interface/modules/custom_modules/oe-module-provider-teams` on the OpenEMR server. The folder name is part of the menu URL.
2. Log in as an administrator and open **Modules → Manage Modules**.
3. On the **Unregistered** tab, register **Provider Teams**.
4. Click **Install** (this runs `table.sql`), then **Enable**.

Enabling the module adds a **Provider Teams** menu and grants the new access controls to the Administrators role. Other roles can be given **View**, **Record**, or **Administer** under Administration → ACL → Provider Teams.

- **View** opens the dashboard, print view, and CSV export.
- **Record** saves time in, time out, missed calls, and the day's staff. It includes view.
- **Administer** creates teams, adds staff, and assigns patients. It includes record and view.

## Use

**Teams.** Create a team such as Team 5. Search OpenEMR users and add the care staff. Any active user can be added, including staff who are not marked as clinical providers. Search existing patients and assign them. A patient can be on one active team. The order number is the round order on the dashboard. Unassigning a patient keeps visits already recorded.

### Care Team sync (OpenEMR 8.x)

This module keeps the **Provider Teams** roster aligned with each assigned patient’s **Care Team** on the patient dashboard.

**Team → patient (automatic).** When you add or remove staff on a provider team, or assign or unassign a patient, the module updates that patient’s Care Team through OpenEMR’s `CareTeamService` (`saveCareTeam` / `getCareTeamData`). It records each member it adds in `provider_team_care_team_links` so it never duplicates users and only removes members it added. If a clinician removes a module-added member from one patient’s Care Team, the link row is kept with an **excluded** flag so automatic sync does not add them back; **Apply to whole team** still updates provider-team staff from the chosen patient and refreshes everyone else. On the patient **Care Team** card, excluded provider-team members appear greyed out with **Removed for this patient** and an **Add back** action (CSRF and chart write permission). Manually added Care Team members are never removed by provider-team changes. Related persons and contacts on the Care Team are not synced. Only **active** care-team user members and active OpenEMR users are considered for sync.

**Patient → team (opt-in only).** Editing a patient’s Care Team in core does **not** change the provider team unless you explicitly choose **Apply to whole team** on the module **Teams** page (one button per assigned patient). A confirmation shows the provider team name and how many patients are on that team (for example, “This updates North Team for 24 patients”). That action updates provider-team staff from the selected patient’s Care Team (OpenEMR users only), then refreshes Care Teams for every patient on the team. Staff are removed from the provider team only when they were module-linked on that patient’s Care Team and are no longer on it; manually added Care Team members do not drive removals.

OpenEMR does not fire an event when the Care Team is saved, and the Care Team card template does not render module injections, so the opt-in control lives on the module **Teams** page rather than on the core Care Team editor.

**OpenEMR 7.x.** Sync requires the `care_teams` and `care_team_member` tables (OpenEMR 8+ style). Older 7.x sites that still use legacy demographics care team fields will log a notice and skip sync. Run **Upgrade SQL** (`sql/1_0_0-to-1_0_1_upgrade.sql` on 1.0.0 installs; `sql/1_0_1-to-1_0_2_upgrade.sql` on 1.0.1 installs) to add or extend the link table.

**Visit dashboard.** Choose the team and a date. Tick the staff on duty, enter time in and time out for each call, or mark a call missed. **Audit a date range** shows up to 31 days read-only, with the same columns as the paper log, a print header for CQC and Care Certificate records, and a CSV download. Missed calls export as `XXXXXX` / `XXX`.

## Develop

```bash
composer install
composer test
```

The tests cover time parsing, missed calls, duration, matrix assembly, and care team reconciliation rules. They do not need a running OpenEMR site.
