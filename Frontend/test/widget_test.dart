import 'package:flutter_test/flutter_test.dart';
import 'package:lumani/core/network/api_client.dart';
import 'package:lumani/main.dart';

void main() {
  testWidgets('LumaniApp smoke test', (WidgetTester tester) async {
    final apiClient = ApiClient();
    await tester.pumpWidget(LumaniApp(apiClient: apiClient));
    expect(find.text('LUMANI'), findsOneWidget);
  });
}
