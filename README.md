DMG Klantenportaal — Mail setup
===============================

This project uses PHPMailer for sending ticket notification emails. For development we use MailHog to capture outgoing emails locally. For production, use a transactional SMTP provider such as SendGrid, Mailgun, or Postmark.

Quick start (development)
-------------------------
- Start the app and MailHog (example):

```powershell
docker-compose up -d --build
```

- Open MailHog UI: http://localhost:8025 — captured messages appear here.

Send real email (production/staging)
-----------------------------------
1. Create an account on a provider (SendGrid suggested).
2. Create an API key with Mail Send scope.
3. Configure your environment variables (do not commit secrets). Create `.env` from the provided `.env.example` and replace placeholders:

```
MAIL_USE_SMTP=1
MAIL_FROM_ADDRESS=support@dmg.nl
MAIL_FROM_NAME="DMG Klantportaal"
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USER=apikey
SMTP_PASS=SG.your_sendgrid_api_key_here
SMTP_SECURE=tls
```

4. Load `.env` in Docker Compose: the project includes `docker-compose.example.yml` demonstrating `env_file: .env`.

5. Restart containers and test the sending script:

```powershell
docker-compose down
docker-compose up -d --build
docker-compose exec -e TEST_MAIL_TO=you@yourdomain.com web php /var/www/html/test_mail.php
```

DNS / Deliverability
---------------------
- For best deliverability, set up domain authentication (SPF/DKIM) in your SendGrid or provider account. Add the TXT/CNAME records your provider gives you.

Notes
- Use MailHog for development to avoid sending real emails while testing.
- Keep API keys and secrets out of git — use `.env` and add it to `.gitignore`.
