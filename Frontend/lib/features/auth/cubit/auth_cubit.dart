import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/models/user_model.dart';
import '../../../core/network/api_client.dart';
import 'auth_state.dart';

class AuthCubit extends Cubit<AuthState> {
  final ApiClient apiClient;

  /// Key used to cache the last-known user JSON in secure storage so that
  /// [AuthenticatedOffline] can reconstruct a [UserModel] without a network
  /// round-trip.
  static const String _cachedUserKey = 'cached_user_json';

  AuthCubit({required this.apiClient}) : super(AuthInitial());

  /// Checks for an existing valid session on app startup.
  ///
  /// Session resilience contract:
  /// - HTTP 401 → token is revoked → clear token, emit [Unauthenticated].
  /// - Network failure (SocketException, timeout, DNS) → token may still be
  ///   valid → preserve token, emit [AuthenticatedOffline] with cached user.
  /// - No stored token → emit [Unauthenticated].
  Future<void> checkAuthStatus() async {
    emit(AuthLoading());
    try {
      final isAuth = await apiClient.isAuthenticated();
      if (!isAuth) {
        emit(Unauthenticated());
        return;
      }

      final response = await apiClient.dio.get('/user');
      final rawUser = response.data['user'] ?? response.data;
      final user = UserModel.fromJson(Map<String, dynamic>.from(rawUser));
      final token = (await apiClient.getToken()) ?? '';

      // Cache user data for offline recovery on next cold start.
      await apiClient.secureStorage.write(
        key: _cachedUserKey,
        value: jsonEncode(user.toJson()),
      );

      emit(Authenticated(user: user, token: token));
    } on DioException catch (e) {
      final statusCode = e.response?.statusCode;
      if (statusCode == 401) {
        // Token is explicitly rejected by the server — clear everything.
        await apiClient.clearToken();
        await apiClient.secureStorage.delete(key: _cachedUserKey);
        emit(Unauthenticated());
      } else {
        // Network failure, timeout, 500, etc.
        // The token has NOT been rejected — preserve the session.
        await _emitOfflineOrUnauthenticated();
      }
    } catch (_) {
      // Non-Dio error (very unlikely). Preserve token if possible.
      await _emitOfflineOrUnauthenticated();
    }
  }

  /// If a stored token and cached user exist, emit [AuthenticatedOffline].
  /// Otherwise there is no session to preserve, so emit [Unauthenticated].
  Future<void> _emitOfflineOrUnauthenticated() async {
    final token = await apiClient.getToken();
    if (token == null || token.isEmpty) {
      emit(Unauthenticated());
      return;
    }

    final cachedJson = await apiClient.secureStorage.read(key: _cachedUserKey);
    if (cachedJson != null && cachedJson.isNotEmpty) {
      final user = UserModel.fromJson(
        Map<String, dynamic>.from(jsonDecode(cachedJson)),
      );
      emit(AuthenticatedOffline(user: user, token: token));
    } else {
      // We have a token but no cached user profile — can't render the UI
      // safely. Preserve the token (don't clear it) but send to auth so
      // the next online launch can verify it.
      emit(Unauthenticated());
    }
  }

  Future<void> login({required String email, required String password}) async {
    emit(AuthLoading());
    try {
      final response = await apiClient.dio.post(
        '/login',
        data: {'email': email, 'password': password},
      );

      final token = response.data['token'] as String;
      final rawUser = Map<String, dynamic>.from(response.data['user']);
      final user = UserModel.fromJson(rawUser);

      await apiClient.saveToken(token);
      await apiClient.secureStorage.write(
        key: _cachedUserKey,
        value: jsonEncode(user.toJson()),
      );

      emit(Authenticated(user: user, token: token));
    } on DioException catch (e) {
      final apiErr = e.error as ApiException?;
      emit(
        AuthError(
          message:
              apiErr?.message ?? 'Login failed. Please check your credentials.',
          validationErrors: apiErr?.errors,
        ),
      );
    } catch (e) {
      emit(AuthError(message: e.toString()));
    }
  }

  Future<void> register({
    required String firstName,
    required String lastName,
    required String email,
    required String password,
    required String passwordConfirmation,
    String preferredLanguage = 'en',
    String? phoneNumber,
  }) async {
    emit(AuthLoading());
    try {
      final response = await apiClient.dio.post(
        '/register',
        data: {
          'first_name': firstName,
          'last_name': lastName,
          'email': email,
          'password': password,
          'password_confirmation': passwordConfirmation,
          'preferred_language': preferredLanguage,
          'phone_number': phoneNumber ?? '',
        },
      );

      final token = response.data['token'] as String;
      final rawUser = Map<String, dynamic>.from(response.data['user']);
      final user = UserModel.fromJson(rawUser);

      await apiClient.saveToken(token);
      await apiClient.secureStorage.write(
        key: _cachedUserKey,
        value: jsonEncode(user.toJson()),
      );

      emit(Authenticated(user: user, token: token));
    } on DioException catch (e) {
      final apiErr = e.error as ApiException?;
      emit(
        AuthError(
          message:
              apiErr?.message ??
              'Registration failed. Please check your details.',
          validationErrors: apiErr?.errors,
        ),
      );
    } catch (e) {
      emit(AuthError(message: e.toString()));
    }
  }

  Future<void> logout() async {
    try {
      await apiClient.dio.post('/logout');
    } catch (_) {
      // Ignore network errors during logout
    } finally {
      await apiClient.clearToken();
      await apiClient.secureStorage.delete(key: _cachedUserKey);
      emit(Unauthenticated());
    }
  }

  /// Emits [Unauthenticated] in response to a global HTTP 401 detected by
  /// the Dio interceptor in [ApiClient].
  ///
  /// The interceptor already cleared the token before calling this.
  /// This method must only be called from [ApiClient.onUnauthenticated].
  void forceUnauthenticated() {
    if (state is! Unauthenticated) {
      emit(Unauthenticated());
    }
  }
}
