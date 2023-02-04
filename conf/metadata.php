<?php
/**
 * Metadata for configuration manager plugin
 * Additions for the remotehostgroup plugin
 *
 */
$meta['namespace']  = array('string');
$meta['target_mailbox']  = array('string');
$meta['mail_username']  = array('string');
$meta['mail_password']  = array('password','_code' => 'base64');
$meta['allowed_domain']  = array('string');
$meta['enable_insert_section'] = array('onoff');
$meta['insert_section_keyword'] = array('string');
$meta['insert_section_page'] = array('string');
$meta['insert_section_trigger'] = array('string');
$meta['insert_section_heading'] = array('string');
$meta['insert_section_expire'] = array('numeric');