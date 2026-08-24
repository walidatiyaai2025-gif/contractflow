import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'safecontracts_design.dart';
import 'safecontracts_tokens.dart';
import 'theme_palette.dart';

abstract final class SafeContractsTheme {
  static ThemeData build(String languageCode, AlkenzyThemePalette palette) {
    final isArabic = languageCode.trim().toLowerCase().startsWith('ar');
    final baseTextTheme = isArabic
        ? GoogleFonts.cairoTextTheme()
        : GoogleFonts.interTextTheme();
    final fontFamily = isArabic
        ? GoogleFonts.cairo().fontFamily
        : GoogleFonts.inter().fontFamily;

    final scheme = ColorScheme.light(
      primary: palette.primary,
      onPrimary: Colors.white,
      primaryContainer: palette.soft,
      onPrimaryContainer: palette.deep,
      secondary: palette.accent,
      onSecondary: Colors.white,
      secondaryContainer: palette.accent.withValues(alpha: 0.18),
      onSecondaryContainer: SafeContractsVisual.ink,
      tertiary: SafeContractsVisual.green,
      onTertiary: Colors.white,
      error: SafeContractsVisual.red,
      onError: Colors.white,
      surface: SafeContractsVisual.surface,
      onSurface: SafeContractsVisual.ink,
      outline: SafeContractsVisual.outline,
      outlineVariant: Color(0xFFE6DCD2),
      surfaceContainerLowest: Color(0xFFFFFEFC),
      surfaceContainerLow: SafeContractsVisual.backgroundRaised,
      surfaceContainer: Color(0xFFF0E9E1),
      surfaceContainerHigh: Color(0xFFE8DDD1),
      surfaceContainerHighest: Color(0xFFDFD3C7),
    );

    final enabledBorder = OutlineInputBorder(
      borderRadius: BorderRadius.circular(SafeContractsRadii.md),
      borderSide: const BorderSide(color: SafeContractsVisual.outline),
    );

    final textTheme = baseTextTheme
        .copyWith(
          displayLarge: baseTextTheme.displayLarge?.copyWith(
            fontSize: SafeContractsTypography.displayLarge,
            height: SafeContractsTypography.displayHeight,
          ),
          displayMedium: baseTextTheme.displayMedium?.copyWith(
            fontSize: SafeContractsTypography.displayMedium,
            height: SafeContractsTypography.displayHeight,
          ),
          displaySmall: baseTextTheme.displaySmall?.copyWith(
            fontSize: SafeContractsTypography.displaySmall,
            height: SafeContractsTypography.displayHeight,
          ),
          headlineLarge: baseTextTheme.headlineLarge?.copyWith(
            fontSize: SafeContractsTypography.headlineLarge,
            height: SafeContractsTypography.headlineHeight,
          ),
          headlineMedium: baseTextTheme.headlineMedium?.copyWith(
            fontSize: SafeContractsTypography.headlineMedium,
            height: SafeContractsTypography.headlineHeight,
          ),
          headlineSmall: baseTextTheme.headlineSmall?.copyWith(
            fontSize: SafeContractsTypography.headlineSmall,
            height: SafeContractsTypography.headlineHeight,
          ),
          titleLarge: baseTextTheme.titleLarge?.copyWith(
            fontSize: SafeContractsTypography.titleLarge,
            height: SafeContractsTypography.titleHeight,
          ),
          titleMedium: baseTextTheme.titleMedium?.copyWith(
            fontSize: SafeContractsTypography.titleMedium,
            height: SafeContractsTypography.titleHeight,
          ),
          titleSmall: baseTextTheme.titleSmall?.copyWith(
            fontSize: SafeContractsTypography.titleSmall,
            height: SafeContractsTypography.titleHeight,
          ),
          bodyLarge: baseTextTheme.bodyLarge?.copyWith(
            fontSize: SafeContractsTypography.bodyLarge,
            height: SafeContractsTypography.bodyHeight,
          ),
          bodyMedium: baseTextTheme.bodyMedium?.copyWith(
            fontSize: SafeContractsTypography.bodyMedium,
            height: SafeContractsTypography.bodyHeight,
          ),
          bodySmall: baseTextTheme.bodySmall?.copyWith(
            fontSize: SafeContractsTypography.bodySmall,
            height: SafeContractsTypography.bodyHeight,
          ),
          labelLarge: baseTextTheme.labelLarge?.copyWith(
            fontSize: SafeContractsTypography.labelLarge,
            height: SafeContractsTypography.labelHeight,
          ),
          labelMedium: baseTextTheme.labelMedium?.copyWith(
            fontSize: SafeContractsTypography.labelMedium,
            height: SafeContractsTypography.labelHeight,
          ),
          labelSmall: baseTextTheme.labelSmall?.copyWith(
            fontSize: SafeContractsTypography.labelSmall,
            height: SafeContractsTypography.labelHeight,
          ),
        )
        .apply(
          bodyColor: SafeContractsVisual.ink,
          displayColor: SafeContractsVisual.ink,
        );

    return ThemeData(
      colorScheme: scheme,
      useMaterial3: true,
      fontFamily: fontFamily,
      textTheme: textTheme,
      scaffoldBackgroundColor: SafeContractsVisual.background,
      canvasColor: SafeContractsVisual.background,
      splashFactory: InkSparkle.splashFactory,
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: palette.primary,
        foregroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        iconTheme: const IconThemeData(color: Colors.white),
        actionsIconTheme: const IconThemeData(color: Colors.white),
        titleTextStyle: textTheme.titleLarge?.copyWith(
          color: Colors.white,
          fontWeight: FontWeight.w800,
        ),
      ),
      cardTheme: CardThemeData(
        margin: EdgeInsets.zero,
        elevation: 0,
        color: SafeContractsVisual.surface,
        shadowColor: const Color(0x1F3D3028),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SafeContractsRadii.lg),
          side: const BorderSide(color: SafeContractsVisual.outline),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: SafeContractsVisual.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SafeContractsRadii.lg),
        ),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: SafeContractsVisual.surface,
        surfaceTintColor: Colors.transparent,
        modalBackgroundColor: SafeContractsVisual.surface,
        showDragHandle: true,
        dragHandleColor: SafeContractsVisual.outline,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(SafeContractsRadii.xl),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: SafeContractsVisual.surface,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: SafeContractsSpacing.md,
          vertical: 15,
        ),
        labelStyle: const TextStyle(color: SafeContractsVisual.muted),
        hintStyle: const TextStyle(color: SafeContractsVisual.muted),
        prefixIconColor: SafeContractsVisual.navy,
        suffixIconColor: SafeContractsVisual.muted,
        border: enabledBorder,
        enabledBorder: enabledBorder,
        disabledBorder: enabledBorder.copyWith(
          borderSide: const BorderSide(color: Color(0xFFE7DED5)),
        ),
        focusedBorder: enabledBorder.copyWith(
          borderSide: BorderSide(color: palette.accent, width: 1.8),
        ),
        errorBorder: enabledBorder.copyWith(
          borderSide: const BorderSide(color: SafeContractsVisual.red),
        ),
        focusedErrorBorder: enabledBorder.copyWith(
          borderSide: const BorderSide(
            color: SafeContractsVisual.red,
            width: 1.8,
          ),
        ),
        errorStyle: const TextStyle(
          color: SafeContractsVisual.redDeep,
          fontWeight: FontWeight.w600,
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: palette.primary,
          foregroundColor: Colors.white,
          disabledBackgroundColor: palette.soft,
          disabledForegroundColor: SafeContractsVisual.muted,
          minimumSize: const Size(0, SafeContractsControlSizes.buttonMinHeight),
          padding: const EdgeInsets.symmetric(
            horizontal: SafeContractsSpacing.lg,
            vertical: SafeContractsSpacing.sm,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(SafeContractsRadii.md),
          ),
          textStyle: TextStyle(
            fontWeight: FontWeight.w800,
            fontFamily: fontFamily,
          ),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: palette.accent,
          foregroundColor: Colors.white,
          elevation: 0,
          minimumSize: const Size(0, SafeContractsControlSizes.buttonMinHeight),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(SafeContractsRadii.md),
          ),
          textStyle: TextStyle(
            fontWeight: FontWeight.w800,
            fontFamily: fontFamily,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: palette.primary,
          side: BorderSide(color: palette.primary),
          minimumSize: const Size(0, SafeContractsControlSizes.buttonMinHeight),
          padding: const EdgeInsets.symmetric(
            horizontal: SafeContractsSpacing.lg,
            vertical: SafeContractsSpacing.sm,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(SafeContractsRadii.md),
          ),
          textStyle: TextStyle(
            fontWeight: FontWeight.w800,
            fontFamily: fontFamily,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: palette.primary,
          textStyle: TextStyle(
            fontWeight: FontWeight.w700,
            fontFamily: fontFamily,
          ),
        ),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: SafeContractsVisual.roseGold,
        foregroundColor: Colors.white,
        elevation: 0,
        focusElevation: 0,
        highlightElevation: 0,
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: SafeContractsControlSizes.bottomNavigationHeight,
        backgroundColor: SafeContractsVisual.surface,
        indicatorColor: SafeContractsVisual.roseGoldSoft,
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            color: states.contains(WidgetState.selected)
                ? SafeContractsVisual.navy
                : SafeContractsVisual.muted,
          ),
        ),
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => textTheme.labelSmall?.copyWith(
            color: states.contains(WidgetState.selected)
                ? SafeContractsVisual.navy
                : SafeContractsVisual.muted,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w800
                : FontWeight.w600,
          ),
        ),
      ),
      navigationDrawerTheme: const NavigationDrawerThemeData(
        backgroundColor: SafeContractsVisual.navyDeep,
        surfaceTintColor: Colors.transparent,
        indicatorColor: Color(0x26C98A7B),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: SafeContractsVisual.surface,
        selectedColor: SafeContractsVisual.roseGoldSoft,
        side: const BorderSide(color: SafeContractsVisual.outline),
        labelStyle: textTheme.labelMedium?.copyWith(
          color: SafeContractsVisual.ink,
          fontWeight: FontWeight.w700,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SafeContractsRadii.sm),
        ),
      ),
      dividerTheme: const DividerThemeData(color: SafeContractsVisual.outline),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: SafeContractsVisual.navyDeep,
        contentTextStyle: textTheme.bodyMedium?.copyWith(color: Colors.white),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SafeContractsRadii.sm),
        ),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: SafeContractsVisual.roseGold,
        linearTrackColor: SafeContractsVisual.roseGoldSoft,
      ),
      checkboxTheme: CheckboxThemeData(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SafeContractsRadii.xs / 2),
        ),
        fillColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? SafeContractsVisual.navy
              : null,
        ),
      ),
    );
  }
}
