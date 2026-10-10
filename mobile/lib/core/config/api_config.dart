class ApiConfig {
  const ApiConfig({required this.baseUrl});

  factory ApiConfig.fromEnvironment() {
    const raw = String.fromEnvironment(
      'API_BASE_URL',
      defaultValue: 'http://localhost/api',
    );
    return ApiConfig(baseUrl: raw);
  }

  final String baseUrl;

  String get normalizedBaseUrl {
    var value = baseUrl.trim();
    if (value.endsWith('/')) {
      value = value.substring(0, value.length - 1);
    }
    return value;
  }

  String get origin {
    final base = Uri.parse(normalizedBaseUrl);
    final buffer = StringBuffer()..write(base.scheme)..write('://')..write(base.host);
    if (base.hasPort) {
      buffer..write(':')..write(base.port);
    }
    return buffer.toString();
  }

  Uri endpoint(String path, [Map<String, dynamic>? queryParameters]) {
    final cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return Uri.parse('$normalizedBaseUrl/$cleanPath').replace(
      queryParameters: queryParameters?.map(
        (key, value) => MapEntry(key, value?.toString()),
      ),
    );
  }

  String resolveDownloadUrl(String rawPath) {
    if (rawPath.startsWith('http://') || rawPath.startsWith('https://')) {
      return rawPath;
    }
    if (rawPath.startsWith('/')) {
      return '$origin$rawPath';
    }
    return endpoint(rawPath).toString();
  }
}
