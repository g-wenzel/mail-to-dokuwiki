# Mail to Dokuwiki

Mail to Dokuwiki is a PHP script that creates a new Dokuwiki page from matching emails. It is a customized variant of https://github.com/kelvinq/mail-to-dokuwiki.
The following changes were made:
* Features removed that I do not need (Confirmation Email, Downloading URLS from subject line) 
* Removed php-readability as it did not process html from Emails properly
* switched to a more recent pandoc-wrapper for PHP
* All emails (not only unread emails) will be pulled from an IMAP-Mailbox and a Dokuwiki-page with their text content and attachments will be created. 
* The email subject is prefixed with the date to create a chronologic email-archive.
* The headline shows also date and sender of the email.
* The emails are deleted after processing.
* Only emails from a domain specified in the .env file are processed. Other Emails are deleted.
* Only attachments of MIME-types specified in path_to_dokuwiki/conf/mime.conf  are allowed.
* Use Dokuwiki's own functions for creating pages and saving media. This results in proper meta-file creation and correct read/write-persmissions.

You will need to specify an IMAP email box for the script to check.

Mail to Dokuwiki processes your content according to the format of your subject line -

| Subject line format |   Any non-URL line    |
| ------------------- | --------------------------------------------------------------- |
| Example             | Fwd: Meeting minutes 01/04/2026      |
| Mode                | Convert body of email, text-only, into new Dokuwiki page.    |
| Page title          | Email subject line                                           |
| Page body           | Email body (text-only)                                       |
| Files               | Uploaded to the specified namespace with timestamp appended. |
| Links to files      | Appended to the end of the page       |

## Dependencies

```json
{
    "require": {
        "php-imap/php-imap": ">=4.1",
        "vlucas/phpdotenv": ">=5.3",
        "ueberdosis/pandoc": ">=0.7"
    }
}
```

Currently Dokuwiki runs on PHP7. Maybe you need to install PHP-IMAP and (optionally) PHP-tidy and enable the extensions.
```bash
sudo apt-get install php7.4-imap
sudo nano /etc/php/7.4/cli/php.ini
```
Search for "imap" and uncomment the line.

You will also need [composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos), [Pandoc](https://pandoc.org/installing.html). Assuming these are already in your system, installing Mail-to-Dokuwiki should simple:

Ususally your dokuwiki-folders should not be writable without sudo-permissions. As it is discouraged to run composer as sudo, I suggest to perform the install in your homedirectory first and then move the files to the dokuwiki plugin-folder.

```bash
git clone https://github.com/g-wenzel/mail-to-dokuwiki.git
cd mail-to-dokuwiki
composer install
cd ..
sudo cp -r ./mail-to-dokuwiki/ /var/www/dokuwiki/lib/plugins/
cd /var/www/dokuwiki/lib/plugins/
sudo chown -R www-data:www-data mail-to-dokuwiki/
```
You maybe have to adapt the path to your Dokuwiki. In this example the user and group for Dokuwiki is assumed to be "www-data" (on Apache).

## Configuration

Please ensure you locate `mail-to-dokuwiki.php` in the folder /lib/plugins/mail-to-dokuwiki. An exmaple configuration file is in `.env.example`. Set the necessary parameters and then rename it to `.env`.

Set restricive access for the .env file, as it contains the password.

```bash
cd /var/www/dokuwiki/lib/plugins/mail-to-dokuwiki/
sudo chmod o-rwx .env
```

## Running

Send the target email address am email with a matching subject prefix and an attachment, and test run with 

```bash
sudo -u www-data php mail-to-dokuwiki.php
```

If all goes well, the following a new wiki page located in the designated namespace named the email subject line will be created.



To get it to run regularly, say every hour on the 5th minute, please setup a cron script -

```bash
sudo nano /etc/crontab
```

and (with the dokuwiki unix-user (here: www-data on Apache) -

```
5 * * * * www-data php /var/www/dokuwiki/lib/plugins/mail-to-dokuwiki/mail-to-dokuwiki.php
```

## Limitations

* Currently `mail-to-dokuwiki.php` is a simple PHP script and is entirely unaware of the current Dokuwiki instance it resides in and relies heavily on the user for correct configuration.
* Supports only first level namespaces.

## License

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; version 2 of the License.
