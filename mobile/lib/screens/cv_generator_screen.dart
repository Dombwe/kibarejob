import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/document_provider.dart';
import '../providers/profile_provider.dart';
import '../widgets/loading_widget.dart';

class CvGeneratorScreen extends ConsumerStatefulWidget {
  const CvGeneratorScreen({super.key});

  @override
  ConsumerState<CvGeneratorScreen> createState() => _CvGeneratorScreenState();
}

class _CvGeneratorScreenState extends ConsumerState<CvGeneratorScreen> {
  final _pageController = PageController();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _city = TextEditingController();
  final _birthDate = TextEditingController();
  final _jobTitle = TextEditingController();
  final _summary = TextEditingController();
  final _educationField = TextEditingController();
  final _education = TextEditingController();
  final _experiences = TextEditingController();
  final _skills = TextEditingController();
  final _languages = TextEditingController();
  final _interests = TextEditingController();
  String _educationLevel = 'Aucun';
  String _availability = 'Immédiate';
  int _step = 0;
  bool _generating = false;

  static const _educationLevels = [
    'Aucun',
    'CEP',
    'BEPC',
    'Bac',
    'Bac +1',
    'Bac +2',
    'Bac +3',
    'Bac +4',
    'Bac +5',
    'Bac +6',
    'Bac +7',
    'Bac +8',
    'Bac +9',
    'Bac +10',
    'Bac +11',
    'Bac +12',
  ];

  static const _availabilityOptions = [
    'Immédiate',
    'Sous 1 semaine',
    'Sous 2 semaines',
    'Sous 1 mois',
    'Préavis en cours',
    'Non disponible',
  ];

  @override
  void initState() {
    super.initState();
    final profile = ref.read(profileProvider).valueOrNull;
    if (profile != null) {
      _firstName.text = profile.firstName;
      _lastName.text = profile.lastName;
      _city.text = profile.city;
      _birthDate.text = profile.birthDate ?? '';
      _educationLevel = _educationLevels.contains(profile.educationLevel)
          ? profile.educationLevel
          : 'Aucun';
      _educationField.text = profile.educationField ?? '';
      _skills.text = profile.skills.join(', ');
      _experiences.text = profile.experiences.join('\n');
      _interests.text = profile.interests.join(', ');
      _languages.text = profile.languages
          .map((language) => language['name']?.toString() ?? '')
          .where((name) => name.isNotEmpty)
          .join(', ');
      _availability = _availabilityOptions.contains(profile.availability)
          ? profile.availability
          : 'Immédiate';
    }
  }

  @override
  void dispose() {
    _pageController.dispose();
    _firstName.dispose();
    _lastName.dispose();
    _city.dispose();
    _birthDate.dispose();
    _jobTitle.dispose();
    _summary.dispose();
    _educationField.dispose();
    _education.dispose();
    _experiences.dispose();
    _skills.dispose();
    _languages.dispose();
    _interests.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final totalSteps = _steps.length;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Générer un CV'),
      ),
      body: _generating
          ? const LoadingWidget(message: 'Génération de votre CV...')
          : SafeArea(
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 6),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        LinearProgressIndicator(
                          value: (_step + 1) / totalSteps,
                          minHeight: 8,
                          borderRadius: BorderRadius.circular(999),
                        ),
                        const SizedBox(height: 10),
                        Text(
                          'Étape ${_step + 1} sur $totalSteps',
                          style: Theme.of(context).textTheme.labelLarge,
                        ),
                      ],
                    ),
                  ),
                  Expanded(
                    child: PageView(
                      controller: _pageController,
                      physics: const NeverScrollableScrollPhysics(),
                      onPageChanged: (value) => setState(() => _step = value),
                      children: _steps,
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      children: [
                        if (_step > 0)
                          Expanded(
                            child: OutlinedButton(
                              onPressed: _previous,
                              child: const Text('Retour'),
                            ),
                          ),
                        if (_step > 0) const SizedBox(width: 12),
                        Expanded(
                          child: FilledButton(
                            onPressed:
                                _step == totalSteps - 1 ? _generateCv : _next,
                            child: Text(
                              _step == totalSteps - 1
                                  ? 'Générer mon CV'
                                  : 'Continuer',
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  List<Widget> get _steps => [
        _StepContent(
          title: 'Vos informations',
          subtitle: 'Ces éléments permettent d’identifier clairement votre CV.',
          children: [
            TextField(
              controller: _lastName,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Nom'),
            ),
            TextField(
              controller: _firstName,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Prénom(s)'),
            ),
            TextField(
              controller: _city,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Ville'),
            ),
            TextField(
              controller: _birthDate,
              readOnly: true,
              decoration: const InputDecoration(
                labelText: 'Date de naissance',
                suffixIcon: Icon(Icons.calendar_today_outlined),
              ),
              onTap: _pickBirthDate,
            ),
          ],
        ),
        _StepContent(
          title: 'Votre objectif',
          subtitle: 'Dites en quelques mots le type de poste recherché.',
          children: [
            TextField(
              controller: _jobTitle,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                labelText: 'Poste recherché',
                hintText: 'Ex : Assistant comptable',
              ),
            ),
            TextField(
              controller: _summary,
              minLines: 4,
              maxLines: 6,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                labelText: 'Résumé professionnel',
                hintText:
                    'Décrivez votre profil, vos forces et ce que vous recherchez.',
              ),
            ),
          ],
        ),
        _StepContent(
          title: 'Formation',
          subtitle: 'Ajoutez votre niveau et vos diplômes principaux.',
          children: [
            DropdownButtonFormField<String>(
              value: _educationLevel,
              decoration: const InputDecoration(labelText: 'Niveau d’études'),
              items: [
                for (final level in _educationLevels)
                  DropdownMenuItem(value: level, child: Text(level)),
              ],
              onChanged: (value) {
                if (value != null) {
                  setState(() => _educationLevel = value);
                }
              },
            ),
            TextField(
              controller: _educationField,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                labelText: 'Domaine d’étude',
                hintText: 'Ex : Gestion, Informatique, Marketing',
              ),
            ),
            TextField(
              controller: _education,
              minLines: 3,
              maxLines: 5,
              decoration: const InputDecoration(
                labelText: 'Diplômes ou formations',
                hintText: 'Un élément par ligne',
              ),
            ),
          ],
        ),
        _StepContent(
          title: 'Expériences',
          subtitle: 'Indiquez vos expériences les plus importantes.',
          children: [
            TextField(
              controller: _experiences,
              minLines: 6,
              maxLines: 8,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                labelText: 'Expériences professionnelles',
                hintText:
                    'Ex : Assistant RH chez FasoCom - gestion des dossiers du personnel',
              ),
            ),
          ],
        ),
        _StepContent(
          title: 'Compétences',
          subtitle: 'Listez vos compétences, langues et centres d’intérêt.',
          children: [
            TextField(
              controller: _skills,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                labelText: 'Compétences',
                hintText: 'Ex : Excel, communication, vente',
              ),
            ),
            TextField(
              controller: _languages,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                labelText: 'Langues',
                hintText: 'Ex : Français, anglais, mooré',
              ),
            ),
            TextField(
              controller: _interests,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                labelText: 'Centres d’intérêt',
                hintText: 'Ex : Lecture, sport, bénévolat',
              ),
            ),
            DropdownButtonFormField<String>(
              value: _availability,
              decoration: const InputDecoration(labelText: 'Disponibilité'),
              items: [
                for (final option in _availabilityOptions)
                  DropdownMenuItem(value: option, child: Text(option)),
              ],
              onChanged: (value) {
                if (value != null) {
                  setState(() => _availability = value);
                }
              },
            ),
          ],
        ),
      ];

  void _next() {
    FocusScope.of(context).unfocus();
    _pageController.nextPage(
      duration: const Duration(milliseconds: 220),
      curve: Curves.easeOut,
    );
  }

  void _previous() {
    FocusScope.of(context).unfocus();
    _pageController.previousPage(
      duration: const Duration(milliseconds: 220),
      curve: Curves.easeOut,
    );
  }

  Future<void> _pickBirthDate() async {
    final initial = DateTime.tryParse(_birthDate.text) ?? DateTime(2000);
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(1940),
      lastDate: DateTime.now(),
    );
    if (picked == null) return;

    _birthDate.text = '${picked.year.toString().padLeft(4, '0')}-'
        '${picked.month.toString().padLeft(2, '0')}-'
        '${picked.day.toString().padLeft(2, '0')}';
  }

  Future<void> _generateCv() async {
    setState(() => _generating = true);
    try {
      final document = await ref.read(documentProvider.notifier).generateCv({
        'firstName': _firstName.text.trim(),
        'lastName': _lastName.text.trim(),
        'birthDate': _birthDate.text.trim(),
        'city': _city.text.trim(),
        'jobTitle': _jobTitle.text.trim(),
        'summary': _summary.text.trim(),
        'educationLevel': _educationLevel,
        'educationField': _educationField.text.trim(),
        'education': _lines(_education.text),
        'experiences': _lines(_experiences.text),
        'skills': _items(_skills.text),
        'languages': _items(_languages.text),
        'interests': _items(_interests.text),
        'availability': _availability,
      });

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('CV généré avec succès.')),
      );
      context.pushReplacement('/documents/${document.id}', extra: document);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Génération impossible : $error')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _generating = false);
      }
    }
  }

  List<String> _items(String value) {
    return value
        .split(RegExp(r'[,;\n]+'))
        .map((item) => item.trim())
        .where((item) => item.isNotEmpty)
        .toList();
  }

  List<String> _lines(String value) {
    return value
        .split('\n')
        .map((item) => item.trim())
        .where((item) => item.isNotEmpty)
        .toList();
  }
}

class _StepContent extends StatelessWidget {
  const _StepContent({
    required this.title,
    required this.subtitle,
    required this.children,
  });

  final String title;
  final String subtitle;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 24),
      children: [
        Text(title, style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 8),
        Text(subtitle, style: Theme.of(context).textTheme.bodyMedium),
        const SizedBox(height: 20),
        for (final child in children) ...[
          child,
          const SizedBox(height: 14),
        ],
      ],
    );
  }
}
