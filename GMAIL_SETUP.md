# Gmail Setup (per teammate)

The Notifications page can send/receive real email through Gmail. Two things make
this work, and **both are local to your own machine** — neither is in git, so
pulling the code alone does not connect Gmail for you.

| What | Where it lives | Shared how |
|---|---|---|
| Google client ID / secret | `config/gmail.local.php` | copied by hand (never git) |
| The connected Gmail account | your own MySQL database | you connect it yourself, once |

## 1. Get `config/gmail.local.php`

This file is git-ignored on purpose — it holds a real Google client secret, and
a shared class repo is not a safe place for that.

**Ask a teammate who already has it working to send you their `config/gmail.local.php`**
directly (Discord, USB, etc. — not through git), and drop it in your own
`config/` folder at the same path.

If nobody has it yet, or you want your own Google Cloud project instead of
sharing one:

1. Copy `config/gmail.local.example.php` to `config/gmail.local.php`.
2. Go to [console.cloud.google.com](https://console.cloud.google.com) → create/select a project.
3. **APIs & Services → Library** → enable **Gmail API**.
4. **APIs & Services → OAuth consent screen** → User type **External**, keep it in **Testing**.
   Add the scopes `gmail.readonly` and `gmail.send`.
5. **APIs & Services → Credentials → Create credentials → OAuth client ID** → **Web application**.
6. Under **Authorized redirect URIs**, add exactly: `http://localhost/sms/api/gmail_callback.php`
7. Paste the **Client ID** and **Client secret** it gives you into `config/gmail.local.php`.

## 2. Add yourself as a test user

While the app is in Google's "Testing" publishing status (the default, and fine
for a school project), only accounts explicitly listed can sign in.

**OAuth consent screen → Test users → + Add users** → add the Gmail address
you're going to connect with. Without this you'll see **"Access blocked... has
not completed the Google verification process."**

If you're sharing a client ID/secret someone else created, ask them to add your
test Gmail address there — they own that Google Cloud project, not you.

## 3. Connect Gmail on your own machine

The *connected account* is stored in your own local database, not in git and
not shared with teammates. Each person connects separately:

1. Start XAMPP (Apache + MySQL) and open `http://localhost/sms`.
2. Sign in, go to **Notifications**.
3. Click **Connect Gmail**, sign in with the test Gmail account, allow both permissions.
4. You should land back on Notifications with **"Connected as your-account@gmail.com"** and a green dot.

## Troubleshooting

| You see | Why | Fix |
|---|---|---|
| "Gmail is not set up yet" | `config/gmail.local.php` missing or has placeholder values | Step 1 |
| "Access blocked... has not completed the Google verification process" | Your Gmail address isn't a test user | Step 2 |
| Connect button redirects but nothing happens after Google | Redirect URI in Google Cloud doesn't exactly match `http://localhost/sms/api/gmail_callback.php` | Fix the URI in Google Cloud Console |
| "Gmail authorization expired — reconnect to continue" | Access was revoked/expired at Google's end | Click **Connect Gmail** again |
| Works for you, not for a classmate after they `git pull` | Expected — see the table above | Send them your `config/gmail.local.php` and have them do Step 3 themselves |

Switching the whole team from a temporary test account to the official school
Gmail later: whoever has that official account clicks **Disconnect** then
**Connect Gmail** and signs in with it. Nothing else changes — same config
file, same code, same page.
