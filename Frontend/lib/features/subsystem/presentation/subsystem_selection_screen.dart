import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../core/responsive/responsive_utils.dart';
import '../../../core/state/subsystem_cubit.dart';
import '../../../core/theme/app_dimensions.dart';

class SubsystemSelectionScreen extends StatelessWidget {
  const SubsystemSelectionScreen({super.key});

  void _selectSubsystem(BuildContext context, Subsystem subsystem) async {
    await context.read<SubsystemCubit>().setSubsystem(subsystem);
    if (context.mounted) {
      context.go('/home');
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: theme.colorScheme.surface,
      body: SafeArea(
        child: Center(
          child: AdaptiveConstraintContainer(
            maxWidth: 600,
            child: Padding(
              padding: AppDimensions.padding24,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Select Academic Subsystem',
                    style: theme.textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: theme.colorScheme.onSurface,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: AppDimensions.space8),
                  Text(
                    'Choose your educational curriculum system to customize your learning content and past papers.',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: AppDimensions.space32),
                  _SubsystemCard(
                    title: 'Anglophone Subsystem (GCE)',
                    subtitle: 'General Certificate of Education (Ordinary & Advanced Level)',
                    icon: Icons.menu_book_rounded,
                    badgeText: 'English Academic Content',
                    onTap: () =>
                        _selectSubsystem(context, Subsystem.anglophone),
                  ),
                  const SizedBox(height: AppDimensions.space16),
                  _SubsystemCard(
                    title: 'Subsystem Francophone (OBC)',
                    subtitle: 'Office du Baccalauréat du Cameroun (BEPC, Probatoire, Baccalauréat)',
                    icon: Icons.school_rounded,
                    badgeText: 'Contenu Académique Français',
                    onTap: () =>
                        _selectSubsystem(context, Subsystem.francophone),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _SubsystemCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  final String badgeText;
  final VoidCallback onTap;

  const _SubsystemCard({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.badgeText,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: theme.colorScheme.outline, width: 1.0),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: AppDimensions.padding20,
          child: Row(
            children: [
              Container(
                padding: AppDimensions.padding16,
                decoration: BoxDecoration(
                  color: theme.colorScheme.primaryContainer,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, size: 32, color: theme.colorScheme.primary),
              ),
              const SizedBox(width: AppDimensions.space16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: theme.colorScheme.secondaryContainer,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        badgeText,
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: theme.colorScheme.onSecondaryContainer,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    const SizedBox(height: AppDimensions.space4),
                    Text(
                      title,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: AppDimensions.space4),
                    Text(
                      subtitle,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppDimensions.space8),
              Icon(
                Icons.chevron_right_rounded,
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
