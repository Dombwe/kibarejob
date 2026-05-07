import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/auth_provider.dart';
import '../widgets/loading_widget.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _cityController = TextEditingController(text: 'Ouagadougou');

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

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authControllerProvider);
    ref.listen(authControllerProvider, (_, next) {
      if (next.valueOrNull != null) {
        context.go('/swipe');
      }
    });

    return Scaffold(
      appBar: AppBar(title: const Text('Inscription')),
      body: authState.isLoading
          ? const LoadingWidget()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                TextField(controller: _firstNameController, decoration: const InputDecoration(labelText: 'Prenom')),
                const SizedBox(height: 12),
                TextField(controller: _lastNameController, decoration: const InputDecoration(labelText: 'Nom')),
                const SizedBox(height: 12),
                TextField(controller: _cityController, decoration: const InputDecoration(labelText: 'Ville')),
                const SizedBox(height: 12),
                TextField(controller: _emailController, decoration: const InputDecoration(labelText: 'Email')),
                const SizedBox(height: 12),
                TextField(controller: _phoneController, decoration: const InputDecoration(labelText: 'Telephone +226')),
                const SizedBox(height: 12),
                TextField(controller: _passwordController, obscureText: true, decoration: const InputDecoration(labelText: 'Mot de passe')),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: () => ref.read(authControllerProvider.notifier).register(
                        email: _emailController.text.trim(),
                        password: _passwordController.text,
                        firstName: _firstNameController.text.trim(),
                        lastName: _lastNameController.text.trim(),
                        city: _cityController.text.trim(),
                        phone: _phoneController.text.trim().isEmpty
                            ? null
                            : _phoneController.text.trim(),
                      ),
                  child: const Text('Creer mon compte'),
                ),
                TextButton(
                  onPressed: () => context.go('/login'),
                  child: const Text('J ai deja un compte'),
                ),
                if (authState.hasError)
                  Text(
                    authState.error.toString(),
                    style: TextStyle(color: Theme.of(context).colorScheme.error),
                  ),
              ],
            ),
    );
  }
}
