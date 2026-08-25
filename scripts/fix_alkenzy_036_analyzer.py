#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def patch(path, fn):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    updated = fn(text)
    if updated == text:
        raise SystemExit(f'No analyzer fix applied to {path}')
    p.write_text(updated, encoding='utf-8')


def remove_class(text, class_name, next_class):
    pattern = rf"\nfinal class {re.escape(class_name)} extends StatelessWidget \{{.*?\n\}}\n\n(?=final class {re.escape(next_class)})"
    text, count = re.subn(pattern, '\n', text, count=1, flags=re.S)
    if count != 1:
        raise SystemExit(f'Could not remove {class_name}')
    return text


def contracts_screen(text):
    return remove_class(text, '_Pagination', '_StatusPill')


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
    if old not in text:
        raise SystemExit('CustomerPage merge constructor marker missing')
    return text.replace(old, new, 1)


def customers_screen(text):
    text = text.replace('    final page = controller.currentPage!;\n', '', 1)
    return remove_class(text, '_Pagination', '_CustomerCard')


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
    return remove_class(text, '_FollowUpPaging', '_FollowUpCard')


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
    return remove_class(text, '_PaymentPaging', '_PaymentCard')


def profile_model(text):
    return text.replace("import 'dart:typed_data';\n", '', 1)


def suppliers_screen(text):
    pattern = re.compile(
        r"\n  List<SafeContractsSupplier> get _visibleSuppliers =>\n      _filteredSuppliers\.take\(_visibleLimit\)\.toList\(growable: false\);\n"
    )
    text, count = pattern.subn('\n', text, count=1)
    if count != 1:
        raise SystemExit('Supplier unused getter marker missing')
    return text


def welcome_screen(text):
    return text.replace("import '../ui/safecontracts_tokens.dart';\n", '', 1)


def profile_test(text):
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
print('Applied all analyzer fixes for Alkenzy ADV 0.3.6+10.')
