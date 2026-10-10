# Known limitations (honest status)

* **Flutter app not compiled or run.** No Flutter/Dart SDK was available when it was written. It is complete as source
  against `docs/api.md`, but compile errors and layout problems are possible. Next step: `flutter create .` (platform folders),
  `flutter pub get`, `flutter analyze`, `flutter test`, then manual device testing.
* No email/SMS: there is **no password-reset-by-email** and no email verification (no mail provider configured).
* No push notifications; the app polls notifications/messages. (Real-time transport such as WebSockets was out of scope.)
* Payments are **simulated** only; there is no payment gateway, no real money movement, no refunds beyond state changes.
* Availability scoring is binary in algorithm v1.0.0 (hours before the deadline are reported but not graded).
* Timezone handling uses the configured application timezone (UTC default); per-user timezones are not modelled.
* Antivirus scanning of uploads is not included; uploads rely on type/size validation and isolated storage.
* The admin dashboard has no 2FA; protect it with HTTPS, a separate host, and optionally IP allow-listing.
* Accessibility and performance have not been measured with real users or load tests; the ISO/IEC 25010 survey and
  expert ratings are **templates only, no data has been collected** (see `iso25010-evaluation.md`).
* Reference data (categories/skills) can be managed in the dashboard but there is no bulk import.
