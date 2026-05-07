import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/document_provider.dart';
import '../services/compression_service.dart';
import '../widgets/loading_widget.dart';

class DocumentUploadScreen extends ConsumerStatefulWidget {
  const DocumentUploadScreen({super.key});

  @override
  ConsumerState<DocumentUploadScreen> createState() => _DocumentUploadScreenState();
}

class _DocumentUploadScreenState extends ConsumerState<DocumentUploadScreen> {
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  String _type = 'diploma';
  File? _file;
  bool _isUploading = false;

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Ajouter un document')),
      body: _isUploading
          ? const LoadingWidget(message: 'Envoi du document...')
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                DropdownButtonFormField<String>(
                  value: _type,
                  decoration: const InputDecoration(labelText: 'Type'),
                  items: const [
                    DropdownMenuItem(value: 'diploma', child: Text('Diplome')),
                    DropdownMenuItem(value: 'certificate', child: Text('Certificat')),
                    DropdownMenuItem(value: 'attestation', child: Text('Attestation')),
                    DropdownMenuItem(value: 'other', child: Text('Autre')),
                  ],
                  onChanged: (value) => setState(() => _type = value ?? 'other'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _titleController,
                  decoration: const InputDecoration(labelText: 'Titre'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _descriptionController,
                  minLines: 3,
                  maxLines: 5,
                  decoration: const InputDecoration(labelText: 'Description'),
                ),
                const SizedBox(height: 20),
                OutlinedButton.icon(
                  onPressed: _pickFile,
                  icon: const Icon(Icons.attach_file),
                  label: Text(_file == null ? 'Choisir un fichier' : _file!.path.split(Platform.pathSeparator).last),
                ),
                const SizedBox(height: 20),
                FilledButton.icon(
                  onPressed: _file == null ? null : _upload,
                  icon: const Icon(Icons.cloud_upload_outlined),
                  label: const Text('Envoyer'),
                ),
              ],
            ),
    );
  }

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'],
    );
    final path = result?.files.single.path;
    if (path == null) {
      return;
    }
    setState(() => _file = File(path));
  }

  Future<void> _upload() async {
    final file = _file;
    if (file == null) {
      return;
    }
    setState(() => _isUploading = true);
    try {
      final lowerPath = file.path.toLowerCase();
      final uploadFile = lowerPath.endsWith('.jpg') ||
              lowerPath.endsWith('.jpeg') ||
              lowerPath.endsWith('.png') ||
              lowerPath.endsWith('.webp')
          ? await CompressionService().compressImage(file)
          : file;
      await ref.read(documentProvider.notifier).uploadDocument(
            file: uploadFile,
            title: _titleController.text.trim(),
            type: _type,
            description: _descriptionController.text.trim(),
          );
      if (mounted) {
        context.go('/documents');
      }
    } finally {
      if (mounted) {
        setState(() => _isUploading = false);
      }
    }
  }
}
