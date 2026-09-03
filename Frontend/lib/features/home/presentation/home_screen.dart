import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../core/models/user_model.dart';
import '../../../core/state/locale_cubit.dart';
import '../../../core/state/subsystem_cubit.dart';
import '../../../core/theme/app_dimensions.dart';
import '../../auth/cubit/auth_cubit.dart';
import '../../auth/cubit/auth_state.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  /// Extracts the [UserModel] from the current [AuthState], handling both
  /// [Authenticated] and [AuthenticatedOffline].
  UserModel? _userFrom(AuthState state) {
    if (state is Authenticated) return state.user;
    if (state is AuthenticatedOffline) return state.user;
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Lumani Dashboard'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout_rounded),
            tooltip: 'Sign Out',
            onPressed: () async {
              await context.read<AuthCubit>().logout();
              if (context.mounted) {
                context.go('/auth');
              }
            },
          ),
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: AppDimensions.padding20,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              BlocBuilder<AuthCubit, AuthState>(
                builder: (context, state) {
                  final user = _userFrom(state);
                  final firstName = (user != null && user.firstName.isNotEmpty)
                      ? user.firstName
                      : 'Student';
                  final isOffline = state is AuthenticatedOffline;

                  return Card(
                    child: Padding(
                      padding: AppDimensions.padding20,
                      child: Row(
                        children: [
                          CircleAvatar(
                            radius: 24,
                            backgroundColor: theme.colorScheme.primaryContainer,
                            child: Text(
                              firstName[0].toUpperCase(),
                              style: TextStyle(
                                color: theme.colorScheme.primary,
                                fontWeight: FontWeight.bold,
                                fontSize: 20,
                              ),
                            ),
                          ),
                          const SizedBox(width: AppDimensions.space16),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Welcome back, $firstName!',
                                  style: theme.textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  user?.email ?? '',
                                  style: theme.textTheme.bodySmall,
                                ),
                                if (isOffline)
                                  Padding(
                                    padding: const EdgeInsets.only(
                                      top: AppDimensions.space4,
                                    ),
                                    child: Text(
                                      'Offline — using cached data',
                                      style: theme.textTheme.bodySmall
                                          ?.copyWith(
                                            color: theme.colorScheme.error,
                                          ),
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
              const SizedBox(height: AppDimensions.space20),
              Text('Active Subsystem', style: theme.textTheme.titleSmall),
              const SizedBox(height: AppDimensions.space8),
              BlocBuilder<SubsystemCubit, SubsystemState>(
                builder: (context, state) {
                  final title = state.subsystem == Subsystem.gce
                      ? 'Anglophone (GCE Curriculum)'
                      : state.subsystem == Subsystem.obc
                      ? 'Francophone (OBC Curriculum)'
                      : 'Not Selected';

                  return Card(
                    child: ListTile(
                      leading: const Icon(Icons.school_outlined),
                      title: Text(title),
                      subtitle: const Text('Tap to switch academic curriculum'),
                      trailing: const Icon(Icons.swap_horiz_rounded),
                      onTap: () => context.go('/subsystem'),
                    ),
                  );
                },
              ),
              const SizedBox(height: AppDimensions.space20),
              Text('Interface Language', style: theme.textTheme.titleSmall),
              const SizedBox(height: AppDimensions.space8),
              BlocBuilder<LocaleCubit, Locale>(
                builder: (context, locale) {
                  return Card(
                    child: ListTile(
                      leading: const Icon(Icons.language_rounded),
                      title: Text(
                        locale.languageCode == 'fr' ? 'Français' : 'English',
                      ),
                      trailing: Switch(
                        value: locale.languageCode == 'fr',
                        onChanged: (isFr) {
                          context.read<LocaleCubit>().setLocale(
                            isFr ? 'fr' : 'en',
                          );
                        },
                      ),
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}
