import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../storage/secure_token_storage.dart';
import 'api_exception.dart';

class ApiEnvelope {
  const ApiEnvelope({required this.data, required this.meta});

  final dynamic data;
  final Map<String, dynamic> meta;
}

class ApiClient {
  ApiClient({required this.config, required this.tokenStorage});

  final ApiConfig config;
  final SecureTokenStorage tokenStorage;
  VoidCallback? _onUnauthorized;

  void setUnauthorizedHandler(VoidCallback handler) {
    _onUnauthorized = handler;
  }

  Future<dynamic> get(
    String path, {
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = true,
  }) async {
    final envelope = await getEnvelope(
      path,
      queryParameters: queryParameters,
      requiresAuth: requiresAuth,
    );
    return envelope.data;
  }

  Future<ApiEnvelope> getEnvelope(
    String path, {
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = true,
  }) {
    return _send(
      'GET',
      path,
      queryParameters: queryParameters,
      requiresAuth: requiresAuth,
    );
  }

  Future<dynamic> post(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
  }) async {
    final envelope = await postEnvelope(path, body: body, requiresAuth: requiresAuth);
    return envelope.data;
  }

  Future<ApiEnvelope> postEnvelope(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
  }) {
    return _send('POST', path, body: body, requiresAuth: requiresAuth);
  }

  Future<dynamic> put(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
  }) async {
    final envelope = await putEnvelope(path, body: body, requiresAuth: requiresAuth);
    return envelope.data;
  }

  Future<ApiEnvelope> putEnvelope(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
  }) {
    return _send('PUT', path, body: body, requiresAuth: requiresAuth);
  }

  Future<dynamic> delete(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
  }) async {
    final envelope = await deleteEnvelope(path, body: body, requiresAuth: requiresAuth);
    return envelope.data;
  }

  Future<ApiEnvelope> deleteEnvelope(
    String path, {
    Map<String, dynamic>? body,
    bool requiresAuth = true,
  }) {
    return _send('DELETE', path, body: body, requiresAuth: requiresAuth);
  }

  Future<dynamic> upload(
    String path, {
    required List<UploadFile> files,
    Map<String, String>? fields,
    String fieldName = 'file',
    bool requiresAuth = true,
  }) async {
    final envelope = await uploadEnvelope(
      path,
      files: files,
      fields: fields,
      fieldName: fieldName,
      requiresAuth: requiresAuth,
    );
    return envelope.data;
  }

  Future<ApiEnvelope> uploadEnvelope(
    String path, {
    required List<UploadFile> files,
    Map<String, String>? fields,
    String fieldName = 'file',
    bool requiresAuth = true,
  }) async {
    try {
      final request = http.MultipartRequest('POST', config.endpoint(path));
      request.headers.addAll(await _headers(requiresAuth, isJson: false));
      if (fields != null) {
        request.fields.addAll(fields);
      }
      for (final file in files) {
        request.files.add(await http.MultipartFile.fromPath(
          fieldName,
          file.path,
          filename: file.fileName,
        ));
      }
      final streamed = await request.send();
      final response = await http.Response.fromStream(streamed);
      return _decodeResponse(response);
    } on SocketException catch (error) {
      throw ApiException.network(error);
    } on HttpException catch (error) {
      throw ApiException.network(error);
    }
  }

  Future<ApiEnvelope> _send(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, dynamic>? queryParameters,
    bool requiresAuth = true,
  }) async {
    try {
      final uri = config.endpoint(path, queryParameters);
      final headers = await _headers(requiresAuth);
      late http.Response response;
      final encodedBody = body == null ? null : jsonEncode(body);

      switch (method) {
        case 'GET':
          response = await http.get(uri, headers: headers);
          break;
        case 'POST':
          response = await http.post(uri, headers: headers, body: encodedBody);
          break;
        case 'PUT':
          response = await http.put(uri, headers: headers, body: encodedBody);
          break;
        case 'DELETE':
          response = await http.delete(uri, headers: headers, body: encodedBody);
          break;
        default:
          throw UnsupportedError('Unsupported method $method');
      }

      return _decodeResponse(response);
    } on SocketException catch (error) {
      throw ApiException.network(error);
    } on HttpException catch (error) {
      throw ApiException.network(error);
    }
  }

  Future<Map<String, String>> _headers(bool requiresAuth, {bool isJson = true}) async {
    final headers = <String, String>{'Accept': 'application/json'};
    if (isJson) {
      headers['Content-Type'] = 'application/json';
    }
    if (requiresAuth) {
      final token = await tokenStorage.readToken();
      if (token != null && token.isNotEmpty) {
        headers['Authorization'] = 'Bearer ' + token;
      }
    }
    return headers;
  }

  ApiEnvelope _decodeResponse(http.Response response) {
    dynamic body;
    try {
      body = response.body.isEmpty ? const {} : jsonDecode(response.body);
    } on FormatException {
      throw ApiException(
        statusCode: response.statusCode,
        code: 'INVALID_RESPONSE',
        message: 'The server returned a malformed JSON response.',
      );
    }
    if (body is! Map<String, dynamic>) {
      throw ApiException(
        statusCode: response.statusCode,
        code: 'INVALID_RESPONSE',
        message: 'The server returned a malformed JSON response.',
      );
    }

    final success = body['success'] == true;
    if (!success) {
      final exception = ApiException.fromResponse(response.statusCode, body);
      if (exception.isUnauthorized) {
        _onUnauthorized?.call();
      }
      throw exception;
    }

    final meta = body['meta'] is Map<String, dynamic>
        ? body['meta'] as Map<String, dynamic>
        : const <String, dynamic>{};
    return ApiEnvelope(data: body['data'], meta: meta);
  }
}

class UploadFile {
  const UploadFile({required this.path, required this.fileName});

  final String path;
  final String fileName;
}
