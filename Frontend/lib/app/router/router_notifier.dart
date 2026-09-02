import 'package:flutter/foundation.dart';

import '../../features/auth/cubit/auth_cubit.dart';
import '../../features/auth/cubit/auth_state.dart';

/// Bridges [AuthCubit] state changes into a [ChangeNotifier] that
/// [GoRouter] can listen to via [refreshListenable].
///
/// This is the minimal, clean approach for GoRouter + BLoC integration.
/// It holds no business logic — it only notifies the router when auth
/// state changes so redirect guards are re-evaluated.
class RouterNotifier extends ChangeNotifier {
  late final Stream<AuthState> _authStream;

  RouterNotifier(AuthCubit authCubit) {
    _authStream = authCubit.stream;
    _authStream.listen((_) => notifyListeners());
  }
}
