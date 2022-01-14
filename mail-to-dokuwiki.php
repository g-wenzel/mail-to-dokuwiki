<?php

    /**
     * Post to Dokuwiki - customized variant of https://github.com/kelvinq/mail-to-dokuwiki
     *
     * Pulls out email and post it to your Dokuwiki.
     * Largely based upon example work of Sebastian Krätzig <info@ts3-tools.info> (PHPIMAP) and Kelvin Quee <kelvin@quee.org>.
     *
     * Supports adding of HTML emails.
     *
     * Supports only first level namespaces only.
     *
     * @author Gregor Wenzel <gregor.wenzel@charite.de>
     */

    declare(strict_types=1);
    require_once __DIR__.'/vendor/autoload.php';

    use PhpImap\Exceptions\ConnectionException;
    use PhpImap\Mailbox;
    use Readability\Readability;
    use Pandoc\Pandoc;

    // Load configuration and credentials from .env files
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $tmpdir = __DIR__.'/tmp';
    $path_to_doku = $_ENV['path_to_doku'];	// Relative path to Dokuwiki root. TODO - Convert into plugin.
    $namespace = $_ENV['namespace'];	// Namespace to create wiki pages in. First level only.
    $target_mailbox = $_ENV['target_mailbox'];
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

    $mailbox = new Mailbox(
        $target_mailbox,
        $mail_username,
        $mail_password,
    );

    try {
        $mail_ids = $mailbox->searchMailbox('UNSEEN'); // Find all mails not read
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

        $pagename_wip = trim((string) $email->subject);

        if (filter_var(filter_var($pagename_wip, FILTER_SANITIZE_URL), FILTER_VALIDATE_URL)) {
            echo 'URL as email subject is not supported';           
        }
        else {
            if ($email->textHtml) {
                $readability = new Readability($email->textHtml);
                $result = $readability->init();
                if ( !file_exists($tmpdir) ) {
                    mkdir ($tmpdir, 0744);
                }
                $pandoc = new Pandoc(null, $tmpdir);
                $pagename = rtrim(sanitize_filename($pagename_wip), "-");
                $wikipage_content = 
                "====== Email -- ".date("d.m.Y")."--".$pagename." ======\n".
                $pandoc->convert($readability->getContent()->getInnerHTML(), "html", "dokuwiki");
                echo("HTML email added as Dokuwiki page.\n");     
            } else {
                $wikipage_content = "====== Email -- ".date("d.m.Y")."--".$pagename_wip." ======\n".$email->textPlain;
                echo("Text email added as Dokuwiki page.");
            }
        }

        $target_page = $path_to_doku.'data/pages/'.$namespace.'/'.date("Y-m-d")."--".$pagename.'.txt'; 
        
                
        if (file_exists($target_page)) {
        	echo("Error: This wiki page already exists.\n");
		} 
        else {
            
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
            // Write text files (create new page) in Dokuwiki
            file_put_contents($target_page, $wikipage_content, FILE_APPEND | LOCK_EX);
            echo "New wiki page for ".$pagename." successfully created.\n";    
		}
        $mailbox->delete($mail_id);
    }
    $mailbox->disconnect();
    //Now, re-index the wiki
    `../../../bin/indexer.php -q`;