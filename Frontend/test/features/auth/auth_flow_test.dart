import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lumani/core/network/api_client.dart';
import 'package:lumani/core/state/locale_cubit.dart';
import 'package:lumani/core/state/subsystem_cubit.dart';
import 'package:lumani/features/auth/cubit/auth_cubit.dart';
import 'package:lumani/features/auth/presentation/auth_entry_screen.dart';

void main() {
  group('Auth Entry Screen & Widget Tests', () {
    late ApiClient mockApiClient;
    late AuthCubit authCubit;

    setUp(() {
      mockApiClient = ApiClient();
      authCubit = AuthCubit(apiClient: mockApiClient);
    });

    testWidgets('Renders AuthEntryScreen with Sign In and Sign Up tabs', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MultiBlocProvider(
          providers: [
            BlocProvider(create: (_) => LocaleCubit()),
            BlocProvider(create: (_) => SubsystemCubit()),
            BlocProvider.value(value: authCubit),
          ],
          child: const MaterialApp(home: AuthEntryScreen()),
        ),
      );

      expect(find.text('Lumani'), findsOneWidget);
      expect(find.text('Sign Up'), findsOneWidget);
      expect(find.text('Email Address'), findsOneWidget);
      expect(find.text('Password'), findsOneWidget);
      expect(find.widgetWithText(ElevatedButton, 'Sign In'), findsOneWidget);
    });

    testWidgets('Validates empty login fields when Sign In button is tapped', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MultiBlocProvider(
          providers: [
            BlocProvider(create: (_) => LocaleCubit()),
            BlocProvider(create: (_) => SubsystemCubit()),
            BlocProvider.value(value: authCubit),
          ],
          child: const MaterialApp(home: AuthEntryScreen()),
        ),
      );

      final signInButton = find.widgetWithText(ElevatedButton, 'Sign In');
      expect(signInButton, findsOneWidget);

      await tester.tap(signInButton);
      await tester.pump();

      expect(find.text('Please enter your email address.'), findsOneWidget);
      expect(find.text('Please enter your password.'), findsOneWidget);
    });
  });
}
