<?php
    /**
     * Post to Dokuwiki by email
     * 
     * Supports adding of HTML emails.
     * Supports only first level namespaces only.
     *
     * @author Gregor Wenzel <gregor.wenzel@charite.de>
     */

    if(!defined('DOKU_INC')) die();
    if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

    require_once(DOKU_PLUGIN.'action.php');
    require_once __DIR__.'/vendor/autoload.php';
    use PhpImap\Exceptions\ConnectionException;
    use PhpImap\Mailbox;
    use Pandoc\Pandoc;

    class action_plugin_mailtodokuwiki extends DokuWiki_Action_Plugin {

        /**
         * if true a process is already running
         * or done in the last 1h
         */
        var $run = false;

        /**
         * Constructor - get  config and check if a check runs in the last 1h
         */
        public function __construct() {
            global $conf;
            $this->loadConfig();
            // check if a runfile exists - if not -> there is no last run
            if (!is_file($conf['cachedir'].'/lastrun')) return;  

            // check last run
            $get = fileatime($conf['cachedir'].'/lastrun');  
            $get = intval($get);
            if ($get+(60*60) > time()) $this->run = true;
        }

        /**
         * return some in
         * @return array
         */
        function getInfo(){
            return confToHash(dirname(__FILE__).'/plugin.info.txt');
        }

        /**
         * Register its handlers with the dokuwiki's event controller
         *
         * we need hook the indexer to trigger the script
         */
        function register(Doku_Event_Handler $controller) {
            $controller->register_hook('INDEXER_TASKS_RUN', 'BEFORE',  $this, 'get_mail_and_post', array());
        }


        function get_mail_and_post(&$event, $param){
            
            $namespace = $this->getConf('namespace');	// Namespace to create wiki pages in. First level only.
            $target_mailbox = $this->getConf('target_mailbox');
            $mail_username = $this->getConf('mail_username');
            $mail_password = conf_decodeString($this->conf['mail_password']);
            $allowed_domain = $this->getConf('allowed_domain');    // only emails form specific domain are allowed

            $excluded_mime = 'application/octet-stream'; // here and below would need to be adapted to exclude several mime types
            $mailbox = new Mailbox($target_mailbox, $mail_username, $mail_password);
            try {
                $mail_ids = $mailbox->searchMailbox(); // Find all mail in in folder
            } catch (ConnectionException $ex) {
                die();
            } catch (Exception $ex) {
                die();
            }
            if (sizeof($mail_ids)>0){ // only run the relevant parts, if there is new email
                
                //check if namespace already exists
                if (!file_exists(DOKU_INC.'data/media/'.$namespace)) {
                    io_createNamespace($namespace, 'media');
                } 

                //get permitted mime-types from dokuwiki config file
                $allowed_mime_types = file (DOKU_INC.'conf/mime.conf', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

                foreach ($mail_ids as $mail_id) {
                    $email = $mailbox->getMail(
                        $mail_id, // ID of the email, you want to get
                        true // Mark retrieved emails as read
                    );

                    if ($allowed_domain == $email->fromHost) { // accept only emails from particular domain
                        $subject = trim((string) $email->subject);
                        $subject = preg_replace("/^(?:(?:Fwd|Re|FW|WG|AW)\h*:\h*)+/i", '', $subject); // remove Re: or Fwd:
                        $subject = str_replace(':','-',$subject); // replace colons
                        $sender = (string) $email->headers->sender[0]->personal;
                        $date = $email->date;
                        if (filter_var(filter_var($subject, FILTER_SANITIZE_URL), FILTER_VALIDATE_URL)) {
                            echo 'URL as email subject is not supported';           
                        }
                        else {
                            $pagename = cleanID($subject);
                            $headline = "====== Email -- ".$pagename." -- ".$sender." -- ".date("d.m.Y",strtotime($date))." ======\n\n";
                            if ($email->textHtml) {
                                $converted_textHtml = (new \Pandoc\Pandoc)
                                    ->from('html')
                                    ->input($email->textHtml)
                                    ->to('dokuwiki')
                                    ->run();
                                $wikipage_content = $headline.$converted_textHtml;     
                            } else {
                                $wikipage_content = $headline.$email->textPlain;
                            }
                            $wikipage_content = cleanText($wikipage_content);
                    
                            $target_page = DOKU_INC.'data/pages/'.$namespace.'/'.date("Y-m-d",strtotime($date))."--".$pagename.'.txt'; 
                        
                                
                            if (file_exists($target_page)) {
                                echo("Error: This wiki page already exists.\n");
                            } 
                            else {
                                
                                $attachments = $email->getAttachments();

                                foreach ($attachments as $attachment) {
                                    $mime_string=explode(';',$attachment->mime); //cut off potential additional charset specification
                                    $mime_string=$mime_string[0];
                                    if (in_array($mime_string,$allowed_mime_types)) {

                                        $attachment_filename = cleanID($attachment->name);
                                        $media_fn   = mediaFN($namespace.':'.$attachment_filename);
                                        if(io_saveFile($media_fn,$attachment->getContents())){
                                            chmod($media_fn, $conf['fmode']);
                                            // Add attachment Dokuwiki markup to wiki content
                                            $wikipage_content .= "\n\n{{ :".$namespace.":".$attachment_filename." |}}";
                                        }
                                    }
                                }
                                // Write text files (create new page) in Dokuwiki
                                saveWikiText($namespace.':'.date("Y-m-d",strtotime($date))."--".$pagename,$wikipage_content,'submitted by email');  
                            }
                        }
                    }
                    $mailbox->deleteMail($mail_id);
                    $mailbox->expungeDeletedMails();
                }
                //Now, re-index the wiki
                include DOKU_INC.'/bin/indexer.php';
            }
            $mailbox->disconnect();
            touch($conf['cachedir'].'/lastrun');
        }
    }
?>