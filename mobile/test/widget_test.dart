import 'package:flutter_test/flutter_test.dart';
import 'package:kibarejob_mobile/app.dart';

void main() {
  test('KIBARE-JOB app widget is available', () {
    const app = KibareJobApp();

    expect(app, isA<KibareJobApp>());
  });
}
