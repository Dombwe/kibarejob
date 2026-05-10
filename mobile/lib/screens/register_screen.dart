import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:go_router/go_router.dart';

import '../providers/auth_provider.dart';
import '../theme/app_theme.dart';
import '../widgets/kibare_logo.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _cityController = TextEditingController(text: 'Ouagadougou');
  bool _obscurePassword = true;
  bool _verificationSent = false;

  @override
  void dispose() {
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    _firstNameController.dispose();
    _lastNameController.dispose();
    _cityController.dispose();
    super.dispose();
  }

  Future<void> _register() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    await ref.read(authControllerProvider.notifier).register(
          email: _emailController.text.trim(),
          password: _passwordController.text,
          firstName: _firstNameController.text.trim(),
          lastName: _lastNameController.text.trim(),
          city: _cityController.text.trim(),
          phone: _phoneController.text.trim().isEmpty
              ? null
              : _phoneController.text.trim(),
        );

    final state = ref.read(authControllerProvider);
    if (!state.hasError && mounted) {
      setState(() => _verificationSent = true);
    }
  }

  Future<void> _createWithGoogle() async {
    await ref.read(authControllerProvider.notifier).loginWithGoogle();
    if (mounted && ref.read(authControllerProvider).valueOrNull != null) {
      context.go('/swipe');
    }
  }

  // ignore: unused_element
  void _showGoogleMessage() {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(
          content: Text(
            'Création avec Google prête côté interface. Il reste à brancher l’OAuth mobile au backend pour finaliser.',
          ),
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authControllerProvider);
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

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
                constraints: const BoxConstraints(maxWidth: 520),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        IconButton(
                          onPressed: () => context.go('/login'),
                          icon: const Icon(Icons.arrow_back_rounded),
                        ),
                        const Spacer(),
                        TextButton(
                          onPressed: () => context.go('/login'),
                          child: const Text('Connexion'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    const KibareLogo(size: 58),
                    const SizedBox(height: 22),
                    Text('Créer votre compte candidat',
                        style: theme.textTheme.headlineLarge),
                    const SizedBox(height: 10),
                    Text(
                      'Quelques informations suffisent. Vous confirmerez ensuite votre adresse email pour sécuriser le compte.',
                      style: theme.textTheme.bodyLarge,
                    ),
                    const SizedBox(height: 24),
                    if (_verificationSent) ...[
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(22),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                height: 56,
                                width: 56,
                                decoration: BoxDecoration(
                                  color:
                                      AppColors.accent.withValues(alpha: 0.35),
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                child: const Icon(
                                  Icons.mark_email_read_rounded,
                                  color: AppColors.primary,
                                ),
                              ),
                              const SizedBox(height: 18),
                              Text(
                                'Vérifiez votre boîte mail',
                                style: theme.textTheme.headlineSmall,
                              ),
                              const SizedBox(height: 10),
                              Text(
                                'Un lien de confirmation a été envoyé à ${_emailController.text.trim()}. Après confirmation, vous pourrez vous connecter.',
                                style: theme.textTheme.bodyLarge,
                              ),
                              const SizedBox(height: 18),
                              FilledButton(
                                onPressed: () => context.go('/login'),
                                child: const Text('Aller à la connexion'),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ] else
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(20),
                          child: Form(
                            key: _formKey,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: TextFormField(
                                        controller: _firstNameController,
                                        textInputAction: TextInputAction.next,
                                        decoration: const InputDecoration(
                                          labelText: 'Prénom',
                                          prefixIcon: Icon(
                                              Icons.person_outline_rounded),
                                        ),
                                        validator: _required,
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: TextFormField(
                                        controller: _lastNameController,
                                        textInputAction: TextInputAction.next,
                                        decoration: const InputDecoration(
                                          labelText: 'Nom',
                                        ),
                                        validator: _required,
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 14),
                                TextFormField(
                                  controller: _cityController,
                                  textInputAction: TextInputAction.next,
                                  decoration: const InputDecoration(
                                    labelText: 'Ville',
                                    prefixIcon:
                                        Icon(Icons.location_on_outlined),
                                  ),
                                  validator: _required,
                                ),
                                const SizedBox(height: 14),
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
                                  controller: _phoneController,
                                  keyboardType: TextInputType.phone,
                                  textInputAction: TextInputAction.next,
                                  decoration: const InputDecoration(
                                    labelText: 'Téléphone +226',
                                    prefixIcon: Icon(Icons.phone_outlined),
                                  ),
                                ),
                                const SizedBox(height: 14),
                                TextFormField(
                                  controller: _passwordController,
                                  obscureText: _obscurePassword,
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
                                    if ((value ?? '').length < 8) {
                                      return 'Utilisez au moins 8 caractères.';
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 12),
                                _HintRow(
                                  icon: Icons.verified_user_outlined,
                                  text:
                                      'Votre email sera vérifié avant la première connexion.',
                                ),
                                if (authState.hasError) ...[
                                  const SizedBox(height: 12),
                                  Text(
                                    authState.error.toString(),
                                    style: TextStyle(
                                      color: theme.colorScheme.error,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                                const SizedBox(height: 18),
                                FilledButton(
                                  onPressed:
                                      authState.isLoading ? null : _register,
                                  child: authState.isLoading
                                      ? const SizedBox(
                                          height: 22,
                                          width: 22,
                                          child: CircularProgressIndicator(
                                              strokeWidth: 2),
                                        )
                                      : const Text('Créer mon compte'),
                                ),
                                const SizedBox(height: 14),
                                OutlinedButton.icon(
                                  onPressed: authState.isLoading
                                      ? null
                                      : _createWithGoogle,
                                  icon: SvgPicture.asset(
                                    'assets/branding/google_g.svg',
                                    height: 22,
                                    width: 22,
                                  ),
                                  label: const Text('Créer avec Google'),
                                ),
                              ],
                            ),
                          ),
                        ),
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

  String? _required(String? value) {
    if ((value ?? '').trim().isEmpty) {
      return 'Champ requis.';
    }
    return null;
  }
}

class _HintRow extends StatelessWidget {
  const _HintRow({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: Theme.of(context).colorScheme.secondary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(text, style: Theme.of(context).textTheme.bodyMedium),
        ),
      ],
    );
  }
}
