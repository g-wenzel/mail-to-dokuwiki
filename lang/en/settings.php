<?php
/*
 * English language file
 *
 * @license	GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author	Esther Brunner <wikidesign@gmail.com>
 */

// for the configuration manager 
$lang['namespace'] = 'the namespace to put the new wiki pages into'; 
$lang['target_mailbox'] = 'The IMAP mailbox, change the default to your mailserver'; 
$lang['mail_username'] = 'the username for the IMAP mailbox, often identical to email address';
$lang['mail_password'] = 'password for the IMAP mailbox';
$lang['allowed_domain'] = 'The domain, from which emails are accepted, ususally your own institution.';
$lang['enable_insert_section'] = 'Enables insert_section feature: Insert email subject as section if a keyword is used in email subject. This is intended for posting short news content to a particular page.';
$lang['insert_section_keyword'] = 'Keyword in email subject that triggers inserting text as section';
$lang['insert_section_page'] = 'Wiki-page to which the email subject is added, namespace can be added optionally, e.g. wiki:features:news';
$lang['insert_section_trigger'] = 'Keyphrase, after which the email subject is added, processed as a regexp. Should be the end of a section';
$lang['insert_section_heading'] = 'Heading of the inserted section with email subject';
$lang['insert_section_expire'] = 'Number of days, after which the inserted section is deleted automatically';