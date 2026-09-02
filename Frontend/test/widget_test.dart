import 'package:flutter_test/flutter_test.dart';
import 'package:lumani/core/network/api_client.dart';
import 'package:lumani/features/auth/cubit/auth_cubit.dart';
import 'package:lumani/main.dart';

void main() {
  testWidgets('LumaniApp smoke test', (WidgetTester tester) async {
    final apiClient = ApiClient();
    final authCubit = AuthCubit(apiClient: apiClient);
    await tester.pumpWidget(
      LumaniApp(apiClient: apiClient, authCubit: authCubit),
    );
    expect(find.text('LUMANI'), findsOneWidget);
  });
}
