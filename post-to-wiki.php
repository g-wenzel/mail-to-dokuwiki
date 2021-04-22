<?php

    /**
     * Post to Dokuwiki
     *
     * Pulls out email with specified prefix (eg [XYZ123]) and post it to your Dokuwiki.
     * Largely based upon example work of @author Sebastian Krätzig <info@ts3-tools.info>
     *
     * @author Kelvin Quee <kelvin@quee.org>
     */

    declare(strict_types=1);

    $path_to_doku = '../../';	// Relative path to Dokuwiki root. TODO - Convert into plugin.
    $namespace = 'personal';	// Namespace to create wiki pages in. TODO - Support deeper levels of namespaces.
    $target_mailbox = '{imap.migadu.com:993/imap/ssl}INBOX';
    $target_mail_subject_prefix = '[9d8uu]'; // Only mails with subject line beginning with this will be retrieved and created into wiki pages.
    $mail_username = 'post-to-wiki@quee.org';
    $mail_password = 'Gills0-Reward-Carport-Islamist-Glitzy';

    require_once __DIR__.'/vendor/autoload.php';	// Path to composer.

	// Check path to Dokuwiki and version is correct. TODO
	if (file_exists($path_to_doku.'VERSION')) {
  			$print_version = file_get_contents($path_to_doku.'VERSION');
  			echo $print_version;
		} else {
			exit('File VERSION does not exist. Please check Dokuwiki path is correct');
		}

    use PhpImap\Exceptions\ConnectionException;
    use PhpImap\Mailbox;

    function sanitize_filename($target_string) {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower($target_string));
    }

    $mailbox = new Mailbox(
        $target_mailbox,
        $mail_username,
        $mail_password,
        __DIR__, // Directory, where attachments will be saved (optional)
        'US-ASCII' // Server encoding (optional)
    );

    try {
        $mail_ids = $mailbox->searchMailbox('SUBJECT "[9d8uu]" UNSEEN'); // Find all mails with matching subject and not read
    } catch (ConnectionException $ex) {
        die('IMAP connection failed: '.$ex->getMessage());
    } catch (Exception $ex) {
        die('An error occured: '.$ex->getMessage());
    }

    foreach ($mail_ids as $mail_id) {
        echo "+------ P A R S I N G ------+\n";

        $email = $mailbox->getMail(
            $mail_id, // ID of the email, you want to get
            true // Mark retrieved emails as read
        );

        echo 'Marking '.count($mail_ids).' emails as read.\n';

        echo 'from-name: '.(string) (isset($email->fromName) ? $email->fromName : $email->fromAddress)."\n";
        echo 'from-email: '.(string) $email->fromAddress."\n";
        echo 'to: '.(string) $email->toString."\n";
        echo 'subject: '.(string) $email->subject."\n";
        echo 'message_id: '.(string) $email->messageId."\n";

        //Create wikipage name using multiple operations to make it Dokuwiki-ish.
        $pagename_wip = strtolower(preg_replace('/[[:space:]]+/', '-', trim(implode((array_slice(explode(']',(string) $email->subject), 1)),"]")))); 
        $pagename = sanitize_filename($pagename_wip);
        $target_page = $path_to_doku.'data/pages/'.$namespace.'/'.$pagename.'.txt'; // Future - Support deeper namespaces

        $attachments = $email->getAttachments();
        $wikipage_content = $email->textPlain; // We take text only from plain text for now. Future - HTML support.

        foreach ($attachments as $attachment) {

            // Some string gymnastics to create sane attachments filenames. To be improved.
            $ext = pathinfo($attachment->name)['extension'];
            $target_attachment_filename = time().sanitize_filename(pathinfo($attachment->name)['filename']).".".$ext;;
            $target_attachment_filepath = $path_to_doku.'data/media/'.$namespace.'/'.$target_attachment_filename;

            $attachment->setFilePath($target_attachment_filepath);
            $attachment->saveToDisk(); // Save attachment to disk

            // Add attachment Dokuwiki markup to wiki content
            $wikipage_content .= "\n{{ :".$namespace.":".$target_attachment_filename." |}}";
        }
        
        //Write to Dokuwiki
        
        if (file_exists($target_page)) {
        	echo("Error: This wiki page already exists.\n");
		} 
        else {
            file_put_contents($target_page, $wikipage_content, FILE_APPEND | LOCK_EX);
		}

        // TODO
        // Sometimes Dokuwiki's search does not find the newly created files. To run php bin/indexer.php manually.
        // Workaround is to email hyperlinks to newly created pages link to the requester.

    }

    $mailbox->disconnect();

