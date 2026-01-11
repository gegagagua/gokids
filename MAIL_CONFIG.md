# Mail Configuration for Garden OTP

## Current Setup
The system is currently configured to use **log** mailer, which means emails are written to `storage/logs/laravel.log` instead of being sent via SMTP.

## To Enable Real Email Sending

### Option 0: Contabo Server (Sendmail) - RECOMMENDED
For Contabo servers, the easiest way is to use sendmail which is usually pre-installed:

```env
MAIL_MAILER=sendmail
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="MyKids Garden System"
```

**Installation and Configuration Steps:**

1. **Check if sendmail is installed:**
```bash
which sendmail
```

2. **If not installed (Ubuntu/Debian):**
```bash
sudo apt-get update
sudo apt-get install sendmail
```

3. **Configure sendmail (if needed):**
```bash
# Configure sendmail
sudo sendmailconfig

# Or manually edit /etc/mail/sendmail.mc if needed
```

4. **Test sendmail:**
```bash
echo "Test email body" | sendmail -v your-email@example.com
```

5. **Check mail queue:**
```bash
mailq
```

6. **View sendmail logs:**
```bash
tail -f /var/log/mail.log
# or
tail -f /var/log/mail.err
```

**Alternative: Contabo SMTP (if sendmail doesn't work)**

If sendmail doesn't work, you can use Contabo's SMTP server:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="MyKids Garden System"
```

**Note:** Replace `yourdomain.com` with your actual domain name configured in Contabo.

### Option 1: Gmail SMTP
Add these settings to your `.env` file:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="MyKids Garden System"
```

**Note:** For Gmail, you need to:
1. Enable 2-factor authentication
2. Generate an App Password
3. Use the App Password instead of your regular password

### Option 2: Outlook/Hotmail SMTP
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-mail.outlook.com
MAIL_PORT=587
MAIL_USERNAME=your-email@outlook.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@outlook.com
MAIL_FROM_NAME="MyKids Garden System"
```

### Option 3: Custom SMTP Server
```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-server.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="MyKids Garden System"
```

### Option 4: Keep Log Mode (Development)
```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

## Testing Email Configuration

After updating your `.env` file, test the configuration:

```bash
# Test sending OTP
curl -X POST http://localhost:8000/api/gardens/send-otp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"test@example.com"}'

# Check logs
tail -f storage/logs/laravel.log
```

## Email Template

The OTP email uses a beautiful HTML template located at:
`resources/views/emails/garden-otp.blade.php`

The template includes:
- Professional design with green theme
- Large, easy-to-read OTP code
- Security warnings
- 10-minute validity notice
- Responsive design
