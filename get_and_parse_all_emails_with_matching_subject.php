<?php

    /**
     * Example: Get and parse all emails which match the subject "part of the subject" with saving their attachments.
     *
     * @author Sebastian Krätzig <info@ts3-tools.info>
     */
    declare(strict_types=1);

    require_once __DIR__.'/vendor/autoload.php';
    $path_to_doku = '../../';
    $namespace = 'personal';

	// Check path to Dokuwiki and version is correct
	if (file_exists($path_to_doku.'VERSION')) {
  			$print_version = file_get_contents($path_to_doku.'VERSION');
  			echo $print_version;
		} else {
			exit('File VERSION does not exist. Please check Dokuwiki path is correct');
		}

    use PhpImap\Exceptions\ConnectionException;
    use PhpImap\Mailbox;

    $mailbox = new Mailbox(
        '{imap.migadu.com:993/imap/ssl}INBOX', // IMAP server and mailbox folder
        'post-to-wiki@quee.org', // Username for the before configured mailbox
        'Gills0-Reward-Carport-Islamist-Glitzy', // Password for the before configured username
        __DIR__, // Directory, where attachments will be saved (optional)
        'US-ASCII' // Server encoding (optional)
    );

    try {
        $mail_ids = $mailbox->searchMailbox('SUBJECT "[9d8uu]"');
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
/*        if ($email->textHtml) {
            echo "Message HTML:\n".$email->textHtml;
        } else {
            echo "Message Plain:\n".$email->textPlain;
        } */
        
        echo "Message Plain:\n".$email->textPlain;
        
        //Write to Dokuwiki
        
        $pagename = preg_replace('/[[:space:]]+/', '-', trim(explode(']',(string) $email->subject)[1])); //Create wikipage name
        $target_page = $path_to_doku.'data/pages/'.$namespace.'/'.$pagename.'.txt';
        echo 'writing to target_page'.$target_page; //Begin writing

        
        if (file_exists($target_page)) {
        	exit('Error: This wiki page already exists.');
		} else {
        	$fp = fopen($target_page, 'w+') or exit('Error: Cannot open file to create wiki page.');
			echo 'writing to target_page'.$target_page; //Begin writing
			fwrite($fp, $email->textPlain) or die('ERROR: Cannot write to configuration file.');
        	flock($fp, LOCK_UN) or die ('ERROR: Cannot unlock file');
			fclose($fp);
			echo 'Wiki page successfully created, written, and closed.'; //Write success
		}


        if (!empty($email->autoSubmitted)) {
            // Mark email as "read" / "seen"
            $mailbox->markMailAsRead($mail_id);
            echo "+------ IGNORING: Auto-Reply ------+\n";
        }

        if (!empty($email_content->precedence)) {
            // Mark email as "read" / "seen"
            $mailbox->markMailAsRead($mail_id);
            echo "+------ IGNORING: Non-Delivery Report/Receipt ------+\n";
        }
    }

    $mailbox->disconnect();
