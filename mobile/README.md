# TaskBuddy mobile

Flutter client for TaskBuddy clients and providers.

## Prerequisites

1. Install Flutter and Dart locally. Reference toolchain (from `flutter --version` / `flutter doctor -v` on the maintainer's machine, 2026-10-10):
   - Flutter 3.44.9 (stable channel, framework revision `6b182d2c75`, engine `5a2a6a42cc`)
   - Dart 3.12.2, DevTools 2.57.0
   - Android SDK 36.1.0 (build-tools 36.1.0), JDK 21 (Android Studio bundled JBR)
   - Windows 10 Pro 22H2; Visual Studio Community 2026 18.8 for Windows desktop builds
   - Note: `flutter doctor` reported unaccepted Android licenses; run `flutter doctor --android-licenses` before Android builds.
   - `pubspec.yaml` requires Dart `>=3.12.0 <4.0.0` and Flutter `>=3.44.0`.
   - `flutter_secure_storage` (^9.2.2) and `file_picker` (^8.1.2) were intentionally left on their current major versions; newer majors (11.x / 13.x) have breaking API changes (e.g. `file_picker` 13 removes `withData`/`allowMultiple` on `pickFiles`) and need a migration that must be compiled and tested with the SDK.
2. From this `mobile/` directory, generate platform folders if needed:
   ```bash
   flutter create .
   ```

## Configure the API base URL

Pass the backend API base URL with `--dart-define`:

```bash
flutter run --dart-define=API_BASE_URL=https://example.com/api
```

The same define should be used for tests or builds that need the runtime config.

## Common commands

```bash
flutter pub get
flutter run --dart-define=API_BASE_URL=https://example.com/api
flutter test --dart-define=API_BASE_URL=https://example.com/api
```

## Notes

- This app stores the authenticated session securely with `flutter_secure_storage`.
- Android and iOS folders are intentionally not checked in here; generate them with `flutter create .`.
- **Important:** this code was authored in an environment without Flutter/Dart SDKs, so it was **not compiled, analyzed, or run** here.
