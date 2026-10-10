# TaskBuddy mobile

Flutter client for TaskBuddy clients and providers.

## Prerequisites

1. Install Flutter and Dart locally.
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
