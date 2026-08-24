import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import 'safecontracts_components.dart';
import 'safecontracts_design.dart';
import 'safecontracts_tokens.dart';

enum SafeContractsSplashState { loading, error }

final class SafeContractsSplash extends StatelessWidget {
  const SafeContractsSplash({
    required this.label,
    this.environmentLabel,
    this.state = SafeContractsSplashState.loading,
    this.message,
    this.retryLabel,
    this.onRetry,
    this.blockBack = false,
    super.key,
  });

  final String label;
  final String? environmentLabel;
  final SafeContractsSplashState state;
  final String? message;
  final String? retryLabel;
  final VoidCallback? onRetry;
  final bool blockBack;

  @override
  Widget build(BuildContext context) {
    final child = Scaffold(
      backgroundColor: SafeContractsVisual.navyDeep,
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[
              Color(0xFF061B2F),
              SafeContractsVisual.navyDeep,
              SafeContractsVisual.navy,
            ],
          ),
        ),
        child: Stack(
          fit: StackFit.expand,
          children: [
            const _SplashOrnament(),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(SafeContractsSpacing.xl),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 430),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Align(
                          child: Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.08),
                              borderRadius: BorderRadius.circular(
                                SafeContractsRadii.xl,
                              ),
                              border: Border.all(
                                color: Colors.white.withValues(alpha: 0.12),
                              ),
                              boxShadow: SafeContractsShadows.navy,
                            ),
                            child: const SafeContractsBrandMark(
                              size: 92,
                              borderRadius: 26,
                            ),
                          ),
                        ),
                        const SizedBox(height: SafeContractsSpacing.xl),
                        Text(
                          SafeContractsBrand.name,
                          textAlign: TextAlign.center,
                          style: Theme.of(context)
                              .textTheme
                              .headlineMedium
                              ?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                letterSpacing: -0.6,
                              ),
                        ),
                        const SizedBox(height: SafeContractsSpacing.xs),
                        Text(
                          label,
                          textAlign: TextAlign.center,
                          style:
                              Theme.of(context).textTheme.bodyLarge?.copyWith(
                                    color: Colors.white.withValues(alpha: 0.76),
                                    height: 1.5,
                                  ),
                        ),
                        if (environmentLabel != null &&
                            environmentLabel!.trim().isNotEmpty) ...[
                          const SizedBox(height: SafeContractsSpacing.sm),
                          Align(
                            child: SafeContractsStatusChip(
                              label: environmentLabel!,
                              tone: SafeContractsStatusTone.info,
                              icon: Icons.shield_outlined,
                            ),
                          ),
                        ],
                        const SizedBox(height: SafeContractsSpacing.xl),
                        if (state == SafeContractsSplashState.loading)
                          const Align(
                            child: SizedBox.square(
                              dimension: 34,
                              child: CircularProgressIndicator(
                                strokeWidth: 3,
                                color: SafeContractsVisual.roseGold,
                              ),
                            ),
                          )
                        else
                          SafeContractsCard(
                            elevated: false,
                            accent: SafeContractsVisual.red,
                            child: Column(
                              children: [
                                const Icon(
                                  Icons.error_outline_rounded,
                                  color: SafeContractsVisual.red,
                                  size: SafeContractsIconSizes.lg,
                                ),
                                if (message != null &&
                                    message!.trim().isNotEmpty) ...[
                                  const SizedBox(
                                    height: SafeContractsSpacing.xs,
                                  ),
                                  Text(
                                    message!,
                                    textAlign: TextAlign.center,
                                  ),
                                ],
                                if (onRetry != null) ...[
                                  const SizedBox(
                                    height: SafeContractsSpacing.md,
                                  ),
                                  SafeContractsButton(
                                    label: retryLabel ?? 'Retry',
                                    onPressed: onRetry,
                                    variant: SafeContractsButtonVariant.outline,
                                  ),
                                ],
                              ],
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
    if (!blockBack) return child;
    return PopScope(canPop: false, child: child);
  }
}

final class _SplashOrnament extends StatelessWidget {
  const _SplashOrnament();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Stack(
        children: [
          PositionedDirectional(
            top: -120,
            end: -110,
            child: Container(
              width: 300,
              height: 300,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: SafeContractsVisual.roseGold.withValues(alpha: 0.16),
                  width: 34,
                ),
              ),
            ),
          ),
          PositionedDirectional(
            bottom: -95,
            start: -100,
            child: Container(
              width: 260,
              height: 260,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: SafeContractsVisual.roseGold.withValues(alpha: 0.06),
                border: Border.all(
                  color: SafeContractsVisual.champagne.withValues(alpha: 0.08),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
