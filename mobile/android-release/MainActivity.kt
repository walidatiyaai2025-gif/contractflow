package com.safecontracts.safecontracts_mobile

import android.app.Activity
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.net.Uri
import android.os.Build
import android.os.Bundle
import io.flutter.embedding.android.FlutterFragmentActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterFragmentActivity() {
    companion object {
        private const val NOTIFICATION_METHOD_CHANNEL = "safecontracts/notifications"
        private const val FILE_METHOD_CHANNEL = "safecontracts/files"
        private const val SAVE_DOCUMENT_REQUEST = 7012
        private const val NOTIFICATION_CHANNEL_ID = "safe_contracts_alerts"
        private const val BANKNOTE_CHANNEL_ID = "safe_contracts_alerts_banknote_counter"
        private const val CASHIER_CHANNEL_ID = "safe_contracts_alerts_cashier_ka_ching"
        private const val COIN_CHANNEL_ID = "safe_contracts_alerts_coin_drop"
        private const val NOTIFICATION_CHANNEL_NAME = "Safe Contracts Alerts"
    }

    private var pendingSaveResult: MethodChannel.Result? = null
    private var pendingSaveBytes: ByteArray? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        ensureNotificationChannels()
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            NOTIFICATION_METHOD_CHANNEL,
        ).setMethodCallHandler { call, result ->
            if (call.method != "showNotification") {
                result.notImplemented()
                return@setMethodCallHandler
            }
            val title = call.argument<String>("title")?.trim().orEmpty()
            val body = call.argument<String>("body")?.trim().orEmpty()
            val iconKey = call.argument<String>("iconKey")?.trim().orEmpty()
            val soundKey = call.argument<String>("soundKey")?.trim().orEmpty()
            val id = call.argument<Int>("id") ?: (System.currentTimeMillis() and 0x7fffffff).toInt()
            if (title.isEmpty() || body.isEmpty()) {
                result.error("invalid_notification", "Notification title and body are required.", null)
                return@setMethodCallHandler
            }
            showNotification(id, title, body, iconKey, soundKey)
            result.success(true)
        }

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            FILE_METHOD_CHANNEL,
        ).setMethodCallHandler { call, result ->
            if (call.method != "saveDocument") {
                result.notImplemented()
                return@setMethodCallHandler
            }
            if (pendingSaveResult != null) {
                result.error("save_in_progress", "Another report download is already open.", null)
                return@setMethodCallHandler
            }
            val filename = call.argument<String>("filename")?.trim().orEmpty()
            val mimeType = call.argument<String>("mimeType")?.trim().orEmpty()
            val bytes = call.argument<ByteArray>("bytes")
            if (filename.isEmpty() || mimeType.isEmpty() || bytes == null || bytes.isEmpty()) {
                result.error("invalid_document", "Report filename, MIME type and content are required.", null)
                return@setMethodCallHandler
            }

            pendingSaveResult = result
            pendingSaveBytes = bytes
            try {
                val intent = Intent(Intent.ACTION_CREATE_DOCUMENT).apply {
                    addCategory(Intent.CATEGORY_OPENABLE)
                    type = mimeType
                    putExtra(Intent.EXTRA_TITLE, filename)
                }
                startActivityForResult(intent, SAVE_DOCUMENT_REQUEST)
            } catch (error: Exception) {
                clearPendingSave()
                result.error("save_unavailable", error.message ?: "Unable to open Android Save As.", null)
            }
        }
    }

    @Deprecated("Deprecated in Android API; retained for Flutter Activity result compatibility.")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode != SAVE_DOCUMENT_REQUEST) return

        val result = pendingSaveResult
        val bytes = pendingSaveBytes
        if (result == null || bytes == null) {
            clearPendingSave()
            return
        }

        if (resultCode != Activity.RESULT_OK || data?.data == null) {
            clearPendingSave()
            result.success(null)
            return
        }

        val uri = data.data!!
        try {
            contentResolver.openOutputStream(uri, "w")?.use { stream ->
                stream.write(bytes)
                stream.flush()
            } ?: throw IllegalStateException("Android did not provide a writable document stream.")
            clearPendingSave()
            result.success(uri.toString())
        } catch (error: Exception) {
            clearPendingSave()
            result.error("save_failed", error.message ?: "Unable to write the report file.", null)
        }
    }

    private fun clearPendingSave() {
        pendingSaveResult = null
        pendingSaveBytes = null
    }

    private fun ensureNotificationChannels() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        manager.createNotificationChannel(
            notificationChannel(
                NOTIFICATION_CHANNEL_ID,
                NOTIFICATION_CHANNEL_NAME,
                null,
            ),
        )
        manager.createNotificationChannel(
            notificationChannel(
                BANKNOTE_CHANNEL_ID,
                "Safe Contracts · Banknote Counter",
                rawSoundUri("banknote_counter"),
            ),
        )
        manager.createNotificationChannel(
            notificationChannel(
                CASHIER_CHANNEL_ID,
                "Safe Contracts · Cashier Ka-ching",
                rawSoundUri("cashier_ka_ching"),
            ),
        )
        manager.createNotificationChannel(
            notificationChannel(
                COIN_CHANNEL_ID,
                "Safe Contracts · Coin Drop",
                rawSoundUri("coin_drop"),
            ),
        )
    }

    private fun notificationChannel(id: String, name: String, soundUri: Uri?): NotificationChannel {
        return NotificationChannel(
            id,
            name,
            NotificationManager.IMPORTANCE_HIGH,
        ).apply {
            description = "Contract, payment and collection alerts from Safe Contracts"
            enableVibration(true)
            lockscreenVisibility = Notification.VISIBILITY_PUBLIC
            if (soundUri != null) {
                val audioAttributes = AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .build()
                setSound(soundUri, audioAttributes)
            }
        }
    }

    private fun rawSoundUri(resourceName: String): Uri =
        Uri.parse("android.resource://$packageName/raw/$resourceName")

    @Suppress("DEPRECATION")
    private fun showNotification(
        id: Int,
        title: String,
        body: String,
        iconKey: String,
        soundKey: String,
    ) {
        ensureNotificationChannels()
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val channelId = notificationChannelId(soundKey)
        val builder = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Notification.Builder(this, channelId)
        } else {
            Notification.Builder(this)
                .setPriority(Notification.PRIORITY_HIGH)
                .setDefaults(Notification.DEFAULT_SOUND or Notification.DEFAULT_VIBRATE)
        }

        val launchIntent = packageManager.getLaunchIntentForPackage(packageName)
        val pendingIntent = launchIntent?.let {
            PendingIntent.getActivity(
                this,
                0,
                it,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
            )
        }

        builder
            .setSmallIcon(iconResource(iconKey))
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(Notification.BigTextStyle().bigText(body))
            .setAutoCancel(true)
            .setVisibility(Notification.VISIBILITY_PUBLIC)
            .setCategory(Notification.CATEGORY_REMINDER)
            .setContentIntent(pendingIntent)

        manager.notify(id, builder.build())
    }

    private fun notificationChannelId(soundKey: String): String = when (soundKey) {
        "banknote_counter" -> BANKNOTE_CHANNEL_ID
        "cashier_ka_ching" -> CASHIER_CHANNEL_ID
        "coin_drop" -> COIN_CHANNEL_ID
        else -> NOTIFICATION_CHANNEL_ID
    }

    private fun iconResource(iconKey: String): Int = when (iconKey) {
        "warning" -> android.R.drawable.ic_dialog_alert
        "success" -> android.R.drawable.checkbox_on_background
        "payment" -> android.R.drawable.ic_menu_agenda
        "contract_due" -> android.R.drawable.ic_menu_today
        else -> android.R.drawable.ic_dialog_info
    }
}
