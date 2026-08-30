import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import 'app/router/app_router.dart';
import 'core/network/api_client.dart';
import 'core/state/locale_cubit.dart';
import 'core/state/subsystem_cubit.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/cubit/auth_cubit.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final apiClient = ApiClient();

  runApp(LumaniApp(apiClient: apiClient));
}

class LumaniApp extends StatelessWidget {
  final ApiClient apiClient;

  const LumaniApp({super.key, required this.apiClient});

  @override
  Widget build(BuildContext context) {
    return MultiBlocProvider(
      providers: [
        BlocProvider(create: (_) => LocaleCubit()),
        BlocProvider(create: (_) => SubsystemCubit()),
        BlocProvider(create: (_) => AuthCubit(apiClient: apiClient)),
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
            routerConfig: AppRouter.router,
          );
        },
      ),
    );
  }
}
