import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';

final class AlkenzyCompanyWelcomeScreen extends StatelessWidget {
  const AlkenzyCompanyWelcomeScreen({
    required this.languageCode,
    required this.onSignIn,
    this.onLanguageChanged,
    super.key,
  });

  static const _background = Color(0xFF07131C);
  static const _surface = Color(0xFF111B24);
  static const _cyan = Color(0xFF72C7D5);
  static const _yellow = Color(0xFFFFD857);

  final String languageCode;
  final VoidCallback onSignIn;
  final ValueChanged<String>? onLanguageChanged;

  bool get _isArabic => languageCode.toLowerCase() == 'ar';

  @override
  Widget build(BuildContext context) {
    final copy = _isArabic ? _WelcomeCopy.arabic : _WelcomeCopy.english;
    final selectedLanguage = _isArabic ? 'ar' : 'en';
    final media = MediaQuery.of(context);
    final textScaler = media.textScaler.clamp(
      minScaleFactor: 1,
      maxScaleFactor: 1.45,
    );

    return Scaffold(
      backgroundColor: _background,
      body: SafeArea(
        child: MediaQuery(
          data: media.copyWith(textScaler: textScaler),
          child: LayoutBuilder(
            builder: (context, constraints) {
              final horizontalPadding = constraints.maxWidth < 380 ? 16.0 : 22.0;
              return SingleChildScrollView(
                padding: EdgeInsets.fromLTRB(
                  horizontalPadding,
                  14,
                  horizontalPadding,
                  28,
                ),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 620),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _Header(
                          selectedLanguage: selectedLanguage,
                          copy: copy,
                          onLanguageChanged: onLanguageChanged,
                        ),
                        const SizedBox(height: 16),
                        _BillboardHero(copy: copy),
                        const SizedBox(height: 26),
                        Text(
                          copy.title,
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                height: 1.14,
                              ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          copy.highlight,
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                                color: _cyan,
                                fontWeight: FontWeight.w900,
                                height: 1.15,
                              ),
                        ),
                        const SizedBox(height: 14),
                        Text(
                          copy.summary,
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                                color: Colors.white.withValues(alpha: 0.78),
                                height: 1.65,
                              ),
                        ),
                        const SizedBox(height: 22),
                        _FeatureGrid(copy: copy),
                        const SizedBox(height: 24),
                        FilledButton.icon(
                          key: const Key('companyWelcomeSignIn'),
                          onPressed: onSignIn,
                          style: FilledButton.styleFrom(
                            minimumSize: const Size.fromHeight(58),
                            backgroundColor: _yellow,
                            foregroundColor: const Color(0xFF111111),
                            textStyle: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                            ),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(18),
                            ),
                          ),
                          icon: const Icon(Icons.login_rounded),
                          label: Text(copy.signIn),
                        ),
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          key: const Key('companyWelcomeLearnMore'),
                          onPressed: () => _showAbout(context, copy),
                          style: OutlinedButton.styleFrom(
                            minimumSize: const Size.fromHeight(54),
                            foregroundColor: _cyan,
                            side: const BorderSide(color: _cyan, width: 1.4),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(18),
                            ),
                          ),
                          icon: const Icon(Icons.info_outline_rounded),
                          label: Text(
                            copy.learnMore,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                        const SizedBox(height: 18),
                        _TrustFooter(copy: copy),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  Future<void> _showAbout(BuildContext context, _WelcomeCopy copy) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: const Color(0xFFF9FBFC),
      builder: (context) => SafeArea(
        top: false,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(24, 6, 24, 30),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  const SafeContractsBrandMark(size: 52, borderRadius: 16),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      copy.aboutTitle,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Text(
                copy.aboutBody,
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(height: 1.65),
              ),
              const SizedBox(height: 22),
              _ContactTile(
                icon: Icons.phone_outlined,
                title: copy.contact,
                value: '01000272232 · 01017030397',
              ),
              const SizedBox(height: 10),
              _ContactTile(
                icon: Icons.location_on_outlined,
                title: copy.office,
                value: copy.officeAddress,
              ),
              const SizedBox(height: 18),
              FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                child: Text(copy.close),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _Header extends StatelessWidget {
  const _Header({
    required this.selectedLanguage,
    required this.copy,
    required this.onLanguageChanged,
  });

  final String selectedLanguage;
  final _WelcomeCopy copy;
  final ValueChanged<String>? onLanguageChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const SafeContractsBrandMark(size: 54, borderRadius: 16),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Alkenzy',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
              ),
              Text(
                copy.agency,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AlkenzyCompanyWelcomeScreen._cyan,
                      fontWeight: FontWeight.w700,
                    ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        SegmentedButton<String>(
          segments: const <ButtonSegment<String>>[
            ButtonSegment<String>(value: 'en', label: Text('EN')),
            ButtonSegment<String>(value: 'ar', label: Text('ع')),
          ],
          selected: <String>{selectedLanguage},
          onSelectionChanged: onLanguageChanged == null
              ? null
              : (selection) => onLanguageChanged!(selection.first),
          showSelectedIcon: false,
          style: ButtonStyle(
            visualDensity: VisualDensity.compact,
            foregroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? const Color(0xFF0D1720)
                  : Colors.white,
            ),
            backgroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? AlkenzyCompanyWelcomeScreen._yellow
                  : Colors.transparent,
            ),
            side: const WidgetStatePropertyAll(
              BorderSide(color: Color(0xFF37515E)),
            ),
          ),
        ),
      ],
    );
  }
}

final class _BillboardHero extends StatelessWidget {
  const _BillboardHero({required this.copy});

  final _WelcomeCopy copy;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      image: true,
      label: copy.heroSemanticLabel,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(28),
        child: AspectRatio(
          aspectRatio: 1.34,
          child: Stack(
            fit: StackFit.expand,
            children: [
              Image.asset(
                'assets/brand/alkenzy_company_billboards.jpg',
                fit: BoxFit.cover,
                alignment: Alignment.center,
                filterQuality: FilterQuality.high,
                excludeFromSemantics: true,
              ),
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: <Color>[
                      Color(0x20000000),
                      Color(0x22000000),
                      Color(0xDD07131C),
                    ],
                    stops: <double>[0, 0.54, 1],
                  ),
                ),
              ),
              PositionedDirectional(
                start: 20,
                end: 20,
                bottom: 20,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      copy.welcome,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: AlkenzyCompanyWelcomeScreen._cyan,
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      copy.agency,
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _FeatureGrid extends StatelessWidget {
  const _FeatureGrid({required this.copy});

  final _WelcomeCopy copy;

  @override
  Widget build(BuildContext context) {
    final features = <({IconData icon, String title, String subtitle})>[
      (
        icon: Icons.workspace_premium_outlined,
        title: copy.experienceTitle,
        subtitle: copy.experienceSubtitle,
      ),
      (
        icon: Icons.insights_outlined,
        title: copy.strategyTitle,
        subtitle: copy.strategySubtitle,
      ),
      (
        icon: Icons.campaign_outlined,
        title: copy.outdoorTitle,
        subtitle: copy.outdoorSubtitle,
      ),
      (
        icon: Icons.devices_outlined,
        title: copy.digitalTitle,
        subtitle: copy.digitalSubtitle,
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 520 ? 4 : 2;
        final spacing = 12.0;
        final cardWidth =
            (constraints.maxWidth - (spacing * (columns - 1))) / columns;
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [
            for (final feature in features)
              SizedBox(
                width: cardWidth,
                child: _FeatureCard(
                  icon: feature.icon,
                  title: feature.title,
                  subtitle: feature.subtitle,
                ),
              ),
          ],
        );
      },
    );
  }
}

final class _FeatureCard extends StatelessWidget {
  const _FeatureCard({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 150),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AlkenzyCompanyWelcomeScreen._surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFF22323D)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          DecoratedBox(
            decoration: BoxDecoration(
              color: AlkenzyCompanyWelcomeScreen._cyan.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Padding(
              padding: const EdgeInsets.all(10),
              child: Icon(
                icon,
                color: AlkenzyCompanyWelcomeScreen._cyan,
                size: 26,
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            title,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Colors.white.withValues(alpha: 0.64),
                  height: 1.35,
                ),
          ),
        ],
      ),
    );
  }
}

final class _TrustFooter extends StatelessWidget {
  const _TrustFooter({required this.copy});

  final _WelcomeCopy copy;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Icon(
          Icons.verified_user_outlined,
          color: AlkenzyCompanyWelcomeScreen._cyan,
          size: 22,
        ),
        const SizedBox(width: 8),
        Flexible(
          child: Text(
            copy.secureAccess,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Colors.white.withValues(alpha: 0.64),
                  fontWeight: FontWeight.w700,
                ),
          ),
        ),
      ],
    );
  }
}

final class _ContactTile extends StatelessWidget {
  const _ContactTile({
    required this.icon,
    required this.title,
    required this.value,
  });

  final IconData icon;
  final String title;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFF1F6F8),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          Icon(icon, color: const Color(0xFF287D8F)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
                const SizedBox(height: 2),
                SelectableText(value),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

final class _WelcomeCopy {
  const _WelcomeCopy({
    required this.agency,
    required this.welcome,
    required this.heroSemanticLabel,
    required this.title,
    required this.highlight,
    required this.summary,
    required this.experienceTitle,
    required this.experienceSubtitle,
    required this.strategyTitle,
    required this.strategySubtitle,
    required this.outdoorTitle,
    required this.outdoorSubtitle,
    required this.digitalTitle,
    required this.digitalSubtitle,
    required this.signIn,
    required this.learnMore,
    required this.secureAccess,
    required this.aboutTitle,
    required this.aboutBody,
    required this.contact,
    required this.office,
    required this.officeAddress,
    required this.close,
  });

  final String agency;
  final String welcome;
  final String heroSemanticLabel;
  final String title;
  final String highlight;
  final String summary;
  final String experienceTitle;
  final String experienceSubtitle;
  final String strategyTitle;
  final String strategySubtitle;
  final String outdoorTitle;
  final String outdoorSubtitle;
  final String digitalTitle;
  final String digitalSubtitle;
  final String signIn;
  final String learnMore;
  final String secureAccess;
  final String aboutTitle;
  final String aboutBody;
  final String contact;
  final String office;
  final String officeAddress;
  final String close;

  static const english = _WelcomeCopy(
    agency: 'Alkenzy Advertising Agency',
    welcome: 'Welcome to',
    heroSemanticLabel: 'Alkenzy outdoor advertising billboards in Giza',
    title: 'Advertising built on experience',
    highlight: 'Planning, execution, and measurable impact',
    summary:
        'Alkenzy specializes in advertising strategy, planning, and campaign execution across outdoor media, print, digital channels, social media, internet, and television.',
    experienceTitle: '10+ years',
    experienceSubtitle: 'Proven advertising-market experience',
    strategyTitle: 'Marketing strategy',
    strategySubtitle: 'Planning and ideas built around each campaign',
    outdoorTitle: 'Outdoor & print',
    outdoorSubtitle: 'Road advertising and advertising publications',
    digitalTitle: 'Digital & TV',
    digitalSubtitle: 'Social, internet, and television campaigns',
    signIn: 'Sign in',
    learnMore: 'Learn more about Alkenzy',
    secureAccess: 'Secure access for authorized Alkenzy ADV business users',
    aboutTitle: 'Who we are',
    aboutBody:
        'Alkenzy is specialized in advertising, planning, and implementing advertising campaigns. With more than ten years of experience and work with major organizations and companies, the agency provides campaign planning and execution, design services, marketing ideas, road advertising, printed advertising, social-media campaigns, internet advertising, and television advertising.',
    contact: 'Contact us',
    office: 'Office address',
    officeAddress: '57 Khatam Al-Morselin, Giza',
    close: 'Close',
  );

  static const arabic = _WelcomeCopy(
    agency: 'الكنزي للإعلان',
    welcome: 'مرحباً بك في',
    heroSemanticLabel: 'لوحات إعلانية لشركة الكنزي في الجيزة',
    title: 'خبرة إعلانية تصنع الفرق',
    highlight: 'تخطيط وتنفيذ وتأثير قابل للقياس',
    summary:
        'الكنزي شركة متخصصة في الإعلان والتخطيط وتنفيذ الحملات الإعلانية عبر الإعلانات الطرقية والمطبوعات، ومواقع التواصل الاجتماعي والإنترنت والتلفزيون.',
    experienceTitle: 'أكثر من 10 سنوات',
    experienceSubtitle: 'خبرة عملية في سوق الإعلان',
    strategyTitle: 'استراتيجيات تسويقية',
    strategySubtitle: 'تخطيط وأفكار مصممة لكل حملة',
    outdoorTitle: 'طرقي ومطبوع',
    outdoorSubtitle: 'إعلانات طرقية ومطبوعات إعلانية',
    digitalTitle: 'رقمي وتلفزيوني',
    digitalSubtitle: 'حملات اجتماعية وإنترنت وتلفزيون',
    signIn: 'تسجيل الدخول',
    learnMore: 'اعرف المزيد عن الكنزي',
    secureAccess: 'دخول آمن لمستخدمي أعمال Alkenzy ADV المصرح لهم',
    aboutTitle: 'من نحن',
    aboutBody:
        'الكنزي شركة متخصصة في مجال الإعلان والتخطيط وتنفيذ الحملات الإعلانية. بخبرة تزيد عن عشر سنوات وشهادة كبرى الجهات والشركات، تقدم الشركة تخطيط وتنفيذ الحملات، وخدمات التصميم والأفكار التسويقية، والإعلانات الطرقية والمطبوعة، والحملات على مواقع التواصل الاجتماعي والإنترنت والتلفزيون.',
    contact: 'تواصل معنا',
    office: 'عنوان المكتب',
    officeAddress: '57 خاتم المرسلين، الجيزة',
    close: 'إغلاق',
  );
}
