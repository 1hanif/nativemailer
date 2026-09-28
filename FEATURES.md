# Native Mailer Features

A tour of what Native Mailer does, how to use each feature, and how it works underneath. For installation and setup, see the [README](README.md).

-   [Capturing mail](#capturing-mail)
-   [The inbox](#the-inbox)
-   [Reading an email](#reading-an-email)
-   [HTML checks](#html-checks)
-   [Releasing to a real inbox](#releasing-to-a-real-inbox)
-   [Retention and cleanup](#retention-and-cleanup)
-   [Settings reference](#settings-reference)

---

## Capturing mail

Point any app's SMTP settings at Native Mailer (`127.0.0.1:1025` by default) and every message it sends is caught and stored locally. Nothing is delivered anywhere.

### Works with your existing mail config

-   **Any username and password are accepted.** The catcher offers `AUTH PLAIN` and `AUTH LOGIN` and never checks the credentials, so an app with `MAIL_USERNAME` and `MAIL_PASSWORD` set (for example a copied production `.env`) delivers without changes.
-   **No TLS needed.** STARTTLS isn't offered, so clients send in plain text over the local connection.
-   **ESMTP extensions:** `SIZE`, `8BITMIME` and `SMTPUTF8` are advertised. Plain `HELO` still works for old clients.

### Every recipient, including BCC

-   **To** and **CC** come from the message headers. Display names with commas (`"Doe, John" <john@x.test>`) and address groups are handled correctly.
-   **BCC** is worked out from the SMTP envelope: anyone the message was delivered to who isn't listed in To or CC. BCC never appears in the headers of a sent email, so this is the only way to see it.

### Real-world messages decode correctly

-   **Charsets:** bodies are converted to UTF-8 from whatever the email declares (ISO-8859-1, Windows-1252, and others). Undeclared text that isn't valid UTF-8 is treated as Windows-1252, the most common mislabelled encoding.
-   **Encodings:** quoted-printable and base64 bodies, and RFC 2047 encoded subjects (`=?UTF-8?Q?...?=`).
-   **Filenames:** RFC 2231 names (`filename*=UTF-8''r%C3%A9sum%C3%A9.pdf`), including long names split across several header lines, and RFC 2047 names inside quotes.
-   **Structure:** nested multipart (mixed, alternative and related), inline images, and non-body parts such as calendar invites, which are kept as attachments.

The parser is tested against real messages produced by Symfony Mime (what Laravel's mailer uses), Python's `email` package, and copies of Apple Mail, Thunderbird and Outlook output in `tests/Fixtures/emails/`.

### Size limit

Messages over **50 MB** are refused with SMTP error `552`. A client that declares the size up front is refused before sending any data. The connection stays open, so the next message still gets through. Change the limit with `SMTP_CATCHER_MAX_SIZE` (in bytes).

---

## The inbox

### Live updates

New mail appears the moment it's captured: the catcher broadcasts an `EmailReceived` event that the inbox listens for. A 30-second refresh is kept as a fallback for when the UI is opened in a plain browser, where desktop events don't arrive.

### Search

The search box matches the sender, To, CC and BCC, the subject, **and the message body** (both the text and HTML parts). Searching for a verification code or a line from the email body finds it.

### Catcher status

The line under the inbox title tells you whether mail is being caught:

| Status                        | Meaning                                                                                                                                          |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| 🟢 **Catching mail on…**      | The catcher is up and answering.                                                                                                                 |
| 🟡 **Another program is using port…** | Something else holds the port. Native Mailer recognises its own catcher by its SMTP greeting, so it can tell. Pick another port in Settings. |
| 🔴 **Not running: …**         | The catcher failed to start, with the reason (for example `Socket bind failed: Address already in use`).                                          |

### Unread badge

The number of unread emails is shown on the app icon: the dock on macOS, and launchers that support badges on Linux. It updates as mail arrives, as you read or mark emails, and when emails are deleted. Windows doesn't support app badges.

### Other inbox features

-   **Read state:** new emails are bold with a "New" badge. Opening one marks it read. Bulk actions mark a selection as read or unread.
-   **Attachments:** a paperclip marks emails with attachments.
-   **Filters:** show only read or unread emails.
-   **Notifications:** a desktop notification for each new email. Clicking it opens the email.

---

## Reading an email

The email view shows From, To, CC, BCC, the subject and the time received, with tabs for each part of the message:

| Tab             | Shows                                                               |
| --------------- | ------------------------------------------------------------------- |
| **HTML**        | The rendered email, with inline images and a list of attachments.   |
| **HTML Source** | The raw HTML.                                                       |
| **Text**        | The plain-text part.                                                |
| **Headers**     | Every header, with encoded values decoded.                          |
| **Raw**         | The complete message exactly as received.                           |
| **Attachments** | Every attachment with its type and size, and a download link.       |
| **Checks**      | Problems found in the HTML (see [HTML checks](#html-checks)).       |

### Preview at different widths

Buttons above the preview switch between **Desktop** (full width), **Tablet** (768px) and **Mobile** (375px), so you can see how the email reflows on smaller screens. Drag the bottom edge of the preview to make it taller.

### Links open in your browser

Clicking a link in the preview opens it in your default browser; `mailto:` links open your mail app. The preview itself never navigates away. Outside the desktop app, links open in a new browser tab.

### Is the preview safe?

Email HTML is untrusted, so the preview is locked down:

-   It runs in a **sandboxed iframe** with an opaque origin, so it can't read or change anything in the app.
-   A **Content-Security-Policy** blocks the email's own scripts, including `<script>` tags, `onclick=`/`onerror=` handlers and `javascript:` links.
-   The only script allowed is Native Mailer's own link handler, identified by a random nonce that changes on every view. It passes clicked `http`, `https` and `mailto` links to the app through `postMessage`; anything else is ignored.

Remote images and stylesheets in the email do load, as they would in a real mail client, so tracking pixels will be requested.

### Attachments

-   **Inline images** (referenced in the HTML as `cid:`) are embedded directly into the preview.
-   **Other attachments** are downloaded from the app. Images open in place; everything else downloads.
-   Attachment downloads are served with headers that stop an HTML or SVG attachment from running scripts inside the app.

---

## HTML checks

The **Checks** tab lists mistakes that make an email look broken, get clipped, or leak development URLs to your users. The badge shows how many were found and turns red when any are errors. The checks read the HTML only; they don't visit any links.

| Area              | Flags                                                                                                                                                                                                                  |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Links**         | Links to development hosts (`localhost`, `127.x.x.x`, `*.test`, `*.local`, …), empty or `#` links, `javascript:` links, relative URLs that only work inside your app, `http://` instead of `https://`, and links with no text. |
| **Images**        | Missing alt text, `cid:` images with no matching attachment, images from development hosts, relative URLs, and `http://` sources.                                                                                      |
| **Size**          | HTML over 102 KB, which Gmail clips behind "View entire message".                                                                                                                                                      |
| **Structure**     | No plain-text part, and no mobile viewport tag.                                                                                                                                                                        |
| **CSS**           | `display: flex`, `display: grid` and `position`, which Outlook desktop ignores.                                                                                                                                        |

---

## Releasing to a real inbox

Sometimes you need to see an email in real Gmail or Outlook. **Release** forwards a captured email through a real SMTP server to addresses you choose.

### Setting up a relay

Open **Settings** on the inbox page and fill in **Release relay**:

| Field               | Notes                                                                                                   |
| ------------------- | ------------------------------------------------------------------------------------------------------- |
| **Host** and **Port** | Your SMTP provider, for example `smtp.gmail.com` and `587`. Leave the host empty to turn Release off. |
| **Encryption**      | **STARTTLS** (usually port 587), **TLS** (usually port 465), or **None**.                               |
| **Envelope sender** | Optional. Some providers only accept mail from verified addresses; set one here if yours does.          |
| **Username** and **Password** | Your SMTP login.                                                                              |

The password is stored encrypted with the app key and is never shown again. Leave the field blank when saving to keep the stored password. Clearing the host also deletes the stored password.

### Releasing an email

Open an email, click **Release**, enter one or more addresses separated by commas, and click **Send**.

The **original message is sent unchanged**: the same headers, subject, To and CC as your app produced. Only the SMTP envelope decides who receives it, so recipients see the email exactly as a real user would. If the relay rejects the message, the error is shown in a notification.

---

## Retention and cleanup

Captured mail, especially with attachments, can grow the database quickly.

-   **Automatic cleanup:** in **Settings → Retention**, choose to delete emails older than a number of days, keep at most a number of emails, or both. Cleanup runs every hour while the app is open, and immediately when you save the settings. Leave both empty to keep everything.
-   **Delete all:** the **Delete all** button on the inbox deletes every email and attachment after asking for confirmation, then compacts the database file (SQLite doesn't give freed space back on its own).

Attachments are stored in their own table and are always deleted along with their email.

---

## Settings reference

### In the app (Settings on the inbox page)

| Setting                       | Default    | Notes                                                                                  |
| ----------------------------- | ---------- | -------------------------------------------------------------------------------------- |
| SMTP port                     | `1025`     | Changing it restarts the catcher (about 2 seconds). Update `MAIL_PORT` in your apps.   |
| Delete emails older than      | off        | Days.                                                                                  |
| Keep at most                  | off        | Number of emails.                                                                      |
| Release relay                 | off        | See [Releasing to a real inbox](#releasing-to-a-real-inbox).                           |

### Environment variables

| Variable                | Default      | Notes                                                               |
| ----------------------- | ------------ | ------------------------------------------------------------------- |
| `SMTP_CATCHER_HOST`     | `127.0.0.1`  | Address the catcher listens on.                                     |
| `SMTP_CATCHER_PORT`     | `1025`       | Default port. The port set in the app takes precedence.             |
| `SMTP_CATCHER_TIMEOUT`  | `30`         | Seconds before an idle SMTP connection is closed.                   |
| `SMTP_CATCHER_MAX_SIZE` | `52428800`   | Largest message accepted, in bytes (50 MB).                         |
