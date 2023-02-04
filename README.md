# Mail to Dokuwiki

Mail to Dokuwiki is a Dokuwiki action plugin that creates a new wiki page from matching emails. It is ispired by https://github.com/kelvinq/mail-to-dokuwiki with some code from https://www.dokuwiki.org/tips:mail2page and https://www.dokuwiki.org/plugin:clearhistory. The aim is to create an archive of circular emails inside an organization.

* All emails (not only unread emails) will be pulled from an IMAP-Mailbox and a wiki-page with their text content and attachments will be created. 
* The email subject is prefixed with the date to create a chronologic email-archive.
* The headline shows also date and sender of the email.
* The emails are deleted after processing.
* Only emails from a domain specified in the config menu are processed. Other Emails are deleted.
* Only attachments of MIME-types specified in path_to_dokuwiki/conf/mime.conf  are allowed (application/octet-stream is not allowed).

You will need to specify an IMAP email box for the script to check.

Mail to Dokuwiki processes your content according to the format of your subject line -

| Subject line format |   Any non-URL line    |
| ------------------- | ------------------------------------------------------- |
| Example             | Fwd: Meeting 01/04/2026      |
| Mode                | Convert body of email, text-only, into new Dokuwiki page.    |
| Page title          | Email subject line                                           |
| Page body           | Email body (text-only)                                       |
| Files               | Uploaded to the specified namespace with timestamp appended. |
| Links to files      | Appended to the end of the page       |

## New feature: Post short news to specific section
You can specify a starting keyword to turn the email subject into a section on an existing wiki-page instaed of turning the whole email into a new wiki-page. This is intended to post short news on the landing page. These news-sections expire after a number of days specified in the configuration menu.


| Subject line format |   Keyword Any non-URLS subject line  |
| ------------------- | ------------------------------------------------------- |
| Example             | WCOTW John Doe wrote a great article. This is why his photo is on the landing page for 7 days!     |
| Mode                | Convert subjcet line of email, text-only, into a section Dokuwiki page.    |
| Section title       | Specified in configuration menu, e.g. Wiki-contributor of the week                  |
| Email body           | Ignored                                      |
| Images               | Included in the newly inserted section, only images are processed, any other attachments are discarded |


## Dependencies & Install
bundeled in repo
```json
{
    "require": {
        "php-imap/php-imap": ">=4.1",
        "ueberdosis/pandoc": ">=0.7"
    }
}
```

Maybe you need to install PHP-IMAP and [Pandoc](https://pandoc.org/installing.html).

Download the repo as zipfile an install it in Dokuwiki via the plugin manager.

## Configuration

Configuration can be done via the Config menu on the Admin page.

## Limitations

* Supports only first level namespaces.
