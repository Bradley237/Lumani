import 'package:dio/dio.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/network/api_client.dart';
import 'auth_state.dart';

class AuthCubit extends Cubit<AuthState> {
  final ApiClient apiClient;

  AuthCubit({required this.apiClient}) : super(AuthInitial());

  Future<void> checkAuthStatus() async {
    emit(AuthLoading());
    try {
      final isAuth = await apiClient.isAuthenticated();
      if (!isAuth) {
        emit(Unauthenticated());
        return;
      }

      final response = await apiClient.dio.get('/user');
      final user = response.data['user'] ?? response.data;
      final token = (await apiClient.getToken()) ?? '';

      emit(Authenticated(user: Map<String, dynamic>.from(user), token: token));
    } on DioException catch (e) {
      final statusCode = e.response?.statusCode;
      if (statusCode == 401) {
        // Token is explicitly rejected by the server — clear it.
        await apiClient.clearToken();
        emit(Unauthenticated());
      } else {
        // Network failure, timeout, 500, etc. — token is still valid.
        // Do NOT clear it. Emit unauthenticated so the splash router
        // redirects to auth, but the token remains for the next launch.
        emit(Unauthenticated());
      }
    } catch (e) {
      // Non-Dio error (very unlikely). Preserve token, redirect to auth.
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
      final user = Map<String, dynamic>.from(response.data['user']);

      await apiClient.saveToken(token);
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
      final user = Map<String, dynamic>.from(response.data['user']);

      await apiClient.saveToken(token);
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
