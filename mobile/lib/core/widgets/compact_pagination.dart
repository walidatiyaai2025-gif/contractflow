import 'package:flutter/material.dart';

/// Shared compact pagination for server-authoritative list endpoints.
///
/// Consumers own the request/controller logic. This widget only reflects the
/// authoritative page metadata and disables navigation while a request is in
/// flight, preventing visual-only pagination semantics.
final class CompactPagination extends StatelessWidget {
  const CompactPagination({
    required this.page,
    required this.totalPages,
    required this.isLoading,
    required this.previousLabel,
    required this.nextLabel,
    required this.onPrevious,
    required this.onNext,
    this.total,
    this.resultLabelBuilder,
    super.key,
  });

  final int page;
  final int totalPages;
  final int? total;
  final bool isLoading;
  final String previousLabel;
  final String nextLabel;
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;
  final String Function(int total)? resultLabelBuilder;

  @override
  Widget build(BuildContext context) {
    assert(page >= 1);
    assert(totalPages >= 1);
    assert(page <= totalPages);

    final count = total;
    if (totalPages <= 1) {
      if (count == null || resultLabelBuilder == null) {
        return const SizedBox.shrink();
      }
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Text(
          resultLabelBuilder!(count),
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.labelSmall,
        ),
      );
    }

    final canPrevious = !isLoading && page > 1 && onPrevious != null;
    final canNext = !isLoading && page < totalPages && onNext != null;

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 4, 12, 8),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            height: 38,
            child: Row(
              children: [
                Expanded(
                  child: TextButton.icon(
                    onPressed: canPrevious ? onPrevious : null,
                    icon: const Icon(Icons.chevron_left_rounded, size: 18),
                    label: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(previousLabel),
                    ),
                  ),
                ),
                Container(
                  constraints: const BoxConstraints(minWidth: 64),
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  alignment: Alignment.center,
                  child: isLoading
                      ? const SizedBox.square(
                          dimension: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(
                          '$page / $totalPages',
                          maxLines: 1,
                          style:
                              Theme.of(context).textTheme.labelLarge?.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                        ),
                ),
                Expanded(
                  child: TextButton.icon(
                    onPressed: canNext ? onNext : null,
                    iconAlignment: IconAlignment.end,
                    icon: const Icon(Icons.chevron_right_rounded, size: 18),
                    label: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(nextLabel),
                    ),
                  ),
                ),
              ],
            ),
          ),
          if (count != null && resultLabelBuilder != null) ...[
            const SizedBox(height: 2),
            Text(
              resultLabelBuilder!(count),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.labelSmall,
            ),
          ],
        ],
      ),
    );
  }
}
