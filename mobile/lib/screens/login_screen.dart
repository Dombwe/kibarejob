import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:go_router/go_router.dart';

import '../providers/auth_provider.dart';
import '../theme/app_theme.dart';
import '../widgets/kibare_logo.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;
  bool _isResending = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    await ref.read(authControllerProvider.notifier).login(
          _emailController.text.trim(),
          _passwordController.text,
        );
  }

  Future<void> _loginWithGoogle() async {
    await ref.read(authControllerProvider.notifier).loginWithGoogle();
  }

  Future<void> _resendVerification() async {
    final email = _emailController.text.trim();
    if (email.isEmpty) {
      _showMessage('Renseignez votre email pour recevoir un nouveau lien.');
      return;
    }

    setState(() => _isResending = true);
    try {
      final message = await ref
          .read(authControllerProvider.notifier)
          .resendVerification(email);
      _showMessage(message);
    } catch (error) {
      _showMessage(error.toString());
    } finally {
      if (mounted) {
        setState(() => _isResending = false);
      }
    }
  }

  // ignore: unused_element
  void _showGoogleMessage() {
    _showMessage(
      'Connexion Google prête côté interface. Il reste à brancher l’OAuth mobile au backend pour finaliser.',
    );
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  String _friendlyError(Object? error) {
    final message = error?.toString().replaceFirst('Exception: ', '').trim();
    if (message != null && message.isNotEmpty) {
      return message;
    }

    return 'Connexion impossible. Verifiez vos identifiants ou la configuration Google.';
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authControllerProvider);
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    ref.listen(authControllerProvider, (_, next) {
      if (next.valueOrNull != null) {
        context.go('/swipe');
      }

      if (next.hasError) {
        _showMessage(_friendlyError(next.error));
      }
    });

    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: isDark
                ? const [AppColors.darkBackground, Color(0xFF162033)]
                : const [
                    AppColors.lightBackground,
                    Colors.white,
                    Color(0xFFF6F7F4)
                  ],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(20, 24, 20, 96),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 460),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const _AuthBrandHeader(
                      title: 'Bon retour',
                      subtitle:
                          'Connectez-vous avec votre email confirmé pour retrouver vos offres et candidatures.',
                    ),
                    const SizedBox(height: 24),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(20),
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              TextFormField(
                                controller: _emailController,
                                keyboardType: TextInputType.emailAddress,
                                textInputAction: TextInputAction.next,
                                decoration: const InputDecoration(
                                  labelText: 'Adresse email',
                                  prefixIcon:
                                      Icon(Icons.alternate_email_rounded),
                                ),
                                validator: (value) {
                                  final email = value?.trim() ?? '';
                                  if (email.isEmpty || !email.contains('@')) {
                                    return 'Entrez une adresse email valide.';
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 14),
                              TextFormField(
                                controller: _passwordController,
                                obscureText: _obscurePassword,
                                textInputAction: TextInputAction.done,
                                onFieldSubmitted: (_) => _login(),
                                decoration: InputDecoration(
                                  labelText: 'Mot de passe',
                                  prefixIcon:
                                      const Icon(Icons.lock_outline_rounded),
                                  suffixIcon: IconButton(
                                    onPressed: () {
                                      setState(() {
                                        _obscurePassword = !_obscurePassword;
                                      });
                                    },
                                    icon: Icon(
                                      _obscurePassword
                                          ? Icons.visibility_outlined
                                          : Icons.visibility_off_outlined,
                                    ),
                                  ),
                                ),
                                validator: (value) {
                                  if ((value ?? '').length < 6) {
                                    return 'Le mot de passe est requis.';
                                  }
                                  return null;
                                },
                              ),
                              Align(
                                alignment: Alignment.centerRight,
                                child: TextButton(
                                  onPressed:
                                      _isResending ? null : _resendVerification,
                                  child: Text(
                                    _isResending
                                        ? 'Envoi en cours...'
                                        : 'Renvoyer le lien de vérification',
                                  ),
                                ),
                              ),
                              if (authState.hasError) ...[
                                const SizedBox(height: 8),
                                _AuthNotice(
                                  icon: Icons.mark_email_unread_rounded,
                                  message:
                                      'Connexion impossible. Vérifiez vos identifiants et confirmez votre email si vous venez de créer un compte.',
                                  isError: true,
                                ),
                              ],
                              const SizedBox(height: 18),
                              FilledButton(
                                onPressed: authState.isLoading ? null : _login,
                                child: authState.isLoading
                                    ? const SizedBox(
                                        height: 22,
                                        width: 22,
                                        child: CircularProgressIndicator(
                                            strokeWidth: 2),
                                      )
                                    : const Text('Se connecter'),
                              ),
                              const SizedBox(height: 14),
                              OutlinedButton.icon(
                                onPressed: authState.isLoading
                                    ? null
                                    : _loginWithGoogle,
                                icon: SvgPicture.asset(
                                  'assets/branding/google_g.svg',
                                  height: 22,
                                  width: 22,
                                ),
                                label: const Text('Continuer avec Google'),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text('Pas encore de compte ?',
                            style: theme.textTheme.bodyMedium),
                        TextButton(
                          onPressed: () => context.go('/register'),
                          child: const Text('Créer un compte'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _AuthBrandHeader extends StatelessWidget {
  const _AuthBrandHeader({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const KibareLogo(size: 58),
        const SizedBox(height: 22),
        Text(title, style: theme.textTheme.headlineLarge),
        const SizedBox(height: 10),
        Text(subtitle, style: theme.textTheme.bodyLarge),
      ],
    );
  }
}

class _AuthNotice extends StatelessWidget {
  const _AuthNotice({
    required this.icon,
    required this.message,
    this.isError = false,
  });

  final IconData icon;
  final String message;
  final bool isError;

  @override
  Widget build(BuildContext context) {
    final color = isError
        ? Theme.of(context).colorScheme.error
        : Theme.of(context).colorScheme.secondary;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: color.withValues(alpha: 0.18)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ),
        ],
      ),
    );
  }
}
