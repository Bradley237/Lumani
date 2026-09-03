import 'dart:io' show Platform;

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  ApiException({required this.message, this.statusCode, this.errors});

  @override
  String toString() => 'ApiException: $message (StatusCode: $statusCode)';
}

/// Callback invoked by [ApiClient] when the server returns HTTP 401.
///
/// The implementor is responsible for clearing auth state and redirecting
/// the user to the authentication flow. This decouples [ApiClient] from
/// [AuthCubit] and avoids circular dependency.
typedef OnUnauthenticated = void Function();

/// The single authoritative API configuration for Lumani.
///
/// ## Selecting an API Environment
///
/// The base URL is injected at build time via `--dart-define`:
///
///   flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api
///   flutter build apk --dart-define=API_BASE_URL=https://api.lumani.cm/api
///
/// When no `--dart-define` is provided, the URL is resolved per-platform:
///   Android Emulator → http://10.0.2.2:8000/api
///   iOS Simulator    → http://127.0.0.1:8000/api
///
/// There is ONE place where this is defined: this file.
/// Do not hardcode any URL in feature code.
abstract final class ApiConfig {
  /// Explicitly set via `--dart-define=API_BASE_URL=...` at build time.
  /// Empty string when not provided.
  static const String _envUrl = String.fromEnvironment('API_BASE_URL');

  /// Resolves the correct base URL for the current platform.
  ///
  /// Priority:
  /// 1. Explicit `--dart-define` value (production / staging / LAN IP).
  /// 2. iOS → `127.0.0.1` (simulator loopback).
  /// 3. Android / fallback → `10.0.2.2` (emulator host gateway).
  static String get baseUrl {
    if (_envUrl.isNotEmpty) return _envUrl;
    final host = Platform.isIOS ? '127.0.0.1' : '10.0.2.2';
    return 'http://$host:8000/api';
  }
}

class ApiClient {
  static const String tokenKey = 'sanctum_auth_token';

  late final Dio dio;
  final FlutterSecureStorage secureStorage;

  /// Called when the API returns HTTP 401 (authentication expired/invalid).
  ///
  /// Assign this after construction to avoid circular dependencies.
  /// Must NOT be called for 403, 422, network errors, or timeouts.
  OnUnauthenticated? onUnauthenticated;

  ApiClient({String? baseUrl, FlutterSecureStorage? storage})
    : secureStorage = storage ?? const FlutterSecureStorage() {
    dio = Dio(
      BaseOptions(
        baseUrl: baseUrl ?? ApiConfig.baseUrl,
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 10),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await secureStorage.read(key: tokenKey);
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          return handler.next(options);
        },
        onError: (DioException error, handler) async {
          final statusCode = error.response?.statusCode;
          final responseData = error.response?.data;

          // HTTP 401: authentication is no longer valid.
          // Clear the stored token and notify the auth layer.
          // Do NOT treat any other status code this way.
          if (statusCode == 401) {
            await clearToken();
            onUnauthenticated?.call();
          }

          String message = 'An unexpected error occurred.';
          Map<String, dynamic>? errors;

          if (responseData is Map<String, dynamic>) {
            if (responseData.containsKey('message')) {
              message = responseData['message'].toString();
            }
            if (responseData.containsKey('errors') &&
                responseData['errors'] is Map) {
              errors = Map<String, dynamic>.from(responseData['errors']);
            }
          }

          return handler.reject(
            DioException(
              requestOptions: error.requestOptions,
              response: error.response,
              type: error.type,
              error: ApiException(
                message: message,
                statusCode: statusCode,
                errors: errors,
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> saveToken(String token) async {
    await secureStorage.write(key: tokenKey, value: token);
  }

  Future<void> clearToken() async {
    await secureStorage.delete(key: tokenKey);
  }

  Future<String?> getToken() async {
    return await secureStorage.read(key: tokenKey);
  }

  Future<bool> isAuthenticated() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }
}
