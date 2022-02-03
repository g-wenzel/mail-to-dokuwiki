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
    use Pandoc\Pandoc;

    function sanitize_filename($target_string) {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower($target_string));
    }

    // Load configuration and credentials from .env files
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $tmpdir = __DIR__.'/tmp';
    $path_to_doku = $_ENV['path_to_doku'];	// Relative path to Dokuwiki root. TODO - Convert into plugin.
    $namespace = $_ENV['namespace'];	// Namespace to create wiki pages in. First level only.
    $target_mailbox = $_ENV['target_mailbox'];
    $mail_username = $_ENV['mail_username'];
    $mail_password = $_ENV['mail_password'];
    $allowed_domain = $_ENV['allowed_domain'];    // only emails form specific domain are allowed
    $dokuwiki_unix_user = $_ENV['dokuwiki_unix_user'];

    $excluded_mime='application/octet-stream'; // would need to be adapted to exclude several mime types
   
    // Check path to Dokuwiki and version is the latest stable (2020-07-29 "Hogfather").
 	 if (file_exists($path_to_doku.'VERSION')) {
        $print_version = file_get_contents($path_to_doku.'VERSION');
        if (str_contains ($print_version, "Hogfather")) {
            echo ("Dokuwiki version is as expected. Proceeding...\n");
        } 
        else { 
            exit('Version of Dokuwiki is not as expected. You may disable this warning and proceed with caution.');
        }
    } 
    else {
        exit('File VERSION does not exist. Please check Dokuwiki path is correct');
    } 

    //check if namespace already exists
    if (!file_exists($path_to_doku.'data/pages/'.$namespace)) {
        mkdir($path_to_doku.'data/pages/'.$namespace, 0755);
        chown($path_to_doku.'data/pages/'.$namespace,$dokuwiki_unix_user);
        chgrp($path_to_doku.'data/pages/'.$namespace,$dokuwiki_unix_user);
        mkdir($path_to_doku.'data/media/'.$namespace, 0755);
        chown($path_to_doku.'data/media/'.$namespace,$dokuwiki_unix_user);
        chgrp($path_to_doku.'data/media/'.$namespace,$dokuwiki_unix_user);
    }

    //get permitted mime-types from dokuwiki config file
    $allowed_mime_types = file ($path_to_doku.'conf/mime.conf', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($allowed_mime_types as $line_num => $line) {
        
        if ((preg_match("/^#.*$/",$line)) or (strpos($line,$excluded_mime))){ // if line is a comment, starting with # or the excluded mime type is found
            unset($allowed_mime_types[$line_num]); // delete line
        }
    }
    $allowed_mime_types = array_values($allowed_mime_types); // re-index the array
    foreach ($allowed_mime_types as $line_num => $line) {
        $splitted_line = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY); // cut off file extension specification in config file
        $allowed_mime_types[$line_num] = $splitted_line[1]; // get allowed mime-types as strings
        $allowed_mime_types[$line_num] = trim($allowed_mime_types[$line_num],"!"); // cut off ! in relevant lines
    }

    $mailbox = new Mailbox(
        $target_mailbox,
        $mail_username,
        $mail_password,
    );

    try {
        $mail_ids = $mailbox->searchMailbox(); // Find all mail in in folder from .env
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

        $subject = trim((string) $email->subject);
        $sender = (string) $email->headers->sender[0]->personal;
        $date = $email->date;

        if ($allowed_domain == $email->fromHost) { // accept only emails from particular domain
            if (filter_var(filter_var($subject, FILTER_SANITIZE_URL), FILTER_VALIDATE_URL)) {
                echo 'URL as email subject is not supported';           
            }
            else {
                $pagename = sanitize_filename($subject);
                $headline = "====== Email -- ".$pagename." -- ".$sender." -- ".date("d.m.Y",strtotime($date))." ======\n";
                if ($email->textHtml) {
                    $converted_textHtml = (new \Pandoc\Pandoc)
                        ->from('html')
                        ->input($email->textHtml)
                        ->to('dokuwiki')
                        ->run();
                    $wikipage_content = $headline.$converted_textHtml;
                    echo("HTML email added as Dokuwiki page.\n");      
                } else {
                    $wikipage_content = $headline.$email->textPlain;
                    echo("Text email added as Dokuwiki page.");
                }
            
        
                $target_page = $path_to_doku.'data/pages/'.$namespace.'/'.date("Y-m-d",strtotime($date))."--".$pagename.'.txt'; 
            
                    
                if (file_exists($target_page)) {
                    echo("Error: This wiki page already exists.\n");
                } 
                else {
                    
                    $attachments = $email->getAttachments();

                    foreach ($attachments as $attachment) {
                        $mime_string=explode(';',$attachment->mime); //cut off potential additional charset specification
                        $mime_string=$mime_string[0];
                        if (in_array($mime_string,$allowed_mime_types)) {
                            // Some string gymnastics to create sane attachments filenames. To be improved.
                            $ext = pathinfo($attachment->name)['extension'];
                            $target_attachment_filename = sanitize_filename(pathinfo($attachment->name)['filename']).".".$ext;
                            $target_attachment_filepath = $path_to_doku.'data/media/'.$namespace.'/'.$target_attachment_filename;

                            $attachment->setFilePath($target_attachment_filepath);
                            $attachment->saveToDisk(); // Save attachment to disk

                            // Add attachment Dokuwiki markup to wiki content
                            $wikipage_content .= "\n{{ :".$namespace.":".$target_attachment_filename." |}}";
                        }
                    }
                    // Write text files (create new page) in Dokuwiki
                    file_put_contents($target_page, $wikipage_content, FILE_APPEND | LOCK_EX);
                    echo "New wiki page for ".$pagename." successfully created.\n";    
                }
            }
        }
        $mailbox->deleteMail($mail_id);
        $mailbox->expungeDeletedMails();
    }
    $mailbox->disconnect();
    //Now, re-index the wiki
    $path_to_doku.`/bin/indexer.php -q`;