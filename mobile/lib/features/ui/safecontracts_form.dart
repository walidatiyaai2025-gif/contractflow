import 'package:flutter/material.dart';

import 'safecontracts_design.dart';
import 'safecontracts_tokens.dart';

typedef SafeContractsValidator = String? Function(String? value);

final class SafeContractsTextField extends StatelessWidget {
  const SafeContractsTextField({
    required this.label,
    this.controller,
    this.hint,
    this.icon,
    this.suffix,
    this.keyboardType,
    this.textInputAction,
    this.validator,
    this.onChanged,
    this.onSubmitted,
    this.enabled = true,
    this.obscureText = false,
    this.autocorrect = true,
    this.enableSuggestions = true,
    this.autofillHints,
    this.maxLines = 1,
    this.minLines,
    super.key,
  });

  final String label;
  final TextEditingController? controller;
  final String? hint;
  final IconData? icon;
  final Widget? suffix;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final SafeContractsValidator? validator;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final bool enabled;
  final bool obscureText;
  final bool autocorrect;
  final bool enableSuggestions;
  final Iterable<String>? autofillHints;
  final int? maxLines;
  final int? minLines;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      enabled: enabled,
      obscureText: obscureText,
      autocorrect: autocorrect,
      enableSuggestions: enableSuggestions,
      autofillHints: autofillHints,
      keyboardType: keyboardType,
      textInputAction: textInputAction,
      validator: validator,
      onChanged: onChanged,
      onFieldSubmitted: onSubmitted,
      maxLines: obscureText ? 1 : maxLines,
      minLines: obscureText ? 1 : minLines,
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        prefixIcon: icon == null ? null : Icon(icon),
        suffixIcon: suffix,
      ),
    );
  }
}

final class SafeContractsEmailField extends StatelessWidget {
  const SafeContractsEmailField({
    required this.label,
    this.controller,
    this.validator,
    this.enabled = true,
    super.key,
  });

  final String label;
  final TextEditingController? controller;
  final SafeContractsValidator? validator;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return SafeContractsTextField(
      label: label,
      controller: controller,
      enabled: enabled,
      icon: Icons.email_outlined,
      keyboardType: TextInputType.emailAddress,
      textInputAction: TextInputAction.next,
      autofillHints: const <String>[AutofillHints.email],
      autocorrect: false,
      validator: validator,
    );
  }
}

final class SafeContractsPhoneField extends StatelessWidget {
  const SafeContractsPhoneField({
    required this.label,
    this.controller,
    this.validator,
    this.enabled = true,
    super.key,
  });

  final String label;
  final TextEditingController? controller;
  final SafeContractsValidator? validator;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return SafeContractsTextField(
      label: label,
      controller: controller,
      enabled: enabled,
      icon: Icons.phone_outlined,
      keyboardType: TextInputType.phone,
      textInputAction: TextInputAction.next,
      autofillHints: const <String>[AutofillHints.telephoneNumber],
      validator: validator,
    );
  }
}

final class SafeContractsPasswordField extends StatelessWidget {
  const SafeContractsPasswordField({
    required this.label,
    required this.obscureText,
    required this.onToggleVisibility,
    this.controller,
    this.validator,
    this.enabled = true,
    this.onSubmitted,
    super.key,
  });

  final String label;
  final bool obscureText;
  final VoidCallback onToggleVisibility;
  final TextEditingController? controller;
  final SafeContractsValidator? validator;
  final bool enabled;
  final ValueChanged<String>? onSubmitted;

  @override
  Widget build(BuildContext context) {
    return SafeContractsTextField(
      label: label,
      controller: controller,
      enabled: enabled,
      obscureText: obscureText,
      icon: Icons.lock_outline_rounded,
      textInputAction: TextInputAction.done,
      autofillHints: const <String>[AutofillHints.password],
      autocorrect: false,
      enableSuggestions: false,
      validator: validator,
      onSubmitted: onSubmitted,
      suffix: IconButton(
        onPressed: enabled ? onToggleVisibility : null,
        icon: Icon(
          obscureText
              ? Icons.visibility_outlined
              : Icons.visibility_off_outlined,
        ),
      ),
    );
  }
}

final class SafeContractsDropdownField<T> extends StatelessWidget {
  const SafeContractsDropdownField({
    required this.label,
    required this.items,
    required this.itemLabel,
    this.value,
    this.onChanged,
    this.validator,
    this.icon = Icons.keyboard_arrow_down_rounded,
    this.enabled = true,
    super.key,
  });

  final String label;
  final List<T> items;
  final String Function(T value) itemLabel;
  final T? value;
  final ValueChanged<T?>? onChanged;
  final FormFieldValidator<T>? validator;
  final IconData icon;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<T>(
      initialValue: value,
      items: items
          .map(
            (item) => DropdownMenuItem<T>(
              value: item,
              child: Text(
                itemLabel(item),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          )
          .toList(growable: false),
      onChanged: enabled ? onChanged : null,
      validator: validator,
      isExpanded: true,
      icon: Icon(icon),
      decoration: InputDecoration(labelText: label),
    );
  }
}

final class SafeContractsDateField extends StatelessWidget {
  const SafeContractsDateField({
    required this.label,
    required this.value,
    required this.onTap,
    this.enabled = true,
    this.errorText,
    super.key,
  });

  final String label;
  final String value;
  final VoidCallback onTap;
  final bool enabled;
  final String? errorText;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(SafeContractsRadii.md),
      onTap: enabled ? onTap : null,
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          errorText: errorText,
          prefixIcon: const Icon(Icons.calendar_month_outlined),
          suffixIcon: const Icon(Icons.expand_more_rounded),
          enabled: enabled,
        ),
        child: Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: value.trim().isEmpty
                ? SafeContractsVisual.muted
                : SafeContractsVisual.ink,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }
}

final class SafeContractsMoneyField extends StatelessWidget {
  const SafeContractsMoneyField({
    required this.label,
    this.controller,
    this.currency,
    this.validator,
    this.enabled = true,
    this.onChanged,
    super.key,
  });

  final String label;
  final TextEditingController? controller;
  final String? currency;
  final SafeContractsValidator? validator;
  final bool enabled;
  final ValueChanged<String>? onChanged;

  @override
  Widget build(BuildContext context) {
    return SafeContractsTextField(
      label: label,
      controller: controller,
      enabled: enabled,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      textInputAction: TextInputAction.next,
      icon: Icons.payments_outlined,
      suffix: currency == null || currency!.trim().isEmpty
          ? null
          : Padding(
              padding: const EdgeInsetsDirectional.only(end: 12),
              child: Center(
                widthFactor: 1,
                child: Text(
                  currency!.trim(),
                  style: const TextStyle(
                    color: SafeContractsVisual.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
      validator: validator,
      onChanged: onChanged,
    );
  }
}

final class SafeContractsTextarea extends StatelessWidget {
  const SafeContractsTextarea({
    required this.label,
    this.controller,
    this.validator,
    this.enabled = true,
    this.hint,
    this.minLines = 4,
    this.maxLines = 7,
    super.key,
  });

  final String label;
  final TextEditingController? controller;
  final SafeContractsValidator? validator;
  final bool enabled;
  final String? hint;
  final int minLines;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    return SafeContractsTextField(
      label: label,
      controller: controller,
      validator: validator,
      enabled: enabled,
      hint: hint,
      icon: Icons.notes_rounded,
      minLines: minLines,
      maxLines: maxLines,
      keyboardType: TextInputType.multiline,
      textInputAction: TextInputAction.newline,
    );
  }
}

final class SafeContractsFilePickerField extends StatelessWidget {
  const SafeContractsFilePickerField({
    required this.label,
    required this.actionLabel,
    required this.onTap,
    this.fileName,
    this.icon = Icons.attach_file_rounded,
    this.enabled = true,
    super.key,
  });

  final String label;
  final String actionLabel;
  final VoidCallback onTap;
  final String? fileName;
  final IconData icon;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(SafeContractsSpacing.md),
      decoration: BoxDecoration(
        color: SafeContractsVisual.surface,
        borderRadius: BorderRadius.circular(SafeContractsRadii.md),
        border: Border.all(color: SafeContractsVisual.outline),
      ),
      child: Row(
        children: [
          Container(
            width: SafeContractsControlSizes.touchTarget,
            height: SafeContractsControlSizes.touchTarget,
            decoration: BoxDecoration(
              color: SafeContractsVisual.roseGoldSoft,
              borderRadius: BorderRadius.circular(SafeContractsRadii.sm),
            ),
            child: Icon(icon, color: SafeContractsVisual.navy),
          ),
          const SizedBox(width: SafeContractsSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
                if (fileName != null && fileName!.trim().isNotEmpty) ...[
                  const SizedBox(height: SafeContractsSpacing.xxs),
                  Text(
                    fileName!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: SafeContractsVisual.muted,
                        ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: SafeContractsSpacing.xs),
          OutlinedButton(
            onPressed: enabled ? onTap : null,
            child: Text(actionLabel),
          ),
        ],
      ),
    );
  }
}
