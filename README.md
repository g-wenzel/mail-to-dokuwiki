# Mail to Dokuwiki

Mail to Dokuwiki is a PHP script that creates a new Dokuwiki page from matching emails. It is a customized variant of https://github.com/kelvinq/mail-to-dokuwiki - mainly with features removed that I do not need. Unread emails will be pulled from an IMAP-Mailbox and a Dokuwiki-page with their text content and attachments will be created. You will need to specify an IMAP email box for the script to check.

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
        "j0k3r/php-readability": ">=1.2.7",
        "ryakad/pandoc-php": "~1.0"
    }
}
```

Currently PHP-IMAP runs on PHP7. Maybe you need to install PHP-IMAP and (optionally) PHP-tidy and enable the extensions.
```bash
sudo apt-get install php7.4-imap
sudo apt-get install php7.4-tidy
sudo nano /etc/php/7.4/cli/php.ini
```
Search for "imap" and "tidy" and uncomment the line.

You will also need [composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos), [Pandoc](https://pandoc.org/installing.html) and [tidy](http://www.html-tidy.org) (aka html tidy) installed. Assuming these are already in your system, installing Mail-to-Dokuwiki should be as simple as -

```bash
cd /path-to-dokuwiki/lib/plugins/
sudo git clone https://github.com/g-wenzel/mail-to-dokuwiki.git
cd mail-to-dokuwiki
sudo composer install
```
Depending on permissions in your Dokuwiki-Folder you may or may not need sudo.

## Configuration

Please ensure you locate `mail-to-dokuwiki.php` in the folder /lib/plugins/mail-to-dokuwiki. An exmaple configuration file is in `.env.example`. Set the necessary parameters and then rename it to `.env`.


## Running

Send the target email address am email with a matching subject prefix and an attachment, and test run with -

```bash
/path-to-php/php mail-to-dokuwiki.php
```

If all goes well, the following will be created -

* A new wiki page located in the designated namespace named the email subject line or the webpage title

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

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; version 2 of the License.
