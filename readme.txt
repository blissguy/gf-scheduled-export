=== Gravity Forms Scheduled Entry Exports ===
Contributors: mixbusmarketing
Tags: gravity forms, export, email, csv, reports
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically emails you a spreadsheet of new form submissions on an hourly, weekly, or monthly schedule. Works with Gravity Forms.

== Description ==

Gravity Forms Scheduled Entry Exports keeps you in the loop without logging in to your website. On the schedule you choose, it gathers your new form submissions into a spreadsheet (CSV) and emails it straight to your inbox — or to anyone else who needs it.

It's built on the official Gravity Forms add-on framework, so it feels right at home: open any form, go to Settings → Schedule Entry Exports, and add as many scheduled exports as you like.

**What it does**

* Sends an email with a spreadsheet of new submissions — hourly, weekly, or monthly.
* Weekly exports go out on the day of the week you pick; monthly exports on the day of the month you pick, both at your chosen time.
* Each export only includes submissions received since the last one, so nothing is missed and nothing repeats.
* Set who receives it, plus the From name, From email, Reply To, BCC, subject, and message — with merge tags like {admin_email} supported.
* Use conditional logic to only include submissions that match rules you set (for example, only entries that chose a certain option).
* Set up as many exports as you like, on any of your forms, each with its own schedule and recipients.
* The feed list shows each export's schedule and when it last ran, right inside your form's settings.
* A "Send Test" link on every export sends it immediately using its saved settings, so you can confirm everything works without waiting for the schedule.
* Quiet periods with no new submissions are skipped by default — no empty emails. Weekly and monthly exports can instead send a short "still running" note, with a message you can customize.
* The spreadsheets open cleanly in Excel and Google Sheets.

**Good to know**

* Gravity Forms (the free version is fine) must be installed and active.
* Exports send reliably when your website sends email reliably. If your site's emails sometimes land in spam, pair this with a free email delivery plugin such as FluentSMTP or WP Mail SMTP.
* No submission data is ever left on your server — the spreadsheet is created, emailed, and immediately deleted.

By Mixbus Marketing | https://mixbusmarketing.com/

== Installation ==

1. Upload the plugin to your website and activate it.
2. Open the form you want reports for, then go to Settings → Schedule Entry Exports.
3. Click "Add New", give the export a name, choose how often it should go out and who receives it, then save.
4. Back on the list, click "Send Test" to send it right away and confirm it arrives.

== Frequently Asked Questions ==

= When exactly does the email arrive? =

At or shortly after the delivery time you chose, in your site's timezone. Websites send scheduled emails when they next get a visitor, so on very quiet sites the email can arrive a little later.

= Which submissions are included? =

Everything received since the previous export went out. A brand-new export starts counting from the moment it's created, and sends its first email at its first scheduled day and time.

= What happens if there were no new submissions? =

By default nothing is sent, and the feed list notes that the last run had no new entries — you won't get empty spreadsheets. On weekly and monthly exports you can turn on "Send an email even when there are no new entries" to get a short note confirming the export is still running; you can write your own version of that note or leave it blank to use the ready-made one.

= Is the exported data stored anywhere? =

No. The spreadsheet is created in a temporary location, emailed, and deleted right away.

== Changelog ==

= 1.1.0 =
* New: weekly and monthly exports can now send a short email on periods with no new entries, so recipients know the export is still running. Turn it on per export under "Quiet Periods", and optionally write your own no-entries message (leaving it blank uses a ready-made one).
* New: the Message box now shows the ready-made email text it will use when left blank, so you can see exactly what recipients get before deciding to customize it.

= 1.0.0 =
* First release: scheduled entry exports built on the Gravity Forms add-on framework. Hourly, weekly, or monthly emails with a CSV spreadsheet of new submissions, per-form export feeds, merge tag support, conditional logic, a last-run status column, and a "Send Test" action to try any export immediately.
