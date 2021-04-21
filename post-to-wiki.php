<?php

    /**
     * Post to Dokuwiki
     *
     * Pulls out email with specified prefix (eg [XYZ123]) and post it to your Dokuwiki.
     * Largely based upon example work of @author Sebastian Krätzig <info@ts3-tools.info>
     *
     * @author Kelvin Quee <kelvin@quee.org>
     */

    $path_to_doku = '../../';	// Relative path to Dokuwiki root. TODO - Convert into plugin.
    $namespace = 'personal';	// Namespace to create wiki pages in. TODO - Support deeper levels of namespaces.
    $target_mailbox = '{imap.migadu.com:993/imap/ssl}INBOX';
    $target_mail_subject_prefix = '[9d8uu]'; // Only mails with subject line beginning with this will be retrieved and created into wiki pages.
    $mail_username = 'post-to-wiki@quee.org';
    $mail_password = 'Gills0-Reward-Carport-Islamist-Glitzy';

    require_once __DIR__.'/vendor/autoload.php';	// Path to composer.

    declare(strict_types=1);

	// Check path to Dokuwiki and version is correct. TODO
	if (file_exists($path_to_doku.'VERSION')) {
  			$print_version = file_get_contents($path_to_doku.'VERSION');
  			echo $print_version;
		} else {
			exit('File VERSION does not exist. Please check Dokuwiki path is correct');
		}

    use PhpImap\Exceptions\ConnectionException;
    use PhpImap\Mailbox;

    $mailbox = new Mailbox(
        $target_mailbox,
        $mail_username,
        $mail_password,
        __DIR__, // Directory, where attachments will be saved (optional)
        'US-ASCII' // Server encoding (optional)
    );

    try {
        $mail_ids = $mailbox->searchMailbox('SUBJECT "[9d8uu]"'); // Previously working
        //$mail_ids = $mailbox->searchMailbox('SUBJECT "'.$target_mail_subject_prefix.'"'); // Previously working
    } catch (ConnectionException $ex) {
        die('IMAP connection failed: '.$ex->getMessage());
    } catch (Exception $ex) {
        die('An error occured: '.$ex->getMessage());
    }

    foreach ($mail_ids as $mail_id) {
        echo "+------ P A R S I N G ------+\n";

        $email = $mailbox->getMail(
            $mail_id, // ID of the email, you want to get
            false // Do NOT mark emails as seen (optional)
        );

        echo 'from-name: '.(string) (isset($email->fromName) ? $email->fromName : $email->fromAddress)."\n";
        echo 'from-email: '.(string) $email->fromAddress."\n";
        echo 'to: '.(string) $email->toString."\n";
        echo 'subject: '.(string) $email->subject."\n";
        echo 'message_id: '.(string) $email->messageId."\n";

        echo 'mail has attachments? ';
        if ($email->hasAttachments()) {
            echo "Yes\n";
        } else {
            echo "No\n";
        }

        if (!empty($email->getAttachments())) {
            echo \count($email->getAttachments())." attachements\n";
        }
/*        if ($email->textHtml) {                               // Attempts to get HTML portion of emails first
            echo "Message HTML:\n".$email->textHtml;
        } else {
            echo "Message Plain:\n".$email->textPlain;
        } */
        
        echo "Message Plain:\n".$email->textPlain;
        // Future - HTML to Markdown
        // $email->textHtml
        // https://github.com/thephpleague/html-to-markdown
        
        
        //Write to Dokuwiki
        
        $pagename = strtolower(preg_replace('/[[:space:]]+/', '-', trim(explode(']',(string) $email->subject)[1]))); //Create wikipage name using multiple operations to make it Dokuwiki-ish.
        $target_page = $path_to_doku.'data/pages/'.$namespace.'/'.$pagename.'.txt'; // Future - Support deeper namespaces
        echo 'writing to target_page'.$target_page; //Begin writing

        
        if (file_exists($target_page)) {
        	echo('Error: This wiki page already exists.');
		} 
        else {
        	$fp = fopen($target_page, 'w+') or exit('Error: Cannot open file to create wiki page.');
			echo 'writing to target_page'.$target_page; //Begin writing
			fwrite($fp, $email->textPlain) or exit('ERROR: Cannot write to configuration file.');
        	flock($fp, LOCK_UN) or exit('ERROR: Cannot unlock file');
			fclose($fp);
			echo 'Wiki page successfully created, written, and closed.';
		}

    }

    $mailbox->disconnect();

