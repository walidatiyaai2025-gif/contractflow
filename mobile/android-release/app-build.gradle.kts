import java.io.File

plugins {
    id("com.android.application")
    id("kotlin-android")
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.safecontracts.safecontracts_mobile"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        applicationId = "com.safecontracts.safecontracts_mobile"
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    val releaseStore = System.getenv("SC_ANDROID_KEYSTORE_PATH")?.trim().orEmpty()
    val releaseStorePassword = System.getenv("SC_ANDROID_KEYSTORE_PASSWORD")?.trim().orEmpty()
    val releaseAlias = System.getenv("SC_ANDROID_KEY_ALIAS")?.trim().orEmpty()
    val releaseKeyPassword = System.getenv("SC_ANDROID_KEY_PASSWORD")?.trim().orEmpty()
    val signingInputs = listOf(releaseStore, releaseStorePassword, releaseAlias, releaseKeyPassword)
    val hasAnySigningInput = signingInputs.any { it.isNotEmpty() }
    val hasAllSigningInputs = signingInputs.all { it.isNotEmpty() }

    if (hasAnySigningInput && !hasAllSigningInputs) {
        throw GradleException(
            "Incomplete SafeContracts Android release signing configuration. " +
                "Provide all SC_ANDROID_KEYSTORE_* variables or none for an unsigned candidate."
        )
    }

    if (hasAllSigningInputs) {
        val store = File(releaseStore)
        if (!store.isFile) {
            throw GradleException("SafeContracts release keystore does not exist at SC_ANDROID_KEYSTORE_PATH")
        }
        signingConfigs {
            create("safecontractsRelease") {
                storeFile = store
                storePassword = releaseStorePassword
                keyAlias = releaseAlias
                keyPassword = releaseKeyPassword
            }
        }
    }

    buildTypes {
        release {
            // Never fall back to debug signing. With no signing secrets Gradle
            // emits an unsigned release candidate; production publishing is a
            // separate gated step that requires verified signing + UAT evidence.
            if (hasAllSigningInputs) {
                signingConfig = signingConfigs.getByName("safecontractsRelease")
            }
            isMinifyEnabled = false
            isShrinkResources = false
        }
    }
}

flutter {
    source = "../.."
}
