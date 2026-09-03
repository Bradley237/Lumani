import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'app/router/app_router.dart';
import 'core/localization/app_localizations.dart';
import 'core/network/api_client.dart';
import 'core/state/locale_cubit.dart';
import 'core/state/subsystem_cubit.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/cubit/auth_cubit.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  // ApiClient is created first. The onUnauthenticated callback is wired
  // after AuthCubit is created, avoiding a circular dependency.
  final apiClient = ApiClient();
  final authCubit = AuthCubit(apiClient: apiClient);

  // Global 401 handler: when the server explicitly rejects the token,
  // notify AuthCubit. This does NOT fire on network errors, timeouts,
  // 403, 422, or any other status code.
  apiClient.onUnauthenticated = () {
    authCubit.forceUnauthenticated();
  };

  runApp(LumaniApp(apiClient: apiClient, authCubit: authCubit));
}

class LumaniApp extends StatelessWidget {
  final ApiClient apiClient;
  final AuthCubit authCubit;

  const LumaniApp({
    super.key,
    required this.apiClient,
    required this.authCubit,
  });

  @override
  Widget build(BuildContext context) {
    final localeCubit = LocaleCubit();

    return MultiBlocProvider(
      providers: [
        BlocProvider(create: (_) => localeCubit),
        BlocProvider(create: (_) => SubsystemCubit(localeCubit: localeCubit)),
        BlocProvider.value(value: authCubit),
      ],
      child: BlocBuilder<LocaleCubit, Locale>(
        builder: (context, locale) {
          return MaterialApp.router(
            title: 'Lumani',
            debugShowCheckedModeBanner: false,
            themeMode: ThemeMode.system,
            theme: AppTheme.lightTheme,
            darkTheme: AppTheme.darkTheme,
            locale: locale,
            localizationsDelegates: const [
              AppLocalizations.delegate,
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            supportedLocales: AppLocalizations.supportedLocales,
            routerConfig: AppRouter.createRouter(authCubit),
          );
        },
      ),
    );
  }
}
