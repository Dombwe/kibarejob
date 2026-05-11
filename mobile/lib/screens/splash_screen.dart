import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/auth_provider.dart';
import '../theme/app_theme.dart';
import '../widgets/kibare_logo.dart';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  final _pageController = PageController();
  int _currentPage = 0;
  bool _isRedirecting = true;

  static const _slides = [
    _SplashSlideData(
      icon: Icons.work_history_rounded,
      title: 'Kibaré Job',
      subtitle:
          'L’application mobile qui rapproche les candidats des offres vraiment pertinentes.',
    ),
    _SplashSlideData(
      icon: Icons.swipe_rounded,
      title: 'Swipez les bonnes opportunités',
      subtitle:
          'Découvrez les offres, aimez celles qui vous intéressent et postulez plus vite.',
    ),
    _SplashSlideData(
      icon: Icons.auto_awesome_rounded,
      title: 'Un matching plus intelligent',
      subtitle:
          'Votre profil, vos documents et vos compétences aident Kibaré Job à mieux vous orienter.',
    ),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _redirectIfOnboardingSeen();
    });
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _continue() {
    if (_currentPage < _slides.length - 1) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 320),
        curve: Curves.easeOutCubic,
      );
      return;
    }

    _enterApp();
  }

  void _redirectIfOnboardingSeen() {
    final storage = ref.read(storageServiceProvider);
    if (storage.hasSeenOnboarding) {
      _goToNextScreen();
      return;
    }

    if (mounted) {
      setState(() => _isRedirecting = false);
    }
  }

  Future<void> _enterApp() async {
    await ref.read(storageServiceProvider).markOnboardingSeen();
    if (!mounted) {
      return;
    }

    _goToNextScreen();
  }

  void _goToNextScreen() {
    final user = ref.read(authControllerProvider).valueOrNull;
    context.go(user == null ? '/login' : '/swipe');
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    if (_isRedirecting) {
      return Scaffold(
        backgroundColor:
            isDark ? AppColors.darkBackground : AppColors.lightBackground,
        body: const SizedBox.expand(),
      );
    }

    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: isDark
                ? const [
                    AppColors.darkBackground,
                    Color(0xFF162033),
                    AppColors.darkSurface,
                  ]
                : const [
                    AppColors.lightBackground,
                    Colors.white,
                    Color(0xFFF6F7F4),
                  ],
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(24, 20, 24, 28),
            child: Column(
              children: [
                Row(
                  children: [
                    const KibareLogo(size: 44),
                    const SizedBox(width: 12),
                    Text(
                      'KIBARE-JOB',
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const Spacer(),
                    TextButton(
                      onPressed: () => _enterApp(),
                      child: const Text('Passer'),
                    ),
                  ],
                ),
                Expanded(
                  child: PageView.builder(
                    controller: _pageController,
                    itemCount: _slides.length,
                    onPageChanged: (index) {
                      setState(() => _currentPage = index);
                    },
                    itemBuilder: (context, index) {
                      return _SplashSlide(data: _slides[index]);
                    },
                  ),
                ),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(
                    _slides.length,
                    (index) => AnimatedContainer(
                      duration: const Duration(milliseconds: 220),
                      margin: const EdgeInsets.symmetric(horizontal: 4),
                      height: 8,
                      width: _currentPage == index ? 28 : 8,
                      decoration: BoxDecoration(
                        color: _currentPage == index
                            ? theme.colorScheme.primary
                            : theme.colorScheme.outline.withValues(alpha: 0.35),
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                FilledButton.icon(
                  onPressed: _continue,
                  icon: Icon(
                    _currentPage == _slides.length - 1
                        ? Icons.arrow_forward_rounded
                        : Icons.keyboard_arrow_right_rounded,
                  ),
                  label: Text(
                    _currentPage == _slides.length - 1
                        ? 'Commencer'
                        : 'Continuer',
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _SplashSlide extends StatelessWidget {
  const _SplashSlide({required this.data});

  final _SplashSlideData data;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Container(
          height: 196,
          width: 196,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                AppColors.secondary.withValues(alpha: isDark ? 0.42 : 0.18),
                AppColors.accent.withValues(alpha: isDark ? 0.32 : 0.45),
              ],
            ),
            border: Border.all(
              color: theme.colorScheme.outline.withValues(alpha: 0.16),
            ),
          ),
          child: Icon(
            data.icon,
            size: 82,
            color: isDark ? AppColors.accent : AppColors.primary,
          ),
        ),
        const SizedBox(height: 36),
        Text(
          data.title,
          textAlign: TextAlign.center,
          style: theme.textTheme.headlineLarge,
        ),
        const SizedBox(height: 16),
        Text(
          data.subtitle,
          textAlign: TextAlign.center,
          style: theme.textTheme.bodyLarge,
        ),
      ],
    );
  }
}

class _SplashSlideData {
  const _SplashSlideData({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;
}
