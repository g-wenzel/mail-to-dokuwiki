<?php
/**
 * Options for the remotehostgroup Plugin
 */
$conf['namespace'] = 'email-archive'; //the namespace to put the new wiki pages into
$conf['target_mailbox'] = '{imap.example.com:993/imap/ssl}INBOX'; //the IMAP mailbox
$conf['mail_username'] = 'post-to-wiki@example.com';  // the username for the IMAP mailbox
$conf['mail_password'] = '';  // password for the IMAP mailbox
$conf['allowed_domain'] = 'example.com'; // the domain, from which emails are accepted, ususally your own institution 
$conf['enable_insert_section'] = 0;
$conf['insert_section_keyword'] = 'Post-to-section';
$conf['insert_section_page'] = 'start';
$conf['insert_section_trigger'] = 'keyphrase';
$conf['insert_section_heading'] = 'Wiki-contributor of the Week';
$conf['insert_section_expire'] = 7;