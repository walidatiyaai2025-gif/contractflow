# Data Safety worksheet — complete before Play submission

Google Play requires the developer to declare all data collected or shared by the app, including data handled by third-party SDKs.

The mobile project includes authentication/business-data features plus Firebase Messaging, Google Mobile Ads, AppLovin MAX, image picking, secure storage and local authentication libraries.

| Data / behavior | Likely purpose | Final declaration required |
|---|---|---|
| Account identifiers / username | Authentication and account management | Confirm collection, retention and deletion behavior |
| Customer / supplier / contract information | Core app functionality | Confirm whether fields qualify as personal info |
| Payment / collection records | Core contract operations | Confirm exact fields and server retention |
| Uploaded images / attachments | User-requested functionality | Confirm collection and storage path |
| FCM device token | Push notifications | Confirm collection and deletion on logout/account deletion |
| Advertising identifiers / diagnostics from AdMob or AppLovin | Ads / fraud prevention / analytics as applicable | Use current SDK Data Safety guidance and production settings |
| Local biometric result | Local authentication | Confirm biometric data itself is not transmitted by the app |

## Security checks
- Confirm data is encrypted in transit.
- Confirm account deletion behavior and retained legal/business records.
- Confirm whether users can request deletion inside the app.
- Confirm whether data is shared with third parties beyond service providers/SDKs.

Do not submit guesses. The Play Console declaration must match actual production behavior.
