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

        $pagename_wip = strtolower(preg_replace('/[[:space:]]+/', '-', trim(implode((array_slice(explode(']',(string) $email->subject), 1)),"]")))); 
        $pagename = sanitize_filename($pagename_wip);

        //Create wikipage name using multiple operations to make it Dokuwiki-ish.
        $target_page = $path_to_doku.'data/pages/'.$namespace.'/'.$pagename.'.txt'; // Future - Support deeper namespaces

        $attachments = $email->getAttachments();
        $wikipage_content = $email->textPlain;

        foreach ($attachments as $attachment) {

            $ext = pathinfo($attachment->name)['extension'];
            
            $target_attachment_filename = time().sanitize_filename(pathinfo($attachment->name)['filename']).".".$ext;;
            
            $target_attachment_filepath = $path_to_doku.'data/media/'.$namespace.'/'.$target_attachment_filename;
            $attachment->setFilePath($target_attachment_filepath);
            echo '--> Saving '.(string) $target_attachment_filepath."...\n";
            $attachment->saveToDisk(); // Save attachment to disk

            // Add attachment Dokuwiki markup to wiki content
            $wikipage_content .= "\n{{ :".$namespace.":".$target_attachment_filename." |}}";
        }


        /*if (!empty($email->getAttachments())) {
            echo \count($email->getAttachments())." attachements\n";
        }*/
/*        if ($email->textHtml) {                               // Attempts to get HTML portion of emails first
            echo "Message HTML:\n".$email->textHtml;
        } else {
            echo "Message Plain:\n".$email->textPlain;
        } */
        
        // echo "Message Plain:\n".$email->textPlain;
        // Future - HTML to Markdown
        // $email->textHtml
        // https://github.com/thephpleague/html-to-markdown
        
        
        //Write to Dokuwiki
        
        if (file_exists($target_page)) {
        	echo("Error: This wiki page already exists.\n");
		} 
        else {
/*        	$fp = fopen($target_page, 'w+') or exit("Error: Cannot open file to create wiki page.\n");
*/			echo 'writing to target_page'.$target_page; //Begin writing
/*			fwrite($fp, $email->textPlain) or exit("ERROR: Cannot write to wiki page.\n");
        	flock($fp, LOCK_UN) or exit("Error: Cannot unlock file.\n");
			fclose($fp);*/
            echo "Writing wikipage_content\n".$wikipage_content;
            file_put_contents($target_page, $wikipage_content, FILE_APPEND | LOCK_EX);
			echo "Wiki page successfully created, written, and closed.\n";
		}

    }

    $mailbox->disconnect();

