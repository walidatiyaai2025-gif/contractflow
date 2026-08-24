import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';

final class LandingLocalizedText {
  const LandingLocalizedText({required this.en, required this.ar});

  final String en;
  final String ar;

  String resolve(String languageCode) =>
      languageCode.toLowerCase() == 'ar' ? ar : en;

  factory LandingLocalizedText.fromJson(
    Object? value,
    String field, {
    required int maximumLength,
  }) {
    final map = apiObjectMap(value, field);
    return LandingLocalizedText(
      en: _requiredText(map['en'], '$field.en', maximumLength),
      ar: _requiredText(map['ar'], '$field.ar', maximumLength),
    );
  }
}

final class MobileLandingService {
  const MobileLandingService({
    required this.key,
    required this.title,
    required this.subtitle,
  });

  final String key;
  final LandingLocalizedText title;
  final LandingLocalizedText subtitle;

  factory MobileLandingService.fromJson(Object? value, int index) {
    final map = apiObjectMap(value, 'services[$index]');
    final key = _requiredText(map['key'], 'services[$index].key', 40);
    if (!RegExp(r'^[a-z][a-z0-9_\-]*$').hasMatch(key)) {
      throw FormatException('services[$index].key is invalid.');
    }
    return MobileLandingService(
      key: key,
      title: LandingLocalizedText.fromJson(
        map['title'],
        'services[$index].title',
        maximumLength: 100,
      ),
      subtitle: LandingLocalizedText.fromJson(
        map['subtitle'],
        'services[$index].subtitle',
        maximumLength: 180,
      ),
    );
  }
}

final class MobileLandingImage {
  const MobileLandingImage({
    required this.id,
    required this.url,
    required this.alt,
  });

  final int id;
  final String url;
  final String alt;

  factory MobileLandingImage.fromJson(Object? value, int index) {
    final map = apiObjectMap(value, 'images[$index]');
    final id = _int(map['id'], 'images[$index].id');
    if (id < 1) {
      throw FormatException('images[$index].id is invalid.');
    }
    final url = _requiredText(map['url'], 'images[$index].url', 2048);
    final uri = Uri.tryParse(url);
    if (uri == null ||
        !uri.hasAuthority ||
        (uri.scheme != 'https' && uri.scheme != 'http')) {
      throw FormatException('images[$index].url is invalid.');
    }
    final altValue = map['alt'];
    if (altValue is! String || altValue.trim().length > 180) {
      throw FormatException('images[$index].alt is invalid.');
    }
    return MobileLandingImage(id: id, url: url, alt: altValue.trim());
  }
}

final class MobileLandingContent {
  const MobileLandingContent({
    required this.brandName,
    required this.agencyName,
    required this.headline,
    required this.highlight,
    required this.summary,
    required this.experienceYears,
    required this.services,
    required this.phones,
    required this.officeAddress,
    required this.images,
    required this.signInLabel,
    required this.learnMoreLabel,
  });

  final String brandName;
  final LandingLocalizedText agencyName;
  final LandingLocalizedText headline;
  final LandingLocalizedText highlight;
  final LandingLocalizedText summary;
  final int experienceYears;
  final List<MobileLandingService> services;
  final List<String> phones;
  final LandingLocalizedText officeAddress;
  final List<MobileLandingImage> images;
  final LandingLocalizedText signInLabel;
  final LandingLocalizedText learnMoreLabel;

  factory MobileLandingContent.fromJson(Object? value) {
    final map = apiObjectMap(value, 'mobile_landing');
    final schemaVersion = _int(map['schema_version'], 'schema_version');
    if (schemaVersion != 1) {
      throw const FormatException('Mobile landing schema is not supported.');
    }

    final serviceValues = apiObjectList(map['services'], 'services');
    if (serviceValues.isEmpty || serviceValues.length > 6) {
      throw const FormatException('Mobile landing services are out of range.');
    }
    final services = <MobileLandingService>[];
    final serviceKeys = <String>{};
    for (var index = 0; index < serviceValues.length; index++) {
      final service =
          MobileLandingService.fromJson(serviceValues[index], index);
      if (!serviceKeys.add(service.key)) {
        throw const FormatException(
            'Mobile landing service keys must be unique.');
      }
      services.add(service);
    }

    final contact = apiObjectMap(map['contact'], 'contact');
    final phoneValues = apiObjectList(contact['phones'], 'contact.phones');
    if (phoneValues.isEmpty || phoneValues.length > 4) {
      throw const FormatException('Mobile landing phone list is out of range.');
    }
    final phones = <String>[];
    for (var index = 0; index < phoneValues.length; index++) {
      final phone = _requiredText(
        phoneValues[index],
        'contact.phones[$index]',
        32,
      );
      if (!RegExp(r'^[0-9+() .\-]+$').hasMatch(phone)) {
        throw FormatException('contact.phones[$index] is invalid.');
      }
      phones.add(phone);
    }

    final experienceYears = _int(map['experience_years'], 'experience_years');
    if (experienceYears < 0 || experienceYears > 100) {
      throw const FormatException('experience_years is out of range.');
    }

    final imageValues = map['images'] == null
        ? const <Object?>[]
        : apiObjectList(map['images'], 'images');
    if (imageValues.length > 6) {
      throw const FormatException('Mobile landing images are out of range.');
    }
    final images = <MobileLandingImage>[];
    final imageIds = <int>{};
    for (var index = 0; index < imageValues.length; index++) {
      final image = MobileLandingImage.fromJson(imageValues[index], index);
      if (!imageIds.add(image.id)) {
        throw const FormatException('Mobile landing image IDs must be unique.');
      }
      images.add(image);
    }

    return MobileLandingContent(
      brandName: _requiredText(map['brand_name'], 'brand_name', 80),
      agencyName: LandingLocalizedText.fromJson(
        map['agency_name'],
        'agency_name',
        maximumLength: 120,
      ),
      headline: LandingLocalizedText.fromJson(
        map['headline'],
        'headline',
        maximumLength: 160,
      ),
      highlight: LandingLocalizedText.fromJson(
        map['highlight'],
        'highlight',
        maximumLength: 180,
      ),
      summary: LandingLocalizedText.fromJson(
        map['summary'],
        'summary',
        maximumLength: 700,
      ),
      experienceYears: experienceYears,
      services: List<MobileLandingService>.unmodifiable(services),
      phones: List<String>.unmodifiable(phones),
      officeAddress: LandingLocalizedText.fromJson(
        contact['office_address'],
        'contact.office_address',
        maximumLength: 240,
      ),
      images: List<MobileLandingImage>.unmodifiable(images),
      signInLabel: LandingLocalizedText.fromJson(
        map['sign_in_label'],
        'sign_in_label',
        maximumLength: 80,
      ),
      learnMoreLabel: LandingLocalizedText.fromJson(
        map['learn_more_label'],
        'learn_more_label',
        maximumLength: 80,
      ),
    );
  }

  static const fallback = MobileLandingContent(
    brandName: 'Alkenzy ADV',
    agencyName: LandingLocalizedText(
      en: 'Alkenzy Advertising Agency',
      ar: 'الكنزي للإعلان',
    ),
    headline: LandingLocalizedText(
      en: 'Advertising built on experience',
      ar: 'خبرة إعلانية تصنع الفرق',
    ),
    highlight: LandingLocalizedText(
      en: 'Planning, execution, and measurable impact',
      ar: 'تخطيط وتنفيذ وتأثير قابل للقياس',
    ),
    summary: LandingLocalizedText(
      en: 'Alkenzy specializes in advertising strategy, planning, and campaign execution across outdoor media, print, digital, social media, internet, and television.',
      ar: 'الكنزي شركة متخصصة في الإعلان والتخطيط وتنفيذ الحملات الإعلانية عبر الإعلانات الطرقية والمطبوعات والمنصات الرقمية ومواقع التواصل والإنترنت والتلفزيون.',
    ),
    experienceYears: 10,
    services: <MobileLandingService>[
      MobileLandingService(
        key: 'strategy',
        title: LandingLocalizedText(
          en: 'Marketing strategy',
          ar: 'استراتيجيات تسويقية',
        ),
        subtitle: LandingLocalizedText(
          en: 'Planning and ideas built around each campaign',
          ar: 'تخطيط وأفكار مصممة لكل حملة',
        ),
      ),
      MobileLandingService(
        key: 'outdoor',
        title: LandingLocalizedText(
          en: 'Outdoor & print',
          ar: 'طرقي ومطبوع',
        ),
        subtitle: LandingLocalizedText(
          en: 'Road advertising and advertising publications',
          ar: 'إعلانات طرقية ومطبوعات إعلانية',
        ),
      ),
      MobileLandingService(
        key: 'digital',
        title: LandingLocalizedText(
          en: 'Digital & social',
          ar: 'رقمي واجتماعي',
        ),
        subtitle: LandingLocalizedText(
          en: 'Social media and internet campaigns',
          ar: 'حملات مواقع التواصل والإنترنت',
        ),
      ),
      MobileLandingService(
        key: 'television',
        title: LandingLocalizedText(
          en: 'Television campaigns',
          ar: 'حملات تلفزيونية',
        ),
        subtitle: LandingLocalizedText(
          en: 'Creative campaign planning and execution',
          ar: 'تخطيط وتنفيذ إبداعي للحملات',
        ),
      ),
    ],
    phones: <String>['01000272232', '01017030397'],
    officeAddress: LandingLocalizedText(
      en: '57 Khatam Al-Morselin, Giza',
      ar: '57 خاتم المرسلين، الجيزة',
    ),
    images: <MobileLandingImage>[],
    signInLabel: LandingLocalizedText(
      en: 'Sign in',
      ar: 'تسجيل الدخول',
    ),
    learnMoreLabel: LandingLocalizedText(
      en: 'Learn more',
      ar: 'اعرف المزيد',
    ),
  );
}

final class MobileLandingRepository {
  const MobileLandingRepository(this.client);

  final SafeContractsApiClient client;

  Future<MobileLandingContent> load() async {
    final envelope = await client.get('mobile-landing');
    return MobileLandingContent.fromJson(envelope.data);
  }
}

enum MobileLandingState { idle, loading, ready, fallback }

final class MobileLandingController extends ChangeNotifier {
  MobileLandingController(this.repository);

  final MobileLandingRepository repository;

  MobileLandingState state = MobileLandingState.idle;
  MobileLandingContent content = MobileLandingContent.fallback;
  String? errorMessage;
  bool _inFlight = false;

  bool get usingFallback => state == MobileLandingState.fallback;

  Future<void> ensureLoaded() async {
    if (state == MobileLandingState.ready || _inFlight) return;
    await refresh();
  }

  Future<void> refresh() async {
    if (_inFlight) return;
    _inFlight = true;
    state = MobileLandingState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      content = await repository.load();
      state = MobileLandingState.ready;
    } on Object catch (error) {
      content = MobileLandingContent.fallback;
      errorMessage = error.toString();
      state = MobileLandingState.fallback;
    } finally {
      _inFlight = false;
      notifyListeners();
    }
  }
}

int _int(Object? value, String field) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null) throw FormatException('$field must be an integer.');
  return parsed;
}

String _requiredText(Object? value, String field, int maximumLength) {
  if (value is! String) {
    throw FormatException('$field must be a string.');
  }
  final normalized = value.trim();
  if (normalized.isEmpty || normalized.length > maximumLength) {
    throw FormatException('$field is outside the supported length.');
  }
  return normalized;
}
