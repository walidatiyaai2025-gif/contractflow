import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'safecontracts_design.dart';
import 'safecontracts_tokens.dart';

abstract final class SafeContractsTheme {
  static ThemeData build(String languageCode) {
    final isArabic = languageCode.trim().toLowerCase().startsWith('ar');
    final baseTextTheme =
        isArabic ? GoogleFonts.cairoTextTheme() : GoogleFonts.interTextTheme();
    final fontFamily = isArabic
        ? GoogleFonts.cairo().fontFamily
        : GoogleFonts.inter().fontFamily;

    const scheme = ColorScheme.light(
      primary: SafeContractsVisual.navy,
      onPrimary: Colors.white,
      primaryContainer: SafeContractsVisual.navySoft,
      onPrimaryContainer: SafeContractsVisual.navyDeep,
      secondary: SafeContractsVisual.roseGold,
      onSecondary: Colors.white,
      secondaryContainer: SafeContractsVisual.roseGoldSoft,
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

    final textTheme = baseTextTheme.apply(
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
        backgroundColor: SafeContractsVisual.navy,
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
          borderSide: const BorderSide(
            color: SafeContractsVisual.roseGold,
            width: 1.8,
          ),
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
          backgroundColor: SafeContractsVisual.navy,
          foregroundColor: Colors.white,
          disabledBackgroundColor: SafeContractsVisual.navySoft,
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
          backgroundColor: SafeContractsVisual.roseGold,
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
          foregroundColor: SafeContractsVisual.navy,
          side: const BorderSide(color: SafeContractsVisual.navy),
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
          foregroundColor: SafeContractsVisual.navy,
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
