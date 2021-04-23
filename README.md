# Mail to Dokuwiki

Mail to Dokuwiki is a PHP script that creates a new Dokuwiki page from matching emails. Only unread emails with a matching prefix (eg [Secret_Word]) will have their text content and attachments created. You will need to specify an IMAP email box for the script to check and an SMTP server to send a email back to you upon successful creation.

## Dependencies

```bash
"require": {
    "php-imap/php-imap": "^4.1",
    "vlucas/phpdotenv": ">=5.3",
    "phpmailer/phpmailer": "^6.4"
}
```

Please install composer using these instructions - https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos


## Configuration

Please ensure you locate `mail-to-dokuwiki.php` in the folder /lib/plugins/mail-to-dokuwiki. An exmaple configuration file is in `.env.example`. Set the necessary parameters and then rename it to `.env`.

Depending on your SMTP mail server, you may need to change the SMTP encryption from SSL to TLS -

```php
// $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
```

Please also remember to change the corresponding port number. You may need to switch it from `465` to `587`. See this guide for debugging tips - https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting

## Running

Send the target email address am email with a matching subject prefix and an attachment, and test run with -

```bash
/path-to-php/php mail-to-dokuwiki.php
```

If all goes well, the following will be created -

* A new wiki page located in the designated namespace named the email subject line sans the prefix
	* Text content of wiki page will be the text component of the email (HTML is not supported)
	* Files, created from the email attached and prefixed with the current time, will be uploaded and linked as expected 
* A simple email with the direct link to the wiki page will be sent to requesting email address 

If you encounter any permission error, ensure that you have the permission to write to the `/data/pages` and `/data/media` directories.

To get it to run regularly, say every hour on the 5th minute, please setup a cron script -

```bash
crontab -e
```

and -

```
5 * * * * cd /path-to-dokuwiki/lib/plugins/mail-to-dokuwiki/ && /path-to-php/php mail-to-dokuwiki.php
```

## Limitations

* Currently `mail-to-dokuwiki.php` is a simple PHP script and is entirely unaware of the current Dokuwiki instance it resides in and relies heavily on the user for correct configuration.
* Supports only first level namespaces.

## License

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; version 2 of the License


*Kelvin Quee <kelvin@quee.org>*

