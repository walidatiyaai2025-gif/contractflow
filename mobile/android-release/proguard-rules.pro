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
