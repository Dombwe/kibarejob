import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/document_model.dart';
import '../models/job_model.dart';
import '../providers/auth_provider.dart';
import '../models/swipe_model.dart';
import '../screens/application_detail_screen.dart';
import '../screens/document_upload_screen.dart';
import '../screens/document_viewer_screen.dart';
import '../screens/documents_screen.dart';
import '../screens/job_detail_screen.dart';
import '../screens/login_screen.dart';
import '../screens/matches_screen.dart';
import '../screens/notifications_screen.dart';
import '../screens/profile_screen.dart';
import '../screens/register_screen.dart';
import '../screens/search_screen.dart';
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
      path: '/jobs/:id',
      builder: (context, state) {
        final job = state.extra is JobModel
            ? state.extra! as JobModel
            : JobModel(
                id: state.pathParameters['id'] ?? '',
                title: 'Offre indisponible',
                description:
                    'Les details de cette offre ne sont pas disponibles.',
                location: '',
                contractType: '',
                requiredSkills: const [],
                deadline: null,
              );
        return JobDetailScreen(job: job);
      },
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
      path: '/documents/:id',
      builder: (context, state) {
        final document = state.extra;
        if (document is! DocumentModel) {
          return const DocumentsScreen();
        }

        return DocumentViewerScreen(document: document);
      },
    ),
    GoRoute(
      path: '/matches',
      builder: (context, state) => const MatchesScreen(),
    ),
    GoRoute(
      path: '/search',
      builder: (context, state) => const SearchScreen(),
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
