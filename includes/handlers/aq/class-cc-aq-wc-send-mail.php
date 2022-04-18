<?php

class CC_AQ_WC_Send_Mail extends CC_AQ_WC_Handler {
    protected $handler_type = 'send_mail';

    public function handle() {
        $email = get_option('chefs_corner_notify_email');

        if (!$email) {
            $this->next_handler = '';
            return;
        }

        $date = date("Y-m-d h:i:sa");

        $this->audit('finished importing at ' . $date);

        $to = $email; 
        $from = get_option('admin_email'); 
        $fromName = get_option('blogname'); 
        $subject = 'AQ Audit Log';
        $headers = "From: $fromName"." <".$from.">"; 

        $file = CHEFS_CORNER_AUDIT_FILE;

        $htmlContent = ' 
            <p>Here is your AQ Plugin audit file.</p> 
        '; 

        $semi_rand = md5(time());  
        $mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";  

        $headers .= "\nMIME-Version: 1.0\n" . "Content-Type: multipart/mixed;\n" . " boundary=\"{$mime_boundary}\""; 
        $message = "--{$mime_boundary}\n" . "Content-Type: text/html; charset=\"UTF-8\"\n" . 
            "Content-Transfer-Encoding: base64\n\n" . $htmlContent . "\n\n"; 

        $message .= "--{$mime_boundary}\n"; 
        $fp =    @fopen($file, "rb"); 
        $data =  @fread($fp, filesize($file)); 
    
        @fclose($fp); 
        $data = chunk_split(base64_encode($data)); 
        $message .= "Content-Type: application/octet-stream; name=\"".basename($file)."\"\n" .  
        "Content-Description: ".basename($file)."\n" . 
        "Content-Disposition: attachment;\n" . " filename=\"".basename($file)."\"; size=".filesize($file).";\n" .  
        "Content-Transfer-Encoding: base64\n\n" . $data . "\n\n"; 

        $message .= "--{$mime_boundary}--"; 

        $result = mail($to, $subject, $message, $headers); 

        if ($result) 
            $this->log('audit log sent to ' . $email);
        else
            $this->log('audit log not sent');
    }
}