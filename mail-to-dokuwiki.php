<?php

    /**
     * Post to Dokuwiki
     *
     * Pulls out email with specified prefix (eg [secret_word]) and post it to your Dokuwiki.
     * Largely based upon example work of Sebastian Krätzig <info@ts3-tools.info> (PHPIMAP) and PHPMailer.
     *
     * Now supports adding of HTML emails and also capturing URLs specified in subject lines.
     *
     * As this was supposed to be a Dokuwiki plugin but I have yet to figure out how to make this work, please still include this file within /lib/plugins/post-to-wiki.
     *
     * Supports only first level namespaces only. To support deeper namespaces.
     *
     * @author Kelvin Quee <kelvin@quee.org>
     */

    declare(strict_types=1);
    require_once __DIR__.'/vendor/autoload.php';

    use PhpImap\Exceptions\ConnectionException;
    use PhpImap\Mailbox;
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use Readability\Readability;
    use Pandoc\Pandoc;

    // Load configuration and credentials from .env files
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $tmpdir = __DIR__.'/tmp';
    $path_to_doku = $_ENV['path_to_doku'];	// Relative path to Dokuwiki root. TODO - Convert into plugin.
    $namespace = $_ENV['namespace'];	// Namespace to create wiki pages in. First level only.
    $target_mailbox = $_ENV['target_mailbox'];
    $target_mail_subject_prefix = $_ENV['target_mail_subject_prefix']; // Only mails with subject line beginning with this will be retrieved and created into wiki pages.
    $mail_username = $_ENV['mail_username'];
    $mail_password = $_ENV['mail_password'];    

	// Check path to Dokuwiki and version is the latest stable (2020-07-29 "Hogfather").
	if (file_exists($path_to_doku.'VERSION')) {
  			$print_version = file_get_contents($path_to_doku.'VERSION');
            if (str_contains ($print_version, "Hogfather")) {
                echo ("Dokuwiki version is as expected. Proceeding...\n");
            } else { 
                exit('Version of Dokuwiki is not as expected. You may disable this warning and proceed with caution.');}
		} else {
			exit('File VERSION does not exist. Please check Dokuwiki path is correct');}

    function sanitize_filename($target_string) {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower($target_string));
    }

    function extract_url($target_string) {
        return substr($target_string, strpos($target_string, "http"));
    }

    $mailbox = new Mailbox(
        $target_mailbox,
        $mail_username,
        $mail_password,
    );

    try {
        $mail_ids = $mailbox->searchMailbox('SUBJECT '.$target_mail_subject_prefix.' UNSEEN'); // Find all mails with matching subject and not read
    } catch (ConnectionException $ex) {
        die('IMAP connection failed: '.$ex->getMessage());
    } catch (Exception $ex) {
        die('An error occured: '.$ex->getMessage());
    }

    foreach ($mail_ids as $mail_id) {
        $email = $mailbox->getMail(
            $mail_id, // ID of the email, you want to get
            true // Mark retrieved emails as read
        );

        echo "Marking ".count($mail_ids)." matching emails as read.\n";

        $pagename_wip = trim(substr((string) $email->subject, strlen($target_mail_subject_prefix)));

        if (filter_var(filter_var($pagename_wip, FILTER_SANITIZE_URL), FILTER_VALIDATE_URL)) {
            $url = extract_url(filter_var($pagename_wip, FILTER_SANITIZE_URL)); 
            $html = file_get_contents($url);
            $readability = new Readability($html, $url);
            $result = $readability->init();
            
            if ( !file_exists($tmpdir) ) {
                mkdir ($tmpdir, 0744);
            }
            
            $pandoc = new Pandoc(null, $tmpdir);

            if ($result) {
                $pagename = rtrim(sanitize_filename($readability->getTitle()->textContent), "-");
                $wikipage_content = 
                "====== ".$readability->getTitle()->textContent." ======\n".
                "===== Personal notes =====\n".
                $email->textPlain."\n".
                "===== Webpage =====\n".
                $pandoc->convert($readability->getContent()->getInnerHTML(), "html", "dokuwiki");
                echo("Webpage sanitised and added as Dokuwiki page.\n");
            } else {
                echo 'Looks like we couldn\'t find the content. :(';
            }
        }
        else {
            //Else just proceed to add body as text
            if ($email->textHtml) {
                $readability = new Readability($email->textHtml);
                $result = $readability->init();
                if ( !file_exists($tmpdir) ) {
                    mkdir ($tmpdir, 0744);
                }
                $pandoc = new Pandoc(null, $tmpdir);
                $pagename = rtrim(sanitize_filename($pagename_wip), "-");
                $wikipage_content = 
                "====== ".$pagename_wip." ======\n".
                $pandoc->convert($readability->getContent()->getInnerHTML(), "html", "dokuwiki");
                echo("HTML email added as Dokuwiki page.\n");     
            } else {
                $wikipage_content = $email->textPlain;
                echo("Text email added as Dokuwiki page.");
            }
            
            
        }

        //Shared common steps to build Dokuwiki page. To make into discrete functions in the future.
        
        $target_page = $path_to_doku.'data/pages/'.$namespace.'/'.$pagename.'.txt'; 
        $attachments = $email->getAttachments();

        foreach ($attachments as $attachment) {

            // Some string gymnastics to create sane attachments filenames. To be improved.
            $ext = pathinfo($attachment->name)['extension'];
            $target_attachment_filename = time()."-".sanitize_filename(pathinfo($attachment->name)['filename']).".".$ext;;
            $target_attachment_filepath = $path_to_doku.'data/media/'.$namespace.'/'.$target_attachment_filename;

            $attachment->setFilePath($target_attachment_filepath);
            $attachment->saveToDisk(); // Save attachment to disk

            // Add attachment Dokuwiki markup to wiki content
            $wikipage_content .= "\n{{ :".$namespace.":".$target_attachment_filename." |}}";
        }
                
        if (file_exists($target_page)) {
        	echo("Error: This wiki page already exists.\n");
		} 
        else {
            // Write text files (create new page) in Dokuwiki
            file_put_contents($target_page, $wikipage_content, FILE_APPEND | LOCK_EX);
            echo "New wiki page for ".$pagename." successfully created.\n";
            // Send email containing direct link to new wiki page using PHPMailer. Your requiresment may differ and as such please debug using - https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting

            try {
                $mail = new PHPMailer();
                $mail->isSMTP();
                $mail->SMTPDebug = SMTP::DEBUG_OFF;
                $mail->Host = $_ENV['smtp_server'];
                $mail->Port = $_ENV['smtp_port'];
                $mail->SMTPAuth = true;
                $mail->Username = $_ENV['smtp_username'];
                $mail->Password = $_ENV['smtp_password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // You may need PHPMailer::ENCRYPTION_STARTTLS. In that case, your SMTP port may be 587.
                $mail->AuthType = 'PLAIN';

                $mail->Subject = $pagename." has been posted to ".$_ENV['your_dokuwiki_url'];
                $mail->Body = "Your new wiki page is at ".$_ENV['your_dokuwiki_url']."/".$namespace."/".$pagename;           
                
                $mail->setFrom($_ENV['mail_username'], 'Post-to-Dokuwiki');
                $mail->addAddress($email->fromAddress);
                $mail->send();
                echo "Email for ".$pagename." successfully sent.\n";
            }
            catch (Exception $e) {
                echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
            }
		}
    }
    $mailbox->disconnect();
    //Now, re-index the wiki
    `../../../bin/indexer.php -q`;