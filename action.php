<?php
    /**
     * Post to Dokuwiki by email
     * 
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
            $controller->register_hook('INDEXER_TASKS_RUN', 'BEFORE',  $this, 'remove_expired_inserted_section', array());
        }


        function get_mail_and_post(&$event, $param){
            global $conf;

            if ($this->run) return;
            $this->run = true;

            $namespace = $this->getConf('namespace');	// Namespace to create wiki pages in. First level only.
            $allowed_domain = $this->getConf('allowed_domain');    // only emails form specific domain are allowed
            $insert_section_feature_enabled = $this->getConf('enable_insert_section');
            $insert_section_keyword = $this->getConf('insert_section_keyword');
            $insert_section_page = $this->getConf('insert_section_page');

            $mailbox = new Mailbox(
                $this->getConf('target_mailbox'), 
                $this->getConf('mail_username'), 
                conf_decodeString($this->conf['mail_password'])
            );
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

                $allowed_mime_types = $this->list_allowed_mime_types();

                foreach ($mail_ids as $mail_id) {
                    $email = $mailbox->getMail(
                        $mail_id, // ID of the email, you want to get
                        true // Mark retrieved emails as read
                    );

                    if ($allowed_domain == $email->fromHost) { // accept only emails from particular domain
                        $subject = $this->clean_email_subject($email->subject);
                        $sender = (string) $email->headers->sender[0]->personal;
                        $date = $email->date;
                        $insert_section_triggered = false;
                        if (filter_var(filter_var($subject, FILTER_SANITIZE_URL), FILTER_VALIDATE_URL)) {
                            echo 'URL as email subject is not supported';           
                        }
                        else {
                            if (($insert_section_feature_enabled) and (str_starts_with($subject,$insert_section_keyword))){
                                $insert_section_triggered = true;
                                $subject = str_ireplace($insert_section_keyword,'',$subject);
                                $subject = trim($subject);
                                $wikipage_content = $this->make_pagetext_with_inserted_section($subject, $insert_section_page);
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
                                    $wikipage_content = filter_var($headline.$converted_textHtml, FILTER_SANITIZE_STRING);     
                                } 
                                else {
                                    $wikipage_content = filter_var($headline.$email->textPlain, FILTER_SANITIZE_STRING);
                                }
                            }
                    
                            $target_page = DOKU_INC.'data/pages/'.$namespace.'/'.date("Y-m-d",strtotime($date))."--".$pagename.'.txt'; 
                        
                                
                            if ((!$insert_section_triggered) and (file_exists($target_page))) {
                                echo("Error: This wiki page already exists.\n");
                            } 
                            else {
                                //save attachments and add reference to wikipage text
                                $attachments = $email->getAttachments();
                                foreach ($attachments as $attachment) {
                                    $mime_string=explode(';',$attachment->mime); //cut off potential additional charset specification
                                    $mime_string=$mime_string[0];
                                    if (in_array($mime_string,$allowed_mime_types)) {
                                        if ((!$insert_section_triggered) or (stristr($mime_string,'image'))) { // only images allowed in inserted sections
                                            $attachment_filename = cleanID($attachment->name);
                                            $media_fn   = mediaFN($namespace.':'.$attachment_filename);
                                            if(io_saveFile($media_fn,$attachment->getContents())){
                                                chmod($media_fn, $conf['fmode']);

                                                if ($insert_section_triggered) {
                                                    // add an image attached to the email with section keyword
                                                    $wikipage_content = str_replace($subject,$subject."\r\n{{ ::".$namespace.":".$attachment_filename."?200|}}\r\n",$wikipage_content);
                                                }
                                                else {
                                                    // Add attachment Dokuwiki markup to wiki content
                                                    $wikipage_content .= "\n\n{{ :".$namespace.":".$attachment_filename." |}}";
                                                }
                                            }
                                        }
                                    }
                                }
                                $wikipage_content = cleanText($wikipage_content);
                                if ($insert_section_triggered) {
                                    // write wiki page with newly inserted section
                                    saveWikiText($insert_section_page,$wikipage_content,'submitted by email');
                                    idx_addPage($insert_section_page);
                                }
                                else {
                                    // Write text files in Dokuwiki, create new page  
                                    saveWikiText($namespace.':'.date("Y-m-d",strtotime($date))."--".$pagename,$wikipage_content,'submitted by email');  
                                    idx_addPage($namespace.':'.date("Y-m-d",strtotime($date))."--".$pagename);
                                }
                            }
                        }
                    }
                    $mailbox->deleteMail($mail_id);
                    $mailbox->expungeDeletedMails();
                }
            }
            $mailbox->disconnect();
            touch($conf['cachedir'].'/lastrun');
        }
    


        function clean_email_subject($subject){
            $subject = trim((string) $subject);
            $subject = preg_replace("/^(?:(?:Fwd|Re|FW|WG|AW)\h*:\h*)+/i", '', $subject); // remove Re: or Fwd:
            $subject = str_replace(':','-',$subject); // replace colons
            $subject = filter_var($subject, FILTER_SANITIZE_STRING);
            return $subject;
        }

        function list_allowed_mime_types(){
            $excluded_mime = 'application/octet-stream'; // this line and some below would need to be adapted to exclude several mime types
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
            return $allowed_mime_types;
        }


        function make_pagetext_with_inserted_section($subject, $insert_section_page) {
            
            $insert_section_trigger = $this->getConf('insert_section_trigger'); // (regexp-)keyword after which the new section is placed 
            $insert_section_heading = $this->getConf('insert_section_heading'); //heading of the new section, e.g. wiki-contributor of the week
            if (!file_exists(DOKU_INC.'data/pages/'.$insert_section_page.'.txt')) die();
            $wikipage_content = file_get_contents(DOKU_INC.'data/pages/'.$insert_section_page.'.txt');
            if (preg_match_all('/={2,6}\s*'.$insert_section_heading.'\s*={2,6}/',$wikipage_content)==1) { // one inserted section is still present on target page
                // replace previous section by new one
                // regexp to match section including heading
                // match 2 to 6 =, then optional whitepaces, then the section-heading, optional whitespace again, then anything but ==
                $wikipage_content = preg_replace('/={2,6}\s*'.$insert_section_heading.'\s*={2,6}[^==]*/mi',"\r\n\r\n===== ".$insert_section_heading." =====\r\n\r\n".$subject."\r\n\r\n",$wikipage_content);
            }
            elseif (preg_match_all('/'.preg_quote($insert_section_trigger).'/',$wikipage_content)==1) { // place fun-section after specified string (insert trigger)
                // double quotes " are needed if \r\n shall be interpreted as a new line
                $wikipage_content = str_replace($insert_section_trigger,$insert_section_trigger."\r\n\r\n===== ".$insert_section_heading." =====\r\n\r\n".$subject,$wikipage_content);
            }
            else { // place fun-section in the beginning of the page
                $wikipage_content = "\r\n\r\n===== ".$insert_section_heading." =====\r\n\r\n".$subject."\r\n".$wikipage_content;
            }
            return $wikipage_content;
        }

        function remove_expired_inserted_section(&$event, $param) {
            if ($this->getConf('enable_insert_section')){
                $insert_section_page = $this->getConf('insert_section_page');
                if (page_exists($insert_section_page)){
                    $page_not_modified_since = time() - filemtime(wikiFN($insert_section_page));
                    // if page with inserted section was not medified for the number of days specified in config
                    if ($page_not_modified_since > 60*60*24*$this->getConf('insert_section_expire')) {
                        //$wikipage_content = file_get_contents(DOKU_INC.'/data/pages/'.$insert_section_page);
                        $wikipage_content = rawWiki($insert_section_page);
                        // look for inserted section
                        $insert_section_heading = $this->getConf('insert_section_heading');
                        if (preg_match_all('/={2,6}\s*'.$insert_section_heading.'\s*={2,6}[^==]*/mi',$wikipage_content,$matched_section_text)==1) { 
                            // look for media-files (images)
                            if  (preg_match_all('/{{\s*:+[^}}]*\.(?:jpg|jpeg|gif|png)[^}}]*}}/mi',$matched_section_text[0][0],$matched_section_images)>0) {
                                foreach ($matched_section_images as $match) { //delete image
                                    $filename = preg_replace('/{{\s*:+/m','',$match[0]);
                                    $filename = preg_split('/(jpg|jpeg|gif|png)/mi',$filename,-1,PREG_SPLIT_DELIM_CAPTURE);
                                    $filename = $filename[0].$filename[1];
                                    $filename = str_replace(':','/',$filename);
                                    unlink(DOKU_INC.'data/media/'.$filename);
                                }
                            } 
                            $wikipage_content = preg_replace('/={2,6}\s*'.$insert_section_heading.'\s*={2,6}[^==]*/mi','',$wikipage_content);
                            saveWikiText($insert_section_page,$wikipage_content,'section deleted by script');
                        }
                    }
                }
            }
        }
    }
?>