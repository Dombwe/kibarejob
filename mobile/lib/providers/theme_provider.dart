import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'auth_provider.dart';
import '../services/storage_service.dart';

final themeControllerProvider =
    StateNotifierProvider<ThemeController, ThemeMode>((ref) {
  return ThemeController(ref.watch(storageServiceProvider));
});

class ThemeController extends StateNotifier<ThemeMode> {
  ThemeController(this._storageService)
      : super(_modeFromValue(_storageService.themeMode));

  final StorageService _storageService;

  Future<void> toggle() async {
    final next = state == ThemeMode.dark ? ThemeMode.light : ThemeMode.dark;
    state = next;
    await _storageService.saveThemeMode(next.name);
  }

  static ThemeMode _modeFromValue(String? value) {
    return switch (value) {
      'dark' => ThemeMode.dark,
      'light' => ThemeMode.light,
      _ => ThemeMode.system,
    };
  }
}
