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

class ApiClient {
  static const String tokenKey = 'sanctum_auth_token';
  static const String defaultBaseUrl = 'http://10.0.2.2:8000/api';

  late final Dio dio;
  final FlutterSecureStorage secureStorage;

  ApiClient({String baseUrl = defaultBaseUrl, FlutterSecureStorage? storage})
    : secureStorage = storage ?? const FlutterSecureStorage() {
    dio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
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
        onError: (DioException error, handler) {
          final statusCode = error.response?.statusCode;
          final responseData = error.response?.data;

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
