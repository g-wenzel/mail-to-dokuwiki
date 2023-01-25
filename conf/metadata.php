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