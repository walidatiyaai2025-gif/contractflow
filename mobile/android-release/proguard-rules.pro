# App-level R8 rules for Alkenzy ADV Android release.
#
# Do NOT add blanket -keep class ** { *; } or -dontobfuscate directives:
# Play Console needs real shrinking/obfuscation, and such rules defeat R8.
#
# Flutter embedding, Firebase, Google Mobile Ads, and other Android plugins
# contribute their own scoped consumer rules. AndroidManifest entry points
# are retained automatically by Android Gradle Plugin/R8.
#
# Retain line information for retracing Java/Kotlin release crashes when
# the mapping.txt from this EXACT release build is uploaded to Play Console.
-keepattributes SourceFile,LineNumberTable

# AppLovin MAX 4.6.4's optional OMID device attestation integration
# references Amazon PrivacyPass, which is not bundled in Android apps that
# do not install Amazon's attestation SDK. Silence ONLY these three optional
# R8 missing-class diagnostics (reported by AppLovin upstream issue #363).
# This does not suppress any other R8 warnings and does not keep/disable
# obfuscation for any app or SDK code.
-dontwarn com.amazon.privacypass.PrivacyPass
-dontwarn com.amazon.privacypass.VerificationContext
-dontwarn com.amazon.privacypass.callback.AttestAPICallback
