#!/usr/bin/env python3
"""Send a test email with attachment to the local SMTP catcher (127.0.0.1:1025)."""
import smtplib
from email.message import EmailMessage
from pathlib import Path

msg = EmailMessage()
msg["From"] = "Test Sender <test@example.com>"
msg["To"] = "iamustapha213@gmail.com"
msg["Subject"] = "Test email with attachment"
msg.set_content("Hello!\n\nThis is a test email with an attachment sent to the SMTP catcher.")

logo = Path(__file__).parent / "logo.png"
msg.add_attachment(
    logo.read_bytes(),
    maintype="image",
    subtype="png",
    filename="logo.png",
)

with smtplib.SMTP("127.0.0.1", 1025, timeout=10) as s:
    s.send_message(msg)

print("Sent to 127.0.0.1:1025 with attachment logo.png")
