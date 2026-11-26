import 'dart:async';

import 'package:flutter/material.dart';

import '../ui/safecontracts_design.dart';
import 'alkenzy_rewarded_fun_service.dart';
import 'alkenzy_xo_match.dart';

/// Available before login as well as in the authenticated navigation drawer.
final class AlkenzyRewardedFunButton extends StatefulWidget {
  const AlkenzyRewardedFunButton({required this.languageCode, super.key});
  final String languageCode;

  @override
  State<AlkenzyRewardedFunButton> createState() =>
      _AlkenzyRewardedFunButtonState();
}

final class _AlkenzyRewardedFunButtonState
    extends State<AlkenzyRewardedFunButton> {
  final AlkenzyRewardedFun _fun = AlkenzyRewardedFun.instance;
  Timer? _ticker;
  String? _error;

  bool get _ar => widget.languageCode.toLowerCase().startsWith('ar');

  @override
  void initState() {
    super.initState();
    _fun.addListener(_refresh);
    unawaited(_fun.ensureLoaded());
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) => _refresh());
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _ticker?.cancel();
    _fun.removeListener(_refresh);
    super.dispose();
  }

  Future<void> _start() async {
    if (!_fun.canStart) return;
    setState(() => _error = null);
    if (!_fun.hasTicket) {
      // Explain the exact reward AND require explicit opt-in for every ad.
      final accepted = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(_ar ? 'ابدأ التسلية' : 'Start the fun'),
          content: Text(
            _ar
                ? 'شاهد إعلاناً اختيارياً لتحصل على جولة واحدة من لعبة XO ضد الكمبيوتر. يمكنك الإلغاء ومتابعة استخدام التطبيق كالمعتاد.'
                : 'Watch an optional ad to unlock one XO game against the computer. You can cancel and continue using the app normally.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(_ar ? 'إلغاء' : 'Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(_ar ? 'مشاهدة الإعلان' : 'Watch ad'),
            ),
          ],
        ),
      );
      if (accepted != true || !mounted) return;
      if (!await _fun.watchForOneGame()) {
        if (mounted) {
          setState(
            () => _error = _ar
                ? 'الإعلان غير متاح الآن، حاول لاحقاً.'
                : 'No ad is available right now. Try again later.',
          );
        }
        return;
      }
    }
    if (!mounted || !await _fun.takeTicket() || !mounted) return;
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) =>
          AlkenzyXoGameDialog(languageCode: widget.languageCode),
    );
  }

  @override
  Widget build(BuildContext context) {
    final duration = _fun.remaining();
    final seconds =
        duration.inSeconds + (duration.inMilliseconds % 1000 > 0 ? 1 : 0);
    final countdown =
        '${(seconds ~/ 60).toString().padLeft(2, '0')}:${(seconds % 60).toString().padLeft(2, '0')}';
    final cooling = !_fun.hasTicket && seconds > 0;
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: FilledButton.icon(
                key: const Key('alkenzyStartFun'),
                style: FilledButton.styleFrom(
                  backgroundColor: SafeContractsVisual.roseGoldDark,
                  foregroundColor: Colors.white,
                ),
                onPressed: _fun.canStart ? () => unawaited(_start()) : null,
                icon: const Icon(Icons.sports_esports_outlined, size: 19),
                label: Text(_ar ? 'ابدأ التسلية' : 'Start the fun'),
              ),
            ),
            const SizedBox(width: 8),
            DecoratedBox(
              decoration: BoxDecoration(
                color: SafeContractsVisual.surfaceWarm,
                border: Border.all(color: SafeContractsVisual.outline),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 9,
                  vertical: 11,
                ),
                child: Text(
                  _ar ? 'عدد المرات: ${_fun.count}' : 'Plays: ${_fun.count}',
                  key: const Key('alkenzyFunCount'),
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 3),
        Text(
          _fun.hasTicket
              ? (_ar
                    ? 'جولة XO جاهزة — اضغط للعب'
                    : 'One XO game is ready — tap to play')
              : cooling
              ? (_ar ? 'متاح مجدداً بعد $countdown' : 'Available in $countdown')
              : (_ar
                    ? 'إعلان اختياري = جولة XO واحدة ضد الكمبيوتر'
                    : 'Optional ad = one XO game against the computer'),
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: SafeContractsVisual.muted,
            fontSize: 11,
          ),
        ),
        if (_error != null)
          Text(
            _error!,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: SafeContractsVisual.red,
              fontSize: 11,
            ),
          ),
      ],
    );
  }
}

/// One game per earned ad. Closing early also consumes the game entitlement.
final class AlkenzyXoGameDialog extends StatefulWidget {
  const AlkenzyXoGameDialog({required this.languageCode, super.key});
  final String languageCode;

  @override
  State<AlkenzyXoGameDialog> createState() => _AlkenzyXoGameDialogState();
}

final class _AlkenzyXoGameDialogState extends State<AlkenzyXoGameDialog> {
  final AlkenzyXoMatch _match = AlkenzyXoMatch();

  bool get _ar => widget.languageCode.toLowerCase().startsWith('ar');

  String get _status => switch (_match.outcome) {
    XoOutcome.playing =>
      _ar ? 'أنت X — الكمبيوتر O' : 'You are X — Computer is O',
    XoOutcome.humanWon => _ar ? 'مبروك! فزت 🎉' : 'You won! 🎉',
    XoOutcome.computerWon => _ar ? 'الكمبيوتر فاز' : 'The computer won',
    XoOutcome.draw => _ar ? 'تعادل!' : 'Draw!',
  };

  @override
  Widget build(BuildContext context) {
    return Dialog(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 350),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                _ar ? 'لعبة إكس أو' : 'Tic-Tac-Toe',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 6),
              Text(_status, key: const Key('alkenzyXoStatus')),
              const SizedBox(height: 14),
              AspectRatio(
                aspectRatio: 1,
                child: GridView.builder(
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: 9,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 3,
                    mainAxisSpacing: 5,
                    crossAxisSpacing: 5,
                  ),
                  itemBuilder: (context, index) {
                    final piece = _match.cells[index];
                    final symbol = switch (piece) {
                      XoPiece.human => 'X',
                      XoPiece.computer => 'O',
                      XoPiece.empty => '',
                    };
                    return Material(
                      color: SafeContractsVisual.surfaceWarm,
                      borderRadius: BorderRadius.circular(10),
                      child: InkWell(
                        key: Key('alkenzyXoCell$index'),
                        borderRadius: BorderRadius.circular(10),
                        onTap:
                            _match.outcome == XoOutcome.playing &&
                                piece == XoPiece.empty
                            ? () => setState(() => _match.humanMove(index))
                            : null,
                        child: Center(
                          child: Text(
                            symbol,
                            style: TextStyle(
                              color: piece == XoPiece.human
                                  ? SafeContractsVisual.navy
                                  : SafeContractsVisual.roseGoldDark,
                              fontSize: 38,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                child: Text(
                  _match.outcome == XoOutcome.playing
                      ? (_ar ? 'إنهاء الجولة' : 'End game')
                      : (_ar ? 'تم' : 'Done'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
