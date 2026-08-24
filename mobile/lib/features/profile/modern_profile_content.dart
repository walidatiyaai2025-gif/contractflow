import 'package:flutter/material.dart';

import '../session/session_controller.dart';
import '../ui/mobile_layout.dart';
import 'profile_identity_sections.dart';

final class ModernProfileContent extends StatelessWidget {
  const ModernProfileContent({
    required this.session,
    required this.languageCode,
    required this.onLanguageChanged,
    required this.onLogout,
    required this.onUserGuide,
    super.key,
  });

  final SafeContractsSession session;
  final String languageCode;
  final ValueChanged<String> onLanguageChanged;
  final VoidCallback onLogout;
  final VoidCallback onUserGuide;

  @override
  Widget build(BuildContext context) {
    return SafeContractsAdaptiveBody(
      child: LayoutBuilder(
        builder: (context, constraints) {
          final content = Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ProfileHero(session: session),
              const SizedBox(height: 12),
              ProfileLanguageControl(
                languageCode: languageCode,
                onLanguageChanged: onLanguageChanged,
              ),
              const SizedBox(height: 10),
              ProfilePrimaryActions(
                onLogout: onLogout,
                onUserGuide: onUserGuide,
              ),
            ],
          );

          if (constraints.maxHeight < 430) {
            return SingleChildScrollView(
              key: const Key('profileShortDeviceScroll'),
              primary: false,
              child: content,
            );
          }
          return content;
        },
      ),
    );
  }
}
