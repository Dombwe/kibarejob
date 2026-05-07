import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/auth_provider.dart';
import '../models/swipe_model.dart';
import '../screens/application_detail_screen.dart';
import '../screens/document_upload_screen.dart';
import '../screens/documents_screen.dart';
import '../screens/login_screen.dart';
import '../screens/matches_screen.dart';
import '../screens/notifications_screen.dart';
import '../screens/profile_screen.dart';
import '../screens/register_screen.dart';
import '../screens/splash_screen.dart';
import '../screens/subscription_screen.dart';
import '../screens/swipe_screen.dart';

final appRouter = GoRouter(
  initialLocation: '/',
  routes: [
    GoRoute(path: '/', builder: (context, state) => const SplashScreen()),
    GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
    GoRoute(
      path: '/register',
      builder: (context, state) => const RegisterScreen(),
    ),
    GoRoute(
      path: '/swipe',
      builder: (context, state) => const SwipeScreen(),
    ),
    GoRoute(
      path: '/profile',
      builder: (context, state) => const ProfileScreen(),
    ),
    GoRoute(
      path: '/documents',
      builder: (context, state) => const DocumentsScreen(),
    ),
    GoRoute(
      path: '/documents/upload',
      builder: (context, state) => const DocumentUploadScreen(),
    ),
    GoRoute(
      path: '/matches',
      builder: (context, state) => const MatchesScreen(),
    ),
    GoRoute(
      path: '/matches/:id',
      builder: (context, state) {
        final application = state.extra is SwipeModel
            ? state.extra! as SwipeModel
            : SwipeModel(
                id: state.pathParameters['id'] ?? '',
                direction: 'like',
                status: 'sent',
              );
        return ApplicationDetailScreen(application: application);
      },
    ),
    GoRoute(
      path: '/notifications',
      builder: (context, state) => const NotificationsScreen(),
    ),
    GoRoute(
      path: '/subscription',
      builder: (context, state) => const SubscriptionScreen(),
    ),
  ],
);

final routerRefreshProvider = Provider<Object?>((ref) {
  return ref.watch(authControllerProvider);
});
