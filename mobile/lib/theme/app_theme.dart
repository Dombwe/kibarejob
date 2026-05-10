import 'package:flutter/material.dart';

class AppColors {
  static const primary = Color(0xFF263849);
  static const secondary = Color(0xFF5F7F99);
  static const accent = Color(0xFFB8C7B4);
  static const lightBackground = Color(0xFFF8FAFC);
  static const darkBackground = Color(0xFF111827);
  static const darkSurface = Color(0xFF1E293B);
  static const slateText = Color(0xFF0F172A);
}

class AppTheme {
  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      brightness: Brightness.light,
      primary: AppColors.primary,
      secondary: AppColors.secondary,
      tertiary: AppColors.accent,
      surface: Colors.white,
    );

    return _base(scheme).copyWith(
      scaffoldBackgroundColor: AppColors.lightBackground,
      cardColor: Colors.white,
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.primary,
        centerTitle: false,
        elevation: 0,
        titleTextStyle: TextStyle(
          color: AppColors.primary,
          fontSize: 20,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }

  static ThemeData dark() {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.secondary,
      brightness: Brightness.dark,
      primary: AppColors.accent,
      secondary: AppColors.secondary,
      tertiary: AppColors.accent,
      surface: AppColors.darkSurface,
    );

    return _base(scheme).copyWith(
      scaffoldBackgroundColor: AppColors.darkBackground,
      cardColor: AppColors.darkSurface,
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.lightBackground,
        centerTitle: false,
        elevation: 0,
        titleTextStyle: TextStyle(
          color: AppColors.lightBackground,
          fontSize: 20,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }

  static ThemeData _base(ColorScheme scheme) {
    final isDark = scheme.brightness == Brightness.dark;
    final borderColor =
        isDark ? Colors.white.withValues(alpha: 0.16) : const Color(0xFFE2E8F0);

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      fontFamily: 'Roboto',
      textTheme: TextTheme(
        headlineLarge: TextStyle(
          color: isDark ? AppColors.lightBackground : AppColors.slateText,
          fontSize: 32,
          fontWeight: FontWeight.w900,
          height: 1.08,
        ),
        headlineMedium: TextStyle(
          color: isDark ? AppColors.lightBackground : AppColors.slateText,
          fontSize: 24,
          fontWeight: FontWeight.w900,
        ),
        headlineSmall: TextStyle(
          color: isDark ? AppColors.lightBackground : AppColors.slateText,
          fontSize: 20,
          fontWeight: FontWeight.w800,
        ),
        bodyLarge: TextStyle(
          color: isDark ? const Color(0xFFCBD5E1) : const Color(0xFF475569),
          fontSize: 16,
          fontWeight: FontWeight.w600,
          height: 1.5,
        ),
        bodyMedium: TextStyle(
          color: isDark ? const Color(0xFFCBD5E1) : const Color(0xFF64748B),
          fontSize: 14,
          fontWeight: FontWeight.w600,
          height: 1.45,
        ),
        labelLarge: TextStyle(
          color: scheme.primary,
          fontSize: 14,
          fontWeight: FontWeight.w800,
        ),
      ),
      cardTheme: CardTheme(
        elevation: 0,
        color: isDark ? AppColors.darkSurface : Colors.white,
        shadowColor: AppColors.primary.withValues(alpha: 0.08),
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(24),
          side: BorderSide(color: borderColor),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor:
              isDark ? AppColors.lightBackground : AppColors.primary,
          side: BorderSide(color: borderColor),
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: borderColor),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: borderColor),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: scheme.secondary, width: 1.4),
        ),
        labelStyle: TextStyle(
          color: isDark ? const Color(0xFFCBD5E1) : const Color(0xFF64748B),
          fontWeight: FontWeight.w700,
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor:
            isDark ? const Color(0xFF0F172A) : const Color(0xFFF1F5F9),
        labelStyle: TextStyle(
          color: isDark ? const Color(0xFFE2E8F0) : const Color(0xFF475569),
          fontWeight: FontWeight.w700,
        ),
        side: BorderSide(color: borderColor),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    );
  }
}
