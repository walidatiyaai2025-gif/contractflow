import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../navigation/navigation_policy.dart';
import '../ui/safecontracts_design.dart';
import 'mobile_user_guide_translations.dart';

final class MobileUserGuideScreen extends StatelessWidget {
  const MobileUserGuideScreen({
    required this.destinations,
    super.key,
  });

  final List<MobileDestination> destinations;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final entries = destinations
        .map(_entryFor)
        .whereType<_GuideEntry>()
        .toList(growable: false);

    return Scaffold(
      backgroundColor: SafeContractsVisual.background,
      appBar: AppBar(
        backgroundColor: SafeContractsVisual.background,
        surfaceTintColor: Colors.transparent,
        title: Text(mobileGuideText(l10n, 'User Guide')),
      ),
      body: SafeContractsBackdrop(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
          children: [
            SafeContractsSurface(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      mobileGuideText(l10n, 'How to use Alkenzy ADV'),
                      style:
                          Theme.of(context).textTheme.headlineSmall?.copyWith(
                                color: SafeContractsVisual.ink,
                                fontWeight: FontWeight.w800,
                              ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      mobileGuideText(
                        l10n,
                        'Only sections available to your account are shown.',
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      mobileGuideText(
                        l10n,
                        'Choose records by name from available lists. Internal IDs and system codes are not user inputs.',
                      ),
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: SafeContractsVisual.muted,
                            fontWeight: FontWeight.w600,
                          ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 14),
            ...entries.map(
              (entry) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _GuideCard(entry: entry),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final class _GuideCard extends StatelessWidget {
  const _GuideCard({required this.entry});

  final _GuideEntry entry;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final area = l10n.t(entry.title);
    return SafeContractsSurface(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: SafeContractsVisual.navySoft,
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Icon(entry.icon, color: SafeContractsVisual.navy),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    area,
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          color: SafeContractsVisual.ink,
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Text(
              mobileGuideText(l10n, 'What this area does'),
              style: Theme.of(context)
                  .textTheme
                  .titleSmall
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            Text(mobileGuideText(l10n, entry.purpose)),
            const SizedBox(height: 12),
            Text(
              mobileGuideText(l10n, 'Recommended steps'),
              style: Theme.of(context)
                  .textTheme
                  .titleSmall
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            ...entry.steps.indexed.map(
              (item) => Padding(
                padding: const EdgeInsets.only(bottom: 5),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${item.$1 + 1}. '),
                    Expanded(child: Text(mobileGuideText(l10n, item.$2))),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 8),
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: FilledButton.tonalIcon(
                onPressed: () => Navigator.of(context).pop(entry.destination),
                icon: const Icon(Icons.arrow_forward_rounded),
                label: Text(
                  mobileGuideText(l10n, 'Go to {area}')
                      .replaceAll('{area}', area),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final class _GuideEntry {
  const _GuideEntry({
    required this.destination,
    required this.title,
    required this.icon,
    required this.purpose,
    required this.steps,
  });

  final MobileDestination destination;
  final String title;
  final IconData icon;
  final String purpose;
  final List<String> steps;
}

_GuideEntry? _entryFor(MobileDestination destination) {
  return switch (destination) {
    MobileDestination.dashboard => const _GuideEntry(
        destination: MobileDestination.dashboard,
        title: 'Dashboard',
        icon: Icons.home_rounded,
        purpose:
            'Dashboard shows your current operational position and the most important payment indicators.',
        steps: <String>[
          'Review the indicators and active filters first.',
          'Open the related customer, contract or payment list when you need the source records.',
        ],
      ),
    MobileDestination.dashboardTwo => const _GuideEntry(
        destination: MobileDestination.dashboardTwo,
        title: 'Dashboard Two',
        icon: Icons.dashboard_customize_rounded,
        purpose:
            'Dashboard Two provides the premium executive view across customer and supplier contracts using the same authorized server data.',
        steps: <String>[
          'Review the all-contract total, contract mix and financial pulse for the current scope.',
          'Open recent contract activity to use the premium contract details view with payments and attachments.',
        ],
      ),
    MobileDestination.customers => const _GuideEntry(
        destination: MobileDestination.customers,
        title: 'Customers',
        icon: Icons.people_alt_outlined,
        purpose:
            'Customers contains the customer records available in your authorized scope.',
        steps: <String>[
          'Search for an existing customer before creating a new record.',
          'Open the customer to review its authorized details and related work.',
        ],
      ),
    MobileDestination.suppliers => const _GuideEntry(
        destination: MobileDestination.suppliers,
        title: 'Suppliers',
        icon: Icons.local_shipping_outlined,
        purpose:
            'Suppliers contains supplier records used by payable contracts and finance operations.',
        steps: <String>[
          'Find the supplier by name before starting supplier-side work.',
          'Open Contracts or Finance for the supplier-related obligations.',
        ],
      ),
    MobileDestination.contracts => const _GuideEntry(
        destination: MobileDestination.contracts,
        title: 'Contracts',
        icon: Icons.folder_copy_outlined,
        purpose:
            'Contracts contains customer receivable and supplier payable agreements available to your account.',
        steps: <String>[
          'Choose the business entity from the provided list instead of typing an internal ID.',
          'Review dates, direction and financial values before saving or editing a contract.',
        ],
      ),
    MobileDestination.payments => const _GuideEntry(
        destination: MobileDestination.payments,
        title: 'Payments',
        icon: Icons.receipt_long_outlined,
        purpose:
            'Payments contains contractual due schedule entries and their remaining balances.',
        steps: <String>[
          'Use filters to find the required payment by business context.',
          'Open collection entry only when the selected payment is the one you intend to settle.',
        ],
      ),
    MobileDestination.finance => const _GuideEntry(
        destination: MobileDestination.finance,
        title: 'Finance',
        icon: Icons.account_balance_wallet_outlined,
        purpose:
            'Finance keeps Accounts Payable and Accounts Receivable separated by direction and currency.',
        steps: <String>[
          'Start from the summary, aging or work queue that matches your task.',
          'Open the related contract or counterparty when you need source details.',
        ],
      ),
    MobileDestination.collections => const _GuideEntry(
        destination: MobileDestination.collections,
        title: 'Collections',
        icon: Icons.payments_outlined,
        purpose:
            'Collections records money received against authorized receivable payments.',
        steps: <String>[
          'Choose the payment and payment method from the provided lists.',
          'Review amount, date and reference before recording the receipt.',
        ],
      ),
    MobileDestination.followUps => const _GuideEntry(
        destination: MobileDestination.followUps,
        title: 'Follow-up',
        icon: Icons.timeline_outlined,
        purpose:
            'Follow-up tracks contact and escalation activity for outstanding receivables.',
        steps: <String>[
          'Choose the outstanding payment from the queue instead of entering a payment ID.',
          'Review previous follow-up history before adding the next action.',
        ],
      ),
    MobileDestination.notifications => const _GuideEntry(
        destination: MobileDestination.notifications,
        title: 'Notifications',
        icon: Icons.notifications_outlined,
        purpose:
            'Notifications shows notification activity available to this mobile configuration.',
        steps: <String>[
          'Open a notification to follow its supported business destination.',
          'Use the destination screen for the actual business action.',
        ],
      ),
    MobileDestination.export => const _GuideEntry(
        destination: MobileDestination.export,
        title: 'Excel export',
        icon: Icons.file_download_outlined,
        purpose:
            'Excel export creates authorized report output for the current scope.',
        steps: <String>[
          'Review the active scope and filters before creating an export.',
          'Use the exported file only for the authorized business purpose.',
        ],
      ),
    MobileDestination.profile => const _GuideEntry(
        destination: MobileDestination.profile,
        title: 'Profile',
        icon: Icons.person_outline,
        purpose:
            'Profile contains your session, language and mobile account settings.',
        steps: <String>[
          'Use language settings here when you need to switch English or Arabic.',
          'Use sign-out or session controls here rather than changing authentication data elsewhere.',
        ],
      ),
  };
}
