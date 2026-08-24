package com.safecontracts.safecontracts_mobile

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.os.Build
import android.os.Bundle
import io.flutter.embedding.android.FlutterFragmentActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterFragmentActivity() {
    companion object {
        private const val METHOD_CHANNEL = "safecontracts/notifications"
        private const val NOTIFICATION_CHANNEL_ID = "safe_contracts_alerts"
        private const val NOTIFICATION_CHANNEL_NAME = "Safe Contracts Alerts"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        ensureNotificationChannel()
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, METHOD_CHANNEL)
            .setMethodCallHandler { call, result ->
                if (call.method != "showNotification") {
                    result.notImplemented()
                    return@setMethodCallHandler
                }
                val title = call.argument<String>("title")?.trim().orEmpty()
                val body = call.argument<String>("body")?.trim().orEmpty()
                val iconKey = call.argument<String>("iconKey")?.trim().orEmpty()
                val id = call.argument<Int>("id") ?: (System.currentTimeMillis() and 0x7fffffff).toInt()
                if (title.isEmpty() || body.isEmpty()) {
                    result.error("invalid_notification", "Notification title and body are required.", null)
                    return@setMethodCallHandler
                }
                showNotification(id, title, body, iconKey)
                result.success(true)
            }
    }

    private fun ensureNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val channel = NotificationChannel(
            NOTIFICATION_CHANNEL_ID,
            NOTIFICATION_CHANNEL_NAME,
            NotificationManager.IMPORTANCE_HIGH,
        ).apply {
            description = "Contract, payment and collection alerts from Safe Contracts"
            enableVibration(true)
            lockscreenVisibility = Notification.VISIBILITY_PUBLIC
        }
        manager.createNotificationChannel(channel)
    }

    @Suppress("DEPRECATION")
    private fun showNotification(id: Int, title: String, body: String, iconKey: String) {
        ensureNotificationChannel()
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val builder = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Notification.Builder(this, NOTIFICATION_CHANNEL_ID)
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

    private fun iconResource(iconKey: String): Int = when (iconKey) {
        "warning" -> android.R.drawable.ic_dialog_alert
        "success" -> android.R.drawable.checkbox_on_background
        "payment" -> android.R.drawable.ic_menu_agenda
        "contract_due" -> android.R.drawable.ic_menu_today
        else -> android.R.drawable.ic_dialog_info
    }
}
