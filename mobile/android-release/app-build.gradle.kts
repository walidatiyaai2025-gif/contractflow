import java.io.File

plugins {
    id("com.android.application")
    id("dev.flutter.flutter-gradle-plugin")
    id("com.google.gms.google-services")
}

android {
    namespace = "com.safecontracts.enterprise"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        applicationId = "com.safecontracts.enterprise"
        minSdk = maxOf(flutter.minSdkVersion, 23)
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    flavorDimensions += "environment"
    productFlavors {
        create("dev") {
            dimension = "environment"
            applicationIdSuffix = ".dev"
            versionNameSuffix = "-dev"
            resValue("string", "app_name", "Enterprise Safe Contracts Dev")
        }
        create("staging") {
            dimension = "environment"
            applicationIdSuffix = ".staging"
            versionNameSuffix = "-staging"
            resValue("string", "app_name", "Enterprise Safe Contracts Staging")
        }
        create("production") {
            dimension = "environment"
            resValue("string", "app_name", "Enterprise Safe Contracts")
        }
    }

    val releaseStore = System.getenv("ESC_ANDROID_KEYSTORE_PATH")?.trim().orEmpty()
    val releaseStorePassword = System.getenv("ESC_ANDROID_KEYSTORE_PASSWORD")?.trim().orEmpty()
    val releaseAlias = System.getenv("ESC_ANDROID_KEY_ALIAS")?.trim().orEmpty()
    val releaseKeyPassword = System.getenv("ESC_ANDROID_KEY_PASSWORD")?.trim().orEmpty()
    val signingInputs = listOf(releaseStore, releaseStorePassword, releaseAlias, releaseKeyPassword)
    val hasAnySigningInput = signingInputs.any { it.isNotEmpty() }
    val hasAllSigningInputs = signingInputs.all { it.isNotEmpty() }

    if (hasAnySigningInput && !hasAllSigningInputs) {
        throw GradleException(
            "Incomplete Enterprise Safe Contracts Android release signing configuration. " +
                "Provide all ESC_ANDROID_* signing variables or none for an unsigned candidate."
        )
    }

    if (hasAllSigningInputs) {
        val store = File(releaseStore)
        if (!store.isFile) {
            throw GradleException("ESC release keystore does not exist at ESC_ANDROID_KEYSTORE_PATH")
        }
        signingConfigs {
            create("enterpriseRelease") {
                storeFile = store
                storePassword = releaseStorePassword
                keyAlias = releaseAlias
                keyPassword = releaseKeyPassword
            }
        }
    }

    buildTypes {
        release {
            if (hasAllSigningInputs) {
                signingConfig = signingConfigs.getByName("enterpriseRelease")
            }
            isMinifyEnabled = false
            isShrinkResources = false
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
