import 'package:equatable/equatable.dart';

import '../../../core/models/user_model.dart';

abstract class AuthState extends Equatable {
  const AuthState();

  @override
  List<Object?> get props => [];
}

class AuthInitial extends AuthState {}

class AuthLoading extends AuthState {}

/// The user is fully authenticated and the server has confirmed the session.
class Authenticated extends AuthState {
  final UserModel user;
  final String token;

  const Authenticated({required this.user, required this.token});

  @override
  List<Object?> get props => [user, token];
}

/// The user has a stored token that has NOT been rejected by the server,
/// but the network is currently unreachable (SocketException, timeout, etc.).
///
/// The app treats this identically to [Authenticated] for navigation guards
/// and UI rendering, using the last-known [user] data. This prevents the
/// critical bug where a network drop on startup forces an unnecessary logout.
class AuthenticatedOffline extends AuthState {
  final UserModel user;
  final String token;

  const AuthenticatedOffline({required this.user, required this.token});

  @override
  List<Object?> get props => [user, token];
}

class Unauthenticated extends AuthState {}

class AuthError extends AuthState {
  final String message;
  final Map<String, dynamic>? validationErrors;

  const AuthError({required this.message, this.validationErrors});

  @override
  List<Object?> get props => [message, validationErrors];
}
