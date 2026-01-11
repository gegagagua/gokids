# Contabo Server Email Configuration Guide

## პრობლემა: Email-ები არ მიეწოდება Contabo სერვერზე

ეს გზამკვლევი დაგეხმარებათ email-ის კონფიგურაციაში Contabo სერვერზე.

## ნაბიჯი 1: შეამოწმეთ sendmail

```bash
# შეამოწმეთ, არის თუ არა sendmail დაყენებული
which sendmail

# თუ არ არის დაყენებული (Ubuntu/Debian):
sudo apt-get update
sudo apt-get install sendmail

# sendmail-ის კონფიგურაცია
sudo sendmailconfig
```

## ნაბიჯი 2: განაახლეთ .env ფაილი

დაამატეთ ან შეცვალეთ შემდეგი ხაზები `.env` ფაილში:

```env
MAIL_MAILER=sendmail
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="MyKids Garden System"
```

**შენიშვნა:** შეცვალეთ `yourdomain.com` თქვენი რეალური დომენით.

## ნაბიჯი 3: გაასუფთავეთ კონფიგურაციის cache

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/mykids
php artisan config:clear
php artisan cache:clear
```

## ნაბიჯი 4: შეამოწმეთ sendmail

```bash
# ტესტი email-ის გაგზავნა
echo "Test email from Contabo server" | sendmail -v your-email@example.com

# შეამოწმეთ mail queue
mailq

# ნახეთ logs
tail -f /var/log/mail.log
```

## ალტერნატივა: SMTP გამოყენება

თუ sendmail არ მუშაობს, შეგიძლიათ გამოიყენოთ SMTP:

### ვარიანტი A: Contabo Mail Server

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

### ვარიანტი B: Gmail SMTP

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

**Gmail-ისთვის საჭიროა:**
1. ჩართეთ 2-ფაქტორიანი ავთენტიფიკაცია
2. შექმენით App Password
3. გამოიყენეთ App Password ჩვეულებრივი პაროლის ნაცვლად

## ნაბიჯი 5: ტესტი

```bash
# ტესტი OTP-ის გაგზავნა
curl -X POST https://yourdomain.com/api/gardens/send-registration-otp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"test@example.com"}'

# შეამოწმეთ logs
tail -f storage/logs/laravel.log
```

## პრობლემების გადაჭრა

### პრობლემა: sendmail არ მუშაობს

1. შეამოწმეთ sendmail სერვისი:
```bash
sudo systemctl status sendmail
sudo systemctl start sendmail
sudo systemctl enable sendmail
```

2. შეამოწმეთ firewall:
```bash
sudo ufw status
# თუ საჭიროა, გახსენით პორტი 25
sudo ufw allow 25
```

### პრობლემა: Email-ები spam-ში მიდის

1. დაამატეთ SPF record DNS-ში:
```
TXT @ "v=spf1 a mx ~all"
```

2. დაამატეთ DKIM record (თუ Contabo-ს აქვს)

3. შეამოწმეთ DMARC record

### პრობლემა: Connection timeout

1. შეამოწმეთ SMTP პორტები (587, 465, 25)
2. შეამოწმეთ firewall settings
3. შეამოწმეთ Contabo-ს SMTP settings

## სასარგებლო ბრძანებები

```bash
# შეამოწმეთ mail queue
mailq

# გაასუფთავეთ mail queue
sudo postqueue -p | tail -n +2 | awk 'BEGIN {RS = ""} /your-email@example.com/ {print $1}' | sudo postsuper -d -

# ნახეთ mail logs
tail -f /var/log/mail.log
tail -f /var/log/mail.err

# შეამოწმეთ sendmail კონფიგურაცია
sendmail -d0.1 -bv

# ტესტი email გაგზავნა
echo "Test" | sendmail -v your-email@example.com
```

## დამატებითი რესურსები

- Contabo Documentation: https://contabo.com/en/dedicated-servers/
- Laravel Mail Documentation: https://laravel.com/docs/mail
- Sendmail Configuration: https://www.sendmail.org/

