#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
changed_paths = []


def patch(path, fn):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    updated = fn(text)
    if updated != text:
        p.write_text(updated, encoding='utf-8')
        changed_paths.append(path)


def remove_class(text, class_name):
    marker = f'final class {class_name} extends StatelessWidget {{'
    start = text.find(marker)
    if start < 0:
        return text
    brace = text.find('{', start)
    if brace < 0:
        raise SystemExit(f'Malformed class {class_name}')
    depth = 0
    end = None
    for index in range(brace, len(text)):
        char = text[index]
        if char == '{':
            depth += 1
        elif char == '}':
            depth -= 1
            if depth == 0:
                end = index + 1
                break
    if end is None:
        raise SystemExit(f'Unclosed class {class_name}')
    while end < len(text) and text[end] in '\r\n':
        end += 1
    prefix = text[:start]
    while prefix.endswith('\n\n\n'):
        prefix = prefix[:-1]
    return prefix + text[end:]


def contracts_screen(text):
    return remove_class(text, '_Pagination')


def customers_model(text):
    text = text.replace(
        "final page = _boundedInt(meta['page'], 'meta.page', minimum: 1, maximum: 5);",
        "final page = _boundedInt(\n      meta['page'],\n      'meta.page',\n      minimum: 1,\n      maximum: 1000000,\n    );",
        1,
    )
    text = text.replace(
        "    if (page < 1 || page > 5) {\n      throw ArgumentError('Customer page must be between 1 and 5.');\n    }",
        "    if (page < 1 || page > 1000000) {\n      throw ArgumentError('Customer page is outside the supported range.');\n    }",
        1,
    )
    old = '''        currentPage = CustomerPage(
          customers: List<SafeContractsCustomer>.unmodifiable(merged.values),
          page: next.page,
          perPage: next.perPage,
          total: next.total,
          totalPages: next.totalPages,
          hasMore: next.hasMore,
          scope: next.scope,
        );'''
    new = '''        currentPage = CustomerPage(
          customers: List<SafeContractsCustomer>.unmodifiable(merged.values),
          page: next.page,
          perPage: next.perPage,
          sort: next.sort,
          order: next.order,
          hasMore: next.hasMore,
          boundedWindow: next.boundedWindow,
          scope: next.scope,
        );'''
    if old in text:
        text = text.replace(old, new, 1)
    return text


def customers_screen(text):
    text = text.replace('    final page = controller.currentPage!;\n', '', 1)
    return remove_class(text, '_Pagination')


def followups_screen(text):
    text = text.replace(
        "    if (page == null || !page.hasMore || _requestInFlight) return;",
        "    if (page == null || !page.hasMore || _requestInFlight) {\n      return;\n    }",
        1,
    )
    text = text.replace(
        "    if (!_scrollController.hasClients ||\n        _scrollController.position.extentAfter > 360) return;",
        "    if (!_scrollController.hasClients ||\n        _scrollController.position.extentAfter > 360) {\n      return;\n    }",
        1,
    )
    return remove_class(text, '_FollowUpPaging')


def payments_screen(text):
    text = text.replace(
        "    if (page == null || !page.hasMore || _requestInFlight) return;",
        "    if (page == null || !page.hasMore || _requestInFlight) {\n      return;\n    }",
        1,
    )
    text = text.replace(
        "    if (!_scrollController.hasClients ||\n        _scrollController.position.extentAfter > 360) return;",
        "    if (!_scrollController.hasClients ||\n        _scrollController.position.extentAfter > 360) {\n      return;\n    }",
        1,
    )
    return remove_class(text, '_PaymentPaging')


def profile_model(text):
    return text.replace("import 'dart:typed_data';\n", '', 1)


def suppliers_screen(text):
    return re.sub(
        r"\n  List<SafeContractsSupplier> get _visibleSuppliers =>\n      _filteredSuppliers\.take\(_visibleLimit\)\.toList\(growable: false\);\n",
        '\n',
        text,
        count=1,
    )


def welcome_screen(text):
    return text.replace("import '../ui/safecontracts_tokens.dart';\n", '', 1)


def profile_test(text):
    if 'avatarUploading:' in text and 'onAvatarUpload:' in text:
        return text
    old = '''            onLogout: () {},
            onUserGuide: () {},
          ),'''
    new = '''            onLogout: () {},
            onUserGuide: () {},
            avatarUrl: null,
            avatarUploading: false,
            onAvatarUpload: () {},
          ),'''
    if old not in text:
        raise SystemExit('Profile harness marker missing')
    return text.replace(old, new, 1)


patch('mobile/lib/features/contracts/contracts_screen.dart', contracts_screen)
patch('mobile/lib/features/customers/customers.dart', customers_model)
patch('mobile/lib/features/customers/customers_screen.dart', customers_screen)
patch('mobile/lib/features/followups/followups_screen.dart', followups_screen)
patch('mobile/lib/features/payments/payments_screen.dart', payments_screen)
patch('mobile/lib/features/profile/profile.dart', profile_model)
patch('mobile/lib/features/suppliers/suppliers_screen.dart', suppliers_screen)
patch('mobile/lib/features/welcome/company_welcome_screen.dart', welcome_screen)
patch('mobile/test/alkenzy_worker3_profile_auth_test.dart', profile_test)

if not changed_paths:
    raise SystemExit('No analyzer fixes were necessary; refusing an empty closure run.')
print('Applied analyzer fixes to:')
for path in changed_paths:
    print(f'- {path}')
